<?php

namespace App\Http\Controllers;

use App\Constants\ModulePermissions;
use App\Models\Hearing;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class HearingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(): Response|JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::LIST_HEARINGS)) {
            abort(403);
        }

        $query = Hearing::with(['case', 'court', 'hearingTypeName'])->orderBy('id', 'desc');

        // Calendar view (FUN-3 frontend): optional ISO date-range filter
        // so the calendar can request just the hearings for the visible
        // month instead of loading every hearing in the system. Leaves
        // the default (no params) behavior — used by the plain list
        // page — completely unchanged.
        if (request()->filled('from')) {
            $query->whereDate('hearing_date', '>=', request()->input('from'));
        }
        if (request()->filled('to')) {
            $query->whereDate('hearing_date', '<=', request()->input('to'));
        }

        $hearings = $this->paginatedOrFull(
            request(),
            $query,
            [$this, 'formatRow'],
            25,
            ['hearing_date'],
            ['notes', 'outcome']
        );

        return $this->response(true, 'success', $hearings, 200);
    }

    /**
     * ENG-4: shared row formatter, see CasesController::formatRow for why.
     */
    public function formatRow(Hearing $row): array
    {
        return [
            'id' => $row->id,
            'case_id' => $row->case_id,
            'case' => $row->case?->case_number ?? 'Unknown case',
            'court_id' => $row->court_id,
            'court' => $row->court?->name ?? 'Unknown court',
            'hearing_date' => Carbon::parse($row->hearing_date)->format('d/m/Y'),
            'hearing_type' => $row->hearing_type,
            'hearing_type_name' => $row->hearingTypeName?->name ?? 'Unclassified',
            'notes' => $row->notes,
            'outcome' => $row->outcome,
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
        if (! Auth::user()->checkPermissionTo(ModulePermissions::CREATE_HEARINGS)) {
            abort(403);
        }

        $request->validate([
            'case_id' => 'required|integer|exists:cases,id',
            'court_id' => 'required|integer|exists:courts,id',
            'hearing_date' => 'required|date_format:d/m/Y',
            'hearing_type' => 'required|integer|exists:hearing_types,id',
            'notes' => 'nullable|string',
            'outcome' => 'nullable|string',
        ]);

        $hearing = Hearing::create([
            'case_id' => $request->case_id,
            'court_id' => $request->court_id,
            'hearing_date' => Carbon::createFromFormat('d/m/Y', $request->hearing_date)->format('Y-m-d'),
            'hearing_type' => $request->hearing_type,
            'notes' => $request->notes,
            'outcome' => $request->outcome,
        ]);

        return $this->response(true, 'success', $this->formatRow($hearing->load(['case', 'court', 'hearingTypeName'])), 201);
    }

    /**
     * Display the specified resource.
     *
     * @return Response
     */
    public function show(Hearing $hearing)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function edit(Hearing $hearing)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Hearing  $hearing
     * @return Response
     */
    public function update(Request $request, $id): Response|JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::UPDATE_HEARINGS)) {
            abort(403);
        }

        $request->validate([
            'case_id' => 'required|integer|exists:cases,id',
            'court_id' => 'required|integer|exists:courts,id',
            'hearing_date' => 'required|date_format:d/m/Y',
            'hearing_type' => 'required|integer|exists:hearing_types,id',
            'notes' => 'nullable|string',
            'outcome' => 'nullable|string',
        ]);

        $hearing = Hearing::findOrFail($id);
        $hearing->update([
            'case_id' => $request->case_id,
            'court_id' => $request->court_id,
            'hearing_date' => Carbon::createFromFormat('d/m/Y', $request->hearing_date)->format('Y-m-d'),
            'hearing_type' => $request->hearing_type,
            'notes' => $request->notes,
            'outcome' => $request->outcome,
            'updated_by' => auth()->user()->id,
        ]);

        return $this->response(true, 'success', $this->formatRow($hearing->fresh(['case', 'court', 'hearingTypeName'])), 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  Hearing  $hearing
     * @return Response
     */
    public function destroy($id)
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::DELETE_HEARINGS)) {
            abort(403);
        }

        $hearing = Hearing::findOrFail($id);
        $hearing->deleted_by = auth()->user()->id;
        $hearing->save();

        $hearing->delete();

        return $this->response(true, 'success', ['id' => (int) $id], 200);
    }
}
