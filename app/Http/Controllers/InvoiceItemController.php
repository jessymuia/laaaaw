<?php

namespace App\Http\Controllers;

use App\Constants\ModulePermissions;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class InvoiceItemController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        //
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
        if (! Auth::user()->checkPermissionTo(ModulePermissions::CREATE_INVOICES)) {
            abort(403);
        }
        $request->validate([
            'invoice_id' => 'required|integer|exists:invoices,id',
            'description' => 'required|string|max:255',
            'quantity' => 'required|numeric|min:0',
            'rate' => 'required|numeric|min:0',
            'tax' => 'required|numeric|min:0',
        ]);

        // Bug fix: total_amount was previously accepted verbatim from the
        // request and summed directly into Invoice.total_amount by
        // refreshTotals(). Two real problems followed from that: (1) the
        // frontend's own computeTotal() sets total_amount to quantity*rate
        // *excluding* tax, so every invoice's grand total silently
        // excluded VAT entirely; and (2) trusting a client-submitted total
        // at all is a financial-integrity issue — any user with invoice
        // permissions could submit a total_amount unrelated to
        // quantity/rate/tax. The line total is now always derived
        // authoritatively on the server: quantity * rate, plus tax.
        $totalAmount = round(($request->quantity * $request->rate) + $request->tax, 2);

        InvoiceItem::create([
            'invoice_id' => $request->invoice_id,
            'description' => $request->description,
            'quantity' => $request->quantity,
            'rate' => $request->rate,
            'tax' => $request->tax,
            'total_amount' => $totalAmount,
        ]);

        Invoice::find($request->invoice_id)?->refreshTotals();

        $items = InvoiceItem::where('invoice_id', $request->invoice_id)->orderBy('id', 'desc')->get();

        return $this->response(true, 'success', $items, 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  InvoiceItem  $invoiceItem
     */
    public function show($id): JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::LIST_INVOICES)) {
            abort(403);
        }

        $items = InvoiceItem::where('invoice_id', $id)->get();

        return $this->response(true, 'success', $items, 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function edit(InvoiceItem $invoiceItem)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  InvoiceItem  $invoiceItem
     * @return Response
     */
    public function update(Request $request, $id): Response|JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::UPDATE_INVOICES)) {
            abort(403);
        }

        $request->validate([
            'description' => 'required|string|max:255',
            'quantity' => 'required|numeric|min:0',
            'rate' => 'required|numeric|min:0',
            'tax' => 'required|numeric|min:0',
            'invoice_id' => 'required|integer|exists:invoices,id',
        ]);

        // See the matching fix in store() — total_amount is always
        // server-derived, never trusted from the request.
        $totalAmount = round(($request->quantity * $request->rate) + $request->tax, 2);

        $item = InvoiceItem::findOrFail($id);
        $item->update([
            'description' => $request->description,
            'quantity' => $request->quantity,
            'rate' => $request->rate,
            'tax' => $request->tax,
            'total_amount' => $totalAmount,
            'updated_by' => auth()->user()->id,
        ]);

        Invoice::find($request->invoice_id)?->refreshTotals();

        $items = InvoiceItem::where('invoice_id', $request->invoice_id)->orderBy('id', 'desc')->get();

        return $this->response(true, 'success', $items, 200);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  InvoiceItem  $invoiceItem
     * @return Response
     */
    public function destroy($id): Response|JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::DELETE_INVOICES)) {
            abort(403);
        }

        $item = InvoiceItem::findOrFail($id);
        $invoiceId = $item->invoice_id;
        $item->deleted_by = auth()->user()->id;
        $item->save();
        $item->delete();

        Invoice::find($invoiceId)?->refreshTotals();

        $items = InvoiceItem::where('invoice_id', $invoiceId)->orderBy('id', 'desc')->get();

        return $this->response(true, 'success', $items, 200);
    }
}
