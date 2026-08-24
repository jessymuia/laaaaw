<?php

namespace App\Http\Controllers;

use App\Constants\ModulePermissions;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    // Broadened from PDF-only. Legal practice documents are routinely
    // Word/Excel and scanned images, not just PDFs.
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/png',
        'image/jpeg',
        'text/plain',
    ];

    // 25MB — up from 10MB, still a deliberate ceiling rather than
    // unlimited, since large files land straight in $10-billed-per-GB
    // cloud storage.
    private const MAX_FILE_SIZE_KB = 25000;

    /**
     * The disk new uploads land on. Prefers S3 whenever it's actually
     * configured (so files get off-site backup by default per FUN-5),
     * and falls back to local disk in dev/unconfigured environments
     * rather than failing the upload outright.
     */
    private function uploadDisk(): string
    {
        return filled(config('filesystems.disks.s3.bucket')) ? 's3' : 'local';
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index() {}

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * If `document_group_id` is supplied and belongs to an existing,
     * accessible document, this is stored as a new *version* of that
     * document rather than a new, unrelated document: the previous
     * current version is flagged `is_current = false` and the new upload
     * becomes current at `version + 1`.
     *
     * @return Response
     */
    public function store(Request $request): Response|JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::CREATE_DOCUMENTS)) {
            abort(403);
        }

        $request->validate([
            'file' => 'required|mimetypes:'.implode(',', self::ALLOWED_MIME_TYPES).'|max:'.self::MAX_FILE_SIZE_KB,
            'title' => 'required|string|max:255',
            'case_id' => 'required|integer|exists:cases,id',
            'document_group_id' => 'nullable|integer|exists:documents,document_group_id',
        ]);

        $file = $request->file('file');
        $filename = $file->getClientOriginalName();
        $randomName = uniqid().'_'.$filename;
        $disk = $this->uploadDisk();

        $filePath = $file->storeAs('cases/docs', $randomName, $disk);

        $version = 1;
        $groupId = null;

        if ($request->filled('document_group_id')) {
            $previousCurrent = Document::where('document_group_id', $request->document_group_id)
                ->where('is_current', true)
                ->first();

            if ($previousCurrent) {
                $version = $previousCurrent->version + 1;
                $groupId = $previousCurrent->document_group_id;
                $previousCurrent->update(['is_current' => false, 'updated_by' => auth()->user()->id]);
            }
        }

        $document = Document::create([
            'title' => $request->input('title'),
            'case_id' => $request->input('case_id'),
            'version' => $version,
            'is_current' => true,
            'filename' => $filename,
            'filepath' => $filePath,
            'full_path' => $filePath,
            'disk' => $disk,
            'mimetype' => $file->getClientMimeType(),
            'filesize' => $file->getSize(),
            'extension' => $file->getClientOriginalExtension(),
        ]);

        // A brand-new document is its own group of one, keyed by its own id
        // (see the FUN-5 migration note on why document_group_id is stable
        // and always points at the first version).
        if (is_null($groupId)) {
            $document->update(['document_group_id' => $document->id]);
        }

        return $this->response(true, 'success', $document, 200);
    }

    /**
     * Display the specified resource.
     *
     * Returns only the current version of each document for the case by
     * default; pass `?all_versions=1` to include every version.
     *
     * @param  Document  $document
     */
    public function show($id): JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::LIST_DOCUMENTS)) {
            abort(403);
        }

        $query = Document::where('case_id', $id);

        if (! request()->boolean('all_versions')) {
            $query->where('is_current', true);
        }

        $documents = $query->orderBy('id', 'desc')->get();
        $documents = $documents->map(function ($row) {
            return [
                'id' => $row->id,
                'document_group_id' => $row->document_group_id,
                'title' => $row->title,
                'filename' => $row->filename,
                'full_path' => $row->full_path,
                'version' => $row->version,
                'is_current' => $row->is_current,
                'filesize' => $row->filesize,
            ];
        });

        return $this->response(true, 'success', $documents, 200);

    }

    /**
     * All versions of a single document, oldest first.
     */
    public function versions($groupId): JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::LIST_DOCUMENTS)) {
            abort(403);
        }

        $versions = Document::where('document_group_id', $groupId)
            ->orderBy('version')
            ->get(['id', 'version', 'is_current', 'filename', 'filesize', 'created_by', 'created_at']);

        return $this->response(true, 'success', $versions, 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function edit(Document $document)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @return Response
     */
    public function update(Request $request, Document $document)
    {
        //
    }

    /**
     * Soft-delete a single version. If the deleted version was the current
     * one, the next most recent remaining version (if any) is promoted to
     * current, so the document group always has at most one current
     * version and callers of show()/preview() never silently see nothing.
     */
    public function destroy($id)
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::DELETE_DOCUMENTS)) {
            abort(403);
        }

        $document = Document::findOrFail($id);
        $wasCurrent = $document->is_current;
        $groupId = $document->document_group_id;

        $document->deleted_by = \auth()->user()->id;
        $document->save();

        $document->delete();

        if ($wasCurrent) {
            $nextVersion = Document::where('document_group_id', $groupId)
                ->orderByDesc('version')
                ->first();

            $nextVersion?->update(['is_current' => true]);
        }

        return $this->response(true, 'success', null, 200);
    }
}
