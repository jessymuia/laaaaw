<?php

namespace App\Http\Controllers;

use App\Constants\ModulePermissions;
use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class ExpenseController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(): Response|JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::LIST_EXPENSES)) {
            abort(403);
        }

        $query = Expense::with(['categoryName', 'user', 'case'])->orderBy('id', 'desc');

        // Bug fix: the case detail page's "Case Expenses" card called this
        // endpoint with no filter at all, so it displayed every expense
        // in the entire firm on every case's page, not just that case's
        // own expenses — directly contradicting the card's own heading.
        // This filter is opt-in (only applied when case_id is actually
        // passed), so the plain expenses list page's existing behaviour
        // is unchanged.
        if (request()->filled('case_id')) {
            $query->where('case_id', request()->input('case_id'));
        }

        // Note: 'expense_date' in the response is a Carbon-formatted alias
        // of the real `date` column (see formatRow below) — it can't be
        // used as a sort column here since Eloquent would try to
        // ORDER BY a column that doesn't exist. Sortable columns below
        // are limited to keys that are a 1:1 match with a real column.
        $expenses = $this->paginatedOrFull(
            request(),
            $query,
            [$this, 'formatRow'],
            25,
            ['amount', 'description', 'vendor', 'payment_method', 'invoice_number'],
            ['description', 'vendor', 'invoice_number']
        );

        return $this->response(true, 'success', $expenses, 200);
    }

    /**
     * ENG-4: shared row formatter, see CasesController::formatRow for why.
     */
    public function formatRow(Expense $row): array
    {
        return [
            'id' => $row->id,
            'case_id' => $row->case_id,
            'expense_date' => Carbon::parse($row->date)->format('d/m/Y'),
            'amount' => $row->amount,
            'category' => $row->category,
            'category_name' => $row->categoryName ? $row->categoryName->name : '',
            'description' => $row->description,
            'vendor' => $row->vendor,
            'payment_method' => $row->payment_method,
            'invoice_number' => $row->invoice_number,
            'user_id' => $row->user_id,
            'user' => $row->user?->name ?? 'Unknown user',
            'case' => $row->case?->case_number ?? 'Unknown case',
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
        if (! Auth::user()->checkPermissionTo(ModulePermissions::CREATE_EXPENSES)) {
            abort(403);
        }

        $request->validate([
            'case_id' => 'required|integer|exists:cases,id',
            'expense_date' => 'required|date_format:d/m/Y',
            'amount' => 'required|numeric|min:0',
            'category' => 'required|integer|exists:expense_categories,id',
            'description' => 'required|string|max:255',
            'vendor' => 'required|string|max:255',
            'payment_method' => 'required|string|max:255',
            'invoice_number' => 'nullable|string|max:255',
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $expense = Expense::create([
            'case_id' => $request->case_id,
            'date' => Carbon::createFromFormat('d/m/Y', $request->expense_date)->format('Y-m-d'),
            'amount' => $request->amount,
            'category' => $request->category,
            'description' => $request->description,
            'vendor' => $request->vendor,
            'payment_method' => $request->payment_method,
            'invoice_number' => $request->invoice_number,
            'user_id' => $request->user_id,
        ]);

        return $this->response(true, 'success', $this->formatRow($expense->load(['categoryName', 'user', 'case'])), 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  Expense  $expense
     * @return Response
     */
    public function show($id): Response|JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::LIST_EXPENSES)) {
            abort(403);
        }

        $expenses = Expense::where('case_id', $id)->firstOrFail();

        return $this->response(true, 'success', $expenses, 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function edit(Expense $expense)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Expense  $expense
     * @return Response
     */
    public function update(Request $request, $id): Response|JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::UPDATE_EXPENSES)) {
            abort(403);
        }

        $request->validate([
            'case_id' => 'required|integer|exists:cases,id',
            'expense_date' => 'required|date_format:d/m/Y',
            'amount' => 'required|numeric|min:0',
            'category' => 'required|integer|exists:expense_categories,id',
            'description' => 'required|string|max:255',
            'vendor' => 'required|string|max:255',
            'payment_method' => 'required|string|max:255',
            'invoice_number' => 'nullable|string|max:255',
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $expense = Expense::findOrFail($id);
        $expense->update([
            'case_id' => $request->case_id,
            'date' => Carbon::createFromFormat('d/m/Y', $request->expense_date)->format('Y-m-d'),
            'amount' => $request->amount,
            'category' => $request->category,
            'description' => $request->description,
            'vendor' => $request->vendor,
            'payment_method' => $request->payment_method,
            'invoice_number' => $request->invoice_number,
            'user_id' => $request->user_id,
            'updated_by' => auth()->user()->id,
        ]);

        return $this->response(true, 'success', $this->formatRow($expense->fresh(['categoryName', 'user', 'case'])), 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  Expense  $expense
     * @return Response
     */
    public function destroy($id)
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::DELETE_EXPENSES)) {
            abort(403);
        }

        $expense = Expense::findOrFail($id);
        $expense->deleted_by = auth()->user()->id;
        $expense->save();

        $expense->delete();

        return $this->response(true, 'success', ['id' => (int) $id], 200);
    }
}
