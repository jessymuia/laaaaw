<?php

namespace App\Http\Controllers;

use App\Constants\ModulePermissions;
use App\Models\Cases;
use App\Models\Client;
use App\Models\Court;
use App\Models\Document;
use App\Models\DocumentAccess;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CasesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(): Response|JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::LIST_CASES)) {
            abort(403);
        }

        $query = Cases::with(['client', 'attorney', 'court'])->orderBy('id', 'desc');

        $cases = $this->paginatedOrFull(
            request(),
            $query,
            [$this, 'formatRow'],
            25,
            ['case_number', 'start_date', 'case_type', 'opposing_party'],
            ['case_number', 'opposing_party', 'police_station']
        );

        return $this->response(true, 'success', $cases, 200);
    }

    /**
     * ENG-4: shared row formatter so index() and the single-resource
     * responses from store()/update() render a case identically, instead
     * of store()/update() re-fetching and reformatting the entire list
     * just to hand back the one row the caller actually needs.
     */
    public function formatRow(Cases $row): array
    {
        return [
            'id' => $row->id,
            'case_number' => $row->case_number,
            'description' => $row->description,
            'client_id' => $row->client_id,
            'client' => $row->client?->name ?? 'Unknown client',
            'assigned_to' => $row->assigned_to,
            'attorney' => $row->attorney?->name,
            'start_date' => $row->start_date ? Carbon::parse($row->start_date)->format('d/m/Y') : null,
            'end_date' => $row->end_date ? Carbon::parse($row->end_date)->format('d/m/Y') : null,
            'case_type' => $row->case_type,
            'police_station' => $row->police_station,
            'court_id' => $row->court_id,
            'court' => $row->court?->name ?? 'Unknown court',
            'opposing_party' => $row->opposing_party,
            'lifecycle_status' => $row->lifecycle_status,
        ];
    }

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
     * @return Response
     */
    public function store(Request $request): Response|JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::CREATE_CASES)) {
            abort(403);
        }

        $request->validate([
            'case_number' => 'required|string|max:255|unique:cases',
            'description' => 'required|string',
            'client_id' => 'required|integer|exists:clients,id',
            'assigned_to' => 'required|integer|exists:users,id',
            'start_date' => 'required|date_format:d/m/Y',
            'end_date' => 'nullable|date_format:d/m/Y|after_or_equal:start_date',
            'case_type' => 'required|string|max:255',
            'police_station' => 'required|string|max:255',
            'court_id' => 'required|integer|exists:courts,id',
            'opposing_party' => 'required|string|max:255',
        ], [
            'case_number.unique' => 'Case number already exists',
        ]);
        $case = Cases::create([
            'case_number' => $request->case_number,
            'description' => $request->description,
            'client_id' => $request->client_id,
            'assigned_to' => $request->assigned_to,
            'start_date' => Carbon::createFromFormat('d/m/Y', $request->start_date)->format('Y-m-d'),
            'end_date' => $request->end_date ? Carbon::createFromFormat('d/m/Y', $request->end_date)->format('Y-m-d') : null,
            'police_station' => $request->police_station,
            'court_id' => $request->court_id,
            'opposing_party' => $request->opposing_party,
            'case_type' => $request->case_type,
        ]);

        // ENG-4: return the created resource, not a full re-fetch of
        // every case in the system.
        return $this->response(true, 'success', $this->formatRow($case->load(['client', 'attorney', 'court'])), 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  Cases  $cases
     * @return JsonResponse
     */
    public function show($id)
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::LIST_CASES)) {
            abort(403);
        }

        if (! $id) {
            abort(404);
        }

        $case = Cases::find($id);

        if (! $case) {
            return $this->response(false, 'error', null, 404);
        }

        $data = [
            'id' => $case->id,
            'assigned' => $case->assigned_to ? (User::withTrashed()->find($case->assigned_to)?->name ?? 'Unknown user') : 'Not assigned',
            'case_number' => $case->case_number,
            'client_id' => $case->client_id,
            'client' => Client::withTrashed()->find($case->client_id)?->name ?? 'Unknown client',
            'court' => $case->court_id ? (Court::withTrashed()->find($case->court_id)?->name ?? 'Unknown court') : '',
            'type' => $case->case_type,
            'police_station' => $case->police_station,
            'start_date' => $case->start_date,
            'opposing_party' => $case->opposing_party,
            'lifecycle_status' => $case->lifecycle_status,
            'description' => $case->description,
        ];

        return $this->response(true, 'success', $data, 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function edit(Cases $cases)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Cases  $cases
     * @return Response
     */
    public function update(Request $request, $id): Response|JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::UPDATE_CASES)) {
            abort(403);
        }

        $case = Cases::findOrFail($id);

        $request->validate([
            'case_number' => 'required|string|max:255|unique:cases,case_number,'.$case->id,
            'description' => 'required|string',
            'client_id' => 'required|integer|exists:clients,id',
            'assigned_to' => 'required|integer|exists:users,id',
            'start_date' => 'required|date_format:d/m/Y',
            'end_date' => 'nullable|date_format:d/m/Y|after_or_equal:start_date',
            'case_type' => 'required|string|max:255',
            'police_station' => 'required|string|max:255',
            'court_id' => 'required|integer|exists:courts,id',
            'opposing_party' => 'required|string|max:255',
        ], [
            'case_number.unique' => 'Case number already exists',
        ]);

        $case->update([
            'case_number' => $request->case_number,
            'description' => $request->description,
            'client_id' => $request->client_id,
            'assigned_to' => $request->assigned_to,
            'start_date' => Carbon::createFromFormat('d/m/Y', $request->start_date)->format('Y-m-d'),
            'end_date' => $request->end_date ? Carbon::createFromFormat('d/m/Y', $request->end_date)->format('Y-m-d') : null,
            'police_station' => $request->police_station,
            'court_id' => $request->court_id,
            'opposing_party' => $request->opposing_party,
            'case_type' => $request->case_type,
            'updated_by' => auth()->user()->id,
        ]);

        return $this->response(true, 'success', $this->formatRow($case->fresh(['client', 'attorney', 'court'])), 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  Cases  $cases
     * @return Response
     */
    public function destroy($id)
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::DELETE_CASES)) {
            abort(403);
        }

        $case = Cases::findOrFail($id);
        $case->deleted_by = auth()->user()->id;
        $case->save();

        $case->delete();

        // ENG-4: return just the deleted id, not a full re-fetch.
        return $this->response(true, 'success', ['id' => (int) $id], 200);
    }

    /**
     * FUN-6: transition a case's lifecycle status, enforcing the allowed
     * transition graph defined on the model rather than letting the client
     * set any arbitrary status.
     */
    public function transitionStatus(Request $request, $id): Response|JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::UPDATE_CASES)) {
            abort(403);
        }

        $request->validate([
            'lifecycle_status' => 'required|in:open,closed,appeal,settled',
        ]);

        $case = Cases::findOrFail($id);

        if (! $case->canTransitionTo($request->lifecycle_status)) {
            return $this->response(
                false,
                "Cannot move a case from '{$case->lifecycle_status}' to '{$request->lifecycle_status}'",
                null,
                422
            );
        }

        $case->update([
            'lifecycle_status' => $request->lifecycle_status,
            'updated_by' => auth()->user()->id,
        ]);

        return $this->response(true, 'success', $this->formatRow($case->fresh(['client', 'attorney', 'court'])), 200);
    }

    public function dropDown(): JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::LIST_CASES)) {
            abort(403);
        }

        $cases = Cases::all();
        $cases = $cases->map(function ($row) {
            return [
                'id' => $row->id,
                'name' => 'Case Number => '.$row->case_number,
                'client_id' => $row->client_id,
            ];
        });

        return $this->response(true, 'success', $cases, 200);
    }

    public function preview()
    {
        $id = \request()->id;
        $doc = Document::findOrFail($id);

        if (! Auth::user()->checkPermissionTo(ModulePermissions::VIEW_DOCUMENTS)) {
            DocumentAccess::create([
                'document_id' => $doc->id,
                'accessed_by' => auth()->user()->id,
                'accessed_date' => now(),
                'action' => 'preview',
                'ip_address' => \request()->ip(),
                'outcome' => 'Unsuccessfull',
                'device_info' => 'device',
            ]);
            abort(403);
        }

        DocumentAccess::create([
            'document_id' => $doc->id,
            'accessed_by' => auth()->user()->id,
            'accessed_date' => now(),
            'action' => 'preview',
            'ip_address' => \request()->ip(),
            'outcome' => 'success',
            'device_info' => 'device', // $browser['device_type']
        ]);

        // Files may live on either disk depending on when they were
        // uploaded (see FUN-5 migration note on the `disk` column).
        // response()->file() only works for local paths, so S3-backed
        // documents are streamed back via a temporary signed download
        // instead of being read into memory here.
        if ($doc->disk === 's3') {
            return Storage::disk('s3')->response($doc->full_path);
        }

        $storagePath = storage_path('app/');
        $full_path = $storagePath.$doc->full_path;

        return response()->file($full_path);
    }
}
