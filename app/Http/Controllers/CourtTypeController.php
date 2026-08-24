<?php

namespace App\Http\Controllers;

use App\Constants\ModulePermissions;
use App\Models\CourtType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class CourtTypeController extends Controller
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

        $query = CourtType::orderBy('id', 'desc');
        $types = $this->paginatedOrFull(
            request(),
            $query,
            [$this, 'formatRow'],
            25,
            ['name'],
            ['name']
        );

        return $this->response(true, 'success', $types, 200);
    }

    /**
     * ENG-4: shared row formatter, see CasesController::formatRow for why.
     */
    public function formatRow(CourtType $row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name,
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
            'name' => 'required|string|max:255|unique:court_types,name',
        ]);

        $type = CourtType::create([
            'name' => $request->name,
        ]);

        return $this->response(true, 'success', $this->formatRow($type), 201);
    }

    /**
     * Display the specified resource.
     *
     * @return Response
     */
    public function show(CourtType $courtType)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function edit(CourtType $courtType)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  CourtType  $courtType
     * @return Response
     */
    public function update(Request $request, $id): Response|JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::UPDATE_COURTS)) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:court_types,name,'.$id,
        ]);

        $type = CourtType::findOrFail($id);
        $type->name = $request->name;
        $type->updated_by = auth()->user()->id;
        $type->save();

        return $this->response(true, 'success', $this->formatRow($type), 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  CourtType  $courtType
     * @return Response
     */
    public function destroy($id)
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::DELETE_COURTS)) {
            abort(403);
        }

        $type = CourtType::findOrFail($id);
        $type->deleted_by = auth()->user()->id;
        $type->save();

        $type->delete();

        return $this->response(true, 'success', ['id' => (int) $id], 200);
    }
}
