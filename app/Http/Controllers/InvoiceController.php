<?php

namespace App\Http\Controllers;

use App\Constants\ModulePermissions;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class InvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::LIST_INVOICES)) {
            abort(403);
        }

        $query = Invoice::with(['case', 'client'])->orderBy('id', 'desc');

        if (auth()->user()->hasRole('admin')) {
            $query->withTrashed()
                ->where(function ($q) {
                    $q->where('workflow_status', 'submitted')
                        ->orWhere('created_by', auth()->user()->id);
                });
        } else {
            $query->where('created_by', auth()->user()->id);
        }

        $invoices = $this->paginatedOrFull(
            request(),
            $query,
            [$this, 'formatRow'],
            25,
            ['invoice_date', 'invoice_due_date'],
            ['invoice_number']
        );

        return $this->response(true, 'success', $invoices, 200);
    }

    /**
     * ENG-4: shared row formatter, see CasesController::formatRow for why.
     */
    public function formatRow(Invoice $row): array
    {
        // Admins can void any invoice; a creator may only delete their
        // own invoice while it's still a draft (not yet submitted).
        $delete = auth()->user()->checkPermissionTo(ModulePermissions::DELETE_INVOICES)
            || ($row->created_by === auth()->user()->id && $row->workflow_status === 'draft');

        return [
            'id' => $row->id,
            'invoice_number' => $row->invoice_number,
            'case_id' => $row->case_id,
            'case' => $row->case?->case_number ?? 'Unknown case',
            'invoice_date' => Carbon::parse($row->invoice_date)->format('d/m/Y'),
            'invoice_due_date' => Carbon::parse($row->invoice_due_date)->format('d/m/Y'),
            'client_id' => $row->client_id,
            'client' => $row->client?->name ?? 'Unknown client',
            'currency' => $row->currency,
            'subtotal' => $row->subtotal,
            'tax_total' => $row->tax_total,
            'total_amount' => $row->total_amount,
            'amount_paid' => $row->amount_paid,
            'workflow_status' => $row->workflow_status,
            'payment_status' => $row->payment_status,
            'status' => $row->trashed() ? 'void' : $row->workflow_status,
            'delete' => $delete,
        ];
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create() {}

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(Request $request): Response|JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::CREATE_INVOICES)) {
            abort(403);
        }

        $request->validate([
            'case_id' => 'required|integer|exists:cases,id',
            'invoice_date' => 'required|date_format:d/m/Y',
            'invoice_due_date' => 'required|date_format:d/m/Y|after_or_equal:invoice_date',
            'client_id' => 'required|integer|exists:clients,id',
        ]);

        $invoice = Invoice::create([
            'case_id' => $request->case_id,
            'invoice_date' => Carbon::createFromFormat('d/m/Y', $request->invoice_date)->format('Y-m-d'),
            'invoice_due_date' => Carbon::createFromFormat('d/m/Y', $request->invoice_due_date)->format('Y-m-d'),
            'client_id' => $request->client_id,
            'workflow_status' => 'draft',
        ]);

        return $this->response(true, 'success', $this->formatRow($invoice->load(['case', 'client'])), 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  Invoice  $invoice
     */
    public function show($id): JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::LIST_INVOICES)) {
            abort(403);
        }

        $invoice = Invoice::findOrFail($id);

        if (! auth()->user()->hasRole('admin') && $invoice->created_by !== auth()->user()->id) {
            abort(403);
        }

        $invoice = [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'case' => $invoice->case()->withTrashed()->first()?->case_number ?? 'Unknown case',
            'client' => $invoice->client()->withTrashed()->first()?->name ?? 'Unknown client',
            'invoice_date' => Carbon::parse($invoice->invoice_date)->format('d/m/Y'),
            'invoice_due_date' => Carbon::parse($invoice->invoice_due_date)->format('d/m/Y'),
            'currency' => $invoice->currency,
            'subtotal' => $invoice->subtotal,
            'tax_total' => $invoice->tax_total,
            'total_amount' => $invoice->total_amount,
            'amount_paid' => $invoice->amount_paid,
            'workflow_status' => $invoice->workflow_status,
            'payment_status' => $invoice->payment_status,
        ];

        return $this->response(true, 'success', $invoice, 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function edit(Invoice $invoice)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Invoice  $invoice
     * @return Response
     */
    public function update(Request $request, $id): Response|JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::UPDATE_INVOICES)) {
            abort(403);
        }

        $request->validate([
            'case_id' => 'required|integer|exists:cases,id',
            'invoice_date' => 'required|date_format:d/m/Y',
            'invoice_due_date' => 'required|date_format:d/m/Y|after_or_equal:invoice_date',
            'client_id' => 'required|integer|exists:clients,id',
        ]);

        $invoice = Invoice::findOrFail($id);

        if (! auth()->user()->hasRole('admin') && $invoice->created_by !== auth()->user()->id) {
            abort(403);
        }

        if ($invoice->workflow_status !== 'draft') {
            return $this->response(false, 'Only draft invoices can be edited', null, 422);
        }

        $invoice->update([
            'case_id' => $request->case_id,
            'invoice_date' => Carbon::createFromFormat('d/m/Y', $request->invoice_date)->format('Y-m-d'),
            'invoice_due_date' => Carbon::createFromFormat('d/m/Y', $request->invoice_due_date)->format('Y-m-d'),
            'client_id' => $request->client_id,
            'updated_by' => auth()->user()->id,
        ]);

        return $this->response(true, 'success', $this->formatRow($invoice->fresh(['case', 'client'])), 200);
    }

    public function sendToAdmin($id): Response|JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::UPDATE_INVOICES)) {
            abort(403);
        }

        $invoice = Invoice::findOrFail($id);

        if (! auth()->user()->hasRole('admin') && $invoice->created_by !== auth()->user()->id) {
            abort(403);
        }

        if ($invoice->workflow_status !== 'draft') {
            return $this->response(false, 'Invoice has already been submitted', null, 422);
        }

        $invoice->update([
            'workflow_status' => 'submitted',
            'updated_by' => auth()->user()->id,
        ]);

        return $this->response(true, 'success', $this->formatRow($invoice->fresh(['case', 'client'])), 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * Admins may void any invoice. A creator may delete their own invoice
     * only while it is still a draft (not yet submitted) — once submitted,
     * only an admin can void it, preserving an audit trail for anything
     * that has left draft state.
     *
     * @param  Invoice  $invoice
     */
    public function destroy($id): JsonResponse
    {
        $invoice = Invoice::findOrFail($id);

        $isAdmin = auth()->user()->checkPermissionTo(ModulePermissions::DELETE_INVOICES);
        $isOwnDraft = $invoice->created_by === auth()->user()->id && $invoice->workflow_status === 'draft';

        if (! $isAdmin && ! $isOwnDraft) {
            return $this->response(false, 'error', null, 404);
        }

        $invoice->deleted_by = auth()->user()->id;
        $invoice->workflow_status = 'void';
        $invoice->save();
        $invoice->delete();

        return $this->response(true, 'success', ['id' => (int) $id], 200);
    }

    public function preview($id): JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::LIST_INVOICES)) {
            abort(403);
        }

        $invoice = Invoice::findOrFail($id);

        if (! auth()->user()->hasRole('admin') && $invoice->created_by !== auth()->user()->id) {
            abort(403);
        }

        $items = InvoiceItem::where('invoice_id', $id)->get();
        $total = InvoiceItem::where('invoice_id', $id)
            ->selectRaw('SUM(tax) as total_tax, SUM(total_amount) as totals')
            ->first();

        $items = $items->map(function ($row) {
            return [
                'item' => $row->description,
                'quantity' => $row->quantity,
                'price' => $row->rate,
                'amount' => $row->total_amount,
            ];
        });

        $invoice = [
            'number' => $invoice->invoice_number,
            'date' => $invoice->invoice_date,
            'due_date' => $invoice->invoice_due_date,
            'client' => $invoice->client()->withTrashed()->first()?->name ?? 'Unknown client',
            'client_address' => $invoice->client()->withTrashed()->first()?->address ?? '',
            'currency' => $invoice->currency,
            'subtotal' => $invoice->subtotal,
            'tax_total' => $invoice->tax_total,
            'total_amount' => $invoice->total_amount,
            'amount_paid' => $invoice->amount_paid,
            'workflow_status' => $invoice->workflow_status,
            'payment_status' => $invoice->payment_status,
            'postShow' => $invoice->workflow_status === 'draft',
        ];

        return $this->response(true, 'success', [$items, $invoice, $total], 200);
    }
}
