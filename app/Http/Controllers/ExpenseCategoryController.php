<?php

namespace App\Http\Controllers;

use App\Constants\ModulePermissions;
use App\Models\ExpenseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class ExpenseCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index()
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::LIST_EXPENSES)) {
            abort(403);
        }

        $query = ExpenseCategory::orderBy('id', 'desc');
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
    public function formatRow(ExpenseCategory $row): array
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
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::CREATE_EXPENSES)) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:expense_categories,name',
        ]);

        $type = ExpenseCategory::create([
            'name' => $request->name,
        ]);

        return $this->response(true, 'success', $this->formatRow($type), 201);
    }

    /**
     * Display the specified resource.
     *
     * @return Response
     */
    public function show(ExpenseCategory $expenseCategory)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function edit(ExpenseCategory $expenseCategory)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  ExpenseCategory  $expenseCategory
     * @return JsonResponse
     */
    public function update(Request $request, $id)
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::UPDATE_EXPENSES)) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:expense_categories,name,'.$id,
        ]);

        $type = ExpenseCategory::findOrFail($id);
        $type->name = $request->name;
        $type->updated_by = auth()->user()->id;
        $type->save();

        return $this->response(true, 'success', $this->formatRow($type), 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  ExpenseCategory  $expenseCategory
     * @return Response
     */
    public function destroy($id)
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::DELETE_EXPENSES)) {
            abort(403);
        }

        $type = ExpenseCategory::findOrFail($id);
        $type->deleted_by = auth()->user()->id;
        $type->save();

        $type->delete();

        return $this->response(true, 'success', ['id' => (int) $id], 200);
    }
}
