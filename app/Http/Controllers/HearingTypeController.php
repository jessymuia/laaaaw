<?php

namespace App\Http\Controllers;

use App\Constants\ModulePermissions;
use App\Models\HearingType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class HearingTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::LIST_HEARINGS)) {
            abort(403);
        }

        $query = HearingType::orderBy('id', 'desc');
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
    public function formatRow(HearingType $row): array
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
     */
    public function store(Request $request): JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::CREATE_HEARINGS)) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:hearing_types,name',
        ]);

        $type = HearingType::create([
            'name' => $request->name,
        ]);

        return $this->response(true, 'success', $this->formatRow($type), 201);
    }

    /**
     * Display the specified resource.
     *
     * @return Response
     */
    public function show(HearingType $hearingType)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function edit(HearingType $hearingType)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  HearingType  $hearingType
     */
    public function update(Request $request, $id): JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::UPDATE_HEARINGS)) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:hearing_types,name,'.$id,
        ]);

        $type = HearingType::findOrFail($id);
        $type->name = $request->name;
        $type->updated_by = auth()->user()->id;
        $type->save();

        return $this->response(true, 'success', $this->formatRow($type), 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  HearingType  $hearingType
     * @return Response
     */
    public function destroy($id)
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::DELETE_HEARINGS)) {
            abort(403);
        }

        $type = HearingType::findOrFail($id);
        $type->deleted_by = auth()->user()->id;
        $type->save();

        $type->delete();

        return $this->response(true, 'success', ['id' => (int) $id], 200);
    }
}
