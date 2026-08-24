<?php

namespace App\Http\Controllers;

use App\Constants\ModulePermissions;
use App\Models\Cases;
use App\Models\Client;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SearchController extends Controller
{
    /**
     * FUN-7: global search across cases, clients and documents.
     *
     * Each entity type is only searched (and only returned) if the current
     * user already holds that module's own LIST permission — global search
     * is a shortcut to existing data, never a way to see more than the
     * normal list views would already show.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:255',
        ]);

        $term = $request->input('q');
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
        $limit = 10;

        $results = [
            'cases' => [],
            'clients' => [],
            'documents' => [],
        ];

        if (Auth::user()->checkPermissionTo(ModulePermissions::LIST_CASES)) {
            $results['cases'] = Cases::where('case_number', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhere('opposing_party', 'like', $like)
                ->orderBy('id', 'desc')
                ->limit($limit)
                ->get(['id', 'case_number', 'description'])
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'title' => $row->case_number,
                    'subtitle' => $row->description,
                    'type' => 'case',
                ]);
        }

        if (Auth::user()->checkPermissionTo(ModulePermissions::LIST_CLIENTS)) {
            $results['clients'] = Client::where('name', 'like', $like)
                ->orWhere('phone_number', 'like', $like)
                ->orderBy('id', 'desc')
                ->limit($limit)
                ->get(['id', 'name', 'phone_number'])
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'title' => $row->name,
                    'subtitle' => $row->phone_number,
                    'type' => 'client',
                ]);
        }

        if (Auth::user()->checkPermissionTo(ModulePermissions::LIST_DOCUMENTS)) {
            $results['documents'] = Document::with('case')
                ->where('title', 'like', $like)
                ->orWhere('filename', 'like', $like)
                ->orderBy('id', 'desc')
                ->limit($limit)
                ->get()
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'title' => $row->title ?: $row->filename,
                    'subtitle' => $row->case?->case_number ?? 'Unknown case',
                    'case_id' => $row->case_id,
                    'type' => 'document',
                ]);
        }

        return $this->response(true, 'success', $results, 200);
    }
}
