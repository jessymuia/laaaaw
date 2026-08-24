<?php

namespace App\Http\Controllers;

use App\Constants\ModulePermissions;
use App\Models\Court;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class CourtController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(): Response|JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::LIST_COURTS)) {
            abort(403);
        }

        $query = Court::with('courtType')->orderBy('id', 'desc');

        $courts = $this->paginatedOrFull(
            request(),
            $query,
            [$this, 'formatRow'],
            25,
            sortableColumns: ['name'],
            searchableColumns: ['name']
        );

        return $this->response(true, 'success', $courts, 200);
    }

    /**
     * ENG-4: shared row formatter, see CasesController::formatRow for why.
     */
    public function formatRow(Court $row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name,
            'type' => $row->type,
            'court_type' => $row->courtType?->name ?? 'Unknown type',
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
        if (! Auth::user()->checkPermissionTo(ModulePermissions::CREATE_COURTS)) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|integer|exists:court_types,id',
        ]);

        $court = Court::create([
            'name' => $request->name,
            'type' => $request->type,
        ]);

        return $this->response(true, 'success', $this->formatRow($court->load('courtType')), 201);
    }

    /**
     * Display the specified resource.
     *
     * @return Response
     */
    public function show(Court $court)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function edit(Court $court)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Court  $court
     * @return Response
     */
    public function update(Request $request, $id): Response|JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::UPDATE_COURTS)) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|integer|exists:court_types,id',
        ]);

        $court = Court::findOrFail($id);
        $court->name = $request->name;
        $court->type = $request->type;
        $court->updated_by = auth()->user()->id;
        $court->save();

        return $this->response(true, 'success', $this->formatRow($court->fresh('courtType')), 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  Court  $court
     * @return Response
     */
    public function destroy($id)
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::DELETE_COURTS)) {
            abort(403);
        }

        $court = Court::findOrFail($id);
        $court->deleted_by = auth()->user()->id;
        $court->save();

        $court->delete();

        return $this->response(true, 'success', ['id' => (int) $id], 200);
    }
}
