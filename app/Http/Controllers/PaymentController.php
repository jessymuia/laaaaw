<?php

namespace App\Http\Controllers;

use App\Constants\ModulePermissions;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    public function index(): Response|JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::LIST_PAYMENTS)) {
            abort(403);
        }

        $query = Payment::with(['invoice', 'receivedBy'])->orderBy('id', 'desc');

        if (request()->has('invoice_id')) {
            $query->where('invoice_id', request()->input('invoice_id'));
        }

        $payments = $this->paginatedOrFull(request(), $query, [$this, 'formatRow']);

        return $this->response(true, 'success', $payments, 200);
    }

    /**
     * ENG-4: shared row formatter, see CasesController::formatRow for why.
     */
    public function formatRow(Payment $row): array
    {
        return [
            'id' => $row->id,
            'receipt_number' => $row->receipt_number,
            'invoice_id' => $row->invoice_id,
            'invoice_number' => $row->invoice?->invoice_number ?? 'Unknown invoice',
            'amount' => $row->amount,
            'payment_date' => Carbon::parse($row->payment_date)->format('d/m/Y'),
            'method' => $row->method,
            'reference_number' => $row->reference_number,
            'received_by' => $row->receivedBy?->name ?? 'Unknown user',
            'notes' => $row->notes,
        ];
    }

    public function store(Request $request): Response|JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::CREATE_PAYMENTS)) {
            abort(403);
        }

        $request->validate([
            'invoice_id' => 'required|integer|exists:invoices,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date_format:d/m/Y',
            'method' => 'required|in:cash,bank_transfer,mobile_money,cheque,card,other',
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $invoice = Invoice::findOrFail($request->invoice_id);

        if ($invoice->workflow_status !== 'submitted') {
            return $this->response(false, 'Payments can only be recorded against a submitted invoice', null, 422);
        }

        $outstanding = (float) $invoice->total_amount - (float) $invoice->amount_paid;
        if ($request->amount > $outstanding + 0.01) {
            return $this->response(false, "Payment of {$request->amount} exceeds the outstanding balance of {$outstanding}", null, 422);
        }

        $payment = Payment::create([
            'invoice_id' => $request->invoice_id,
            'amount' => $request->amount,
            'payment_date' => Carbon::createFromFormat('d/m/Y', $request->payment_date)->format('Y-m-d'),
            'method' => $request->method,
            'reference_number' => $request->reference_number,
            'received_by' => auth()->user()->id,
            'notes' => $request->notes,
        ]);

        return $this->response(true, 'success', ['receipt_number' => $payment->receipt_number], 200);
    }

    public function show($id): JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::LIST_PAYMENTS)) {
            abort(403);
        }

        $payment = Payment::with(['invoice.client', 'receivedBy'])->findOrFail($id);

        return $this->response(true, 'success', [
            'receipt_number' => $payment->receipt_number,
            'invoice_number' => $payment->invoice?->invoice_number,
            'client' => $payment->invoice?->client?->name ?? 'Unknown client',
            'amount' => $payment->amount,
            'payment_date' => Carbon::parse($payment->payment_date)->format('d/m/Y'),
            'method' => $payment->method,
            'reference_number' => $payment->reference_number,
            'received_by' => $payment->receivedBy?->name ?? 'Unknown user',
        ], 200);
    }

    /**
     * Void a payment (soft-delete). The invoice's amount_paid/payment_status
     * are kept in sync automatically via Payment::booted() -> deleted hook.
     */
    public function destroy($id): Response|JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::DELETE_PAYMENTS)) {
            abort(403);
        }

        $payment = Payment::findOrFail($id);
        $payment->deleted_by = auth()->user()->id;
        $payment->save();
        $payment->delete();

        return $this->response(true, 'success', ['id' => (int) $id], 200);
    }
}
