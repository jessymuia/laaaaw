<?php

namespace App\Http\Controllers;

use App\Constants\ModulePermissions;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\TimeEntry;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TimeEntryController extends Controller
{
    public function index(): Response|JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::LIST_TIME_ENTRIES)) {
            abort(403);
        }

        $query = TimeEntry::with(['case', 'user'])->orderBy('date', 'desc');

        if (request()->has('case_id')) {
            $query->where('case_id', request()->input('case_id'));
        }

        $entries = $this->paginatedOrFull(request(), $query, [$this, 'formatRow']);

        return $this->response(true, 'success', $entries, 200);
    }

    /**
     * ENG-4: shared row formatter, see CasesController::formatRow for why.
     */
    public function formatRow(TimeEntry $row): array
    {
        return [
            'id' => $row->id,
            'case_id' => $row->case_id,
            'case' => $row->case?->case_number ?? 'Unknown case',
            'user_id' => $row->user_id,
            'user' => $row->user?->name ?? 'Unknown user',
            'description' => $row->description,
            'date' => Carbon::parse($row->date)->format('d/m/Y'),
            'hours' => $row->hours,
            'hourly_rate' => $row->hourly_rate,
            'amount' => $row->amount,
            'billable' => $row->billable,
            'billed' => $row->billed,
        ];
    }

    public function store(Request $request): Response|JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::CREATE_TIME_ENTRIES)) {
            abort(403);
        }

        $request->validate([
            'case_id' => 'required|integer|exists:cases,id',
            'description' => 'required|string|max:255',
            'date' => 'required|date_format:d/m/Y',
            'hours' => 'required|numeric|min:0.01|max:24',
            'hourly_rate' => 'required|numeric|min:0',
            'billable' => 'boolean',
        ]);

        $entry = TimeEntry::create([
            'case_id' => $request->case_id,
            'user_id' => auth()->user()->id,
            'description' => $request->description,
            'date' => Carbon::createFromFormat('d/m/Y', $request->date)->format('Y-m-d'),
            'hours' => $request->hours,
            'hourly_rate' => $request->hourly_rate,
            'billable' => $request->boolean('billable', true),
        ]);

        return $this->response(true, 'success', $this->formatRow($entry->load(['case', 'user'])), 201);
    }

    public function update(Request $request, $id): Response|JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::UPDATE_TIME_ENTRIES)) {
            abort(403);
        }

        $entry = TimeEntry::findOrFail($id);

        if ($entry->billed) {
            return $this->response(false, 'This time entry has already been billed and cannot be edited', null, 422);
        }

        $request->validate([
            'case_id' => 'required|integer|exists:cases,id',
            'description' => 'required|string|max:255',
            'date' => 'required|date_format:d/m/Y',
            'hours' => 'required|numeric|min:0.01|max:24',
            'hourly_rate' => 'required|numeric|min:0',
            'billable' => 'boolean',
        ]);

        $entry->update([
            'case_id' => $request->case_id,
            'description' => $request->description,
            'date' => Carbon::createFromFormat('d/m/Y', $request->date)->format('Y-m-d'),
            'hours' => $request->hours,
            'hourly_rate' => $request->hourly_rate,
            'billable' => $request->boolean('billable', true),
            'updated_by' => auth()->user()->id,
        ]);

        return $this->response(true, 'success', $this->formatRow($entry->fresh(['case', 'user'])), 200);
    }

    public function destroy($id): Response|JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::DELETE_TIME_ENTRIES)) {
            abort(403);
        }

        $entry = TimeEntry::findOrFail($id);

        if ($entry->billed) {
            return $this->response(false, 'This time entry has already been billed and cannot be deleted', null, 422);
        }

        $entry->deleted_by = auth()->user()->id;
        $entry->save();
        $entry->delete();

        return $this->response(true, 'success', ['id' => (int) $id], 200);
    }

    /**
     * Generate a draft invoice from a case's outstanding unbilled,
     * billable time entries. Each entry becomes one invoice line item and
     * is marked billed + linked to that line item, so it can never be
     * billed twice.
     */
    public function generateInvoice(Request $request): Response|JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::CREATE_INVOICES)) {
            abort(403);
        }

        $request->validate([
            'case_id' => 'required|integer|exists:cases,id',
            'client_id' => 'required|integer|exists:clients,id',
        ]);

        $entries = TimeEntry::where('case_id', $request->case_id)
            ->where('billable', true)
            ->where('billed', false)
            ->get();

        if ($entries->isEmpty()) {
            return $this->response(false, 'No unbilled billable time entries for this case', null, 422);
        }

        $invoice = DB::transaction(function () use ($entries, $request) {
            $invoice = Invoice::create([
                'case_id' => $request->case_id,
                'client_id' => $request->client_id,
                'invoice_date' => now()->format('Y-m-d'),
                'invoice_due_date' => now()->addDays(30)->format('Y-m-d'),
                'workflow_status' => 'draft',
            ]);

            foreach ($entries as $entry) {
                $item = InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => $entry->description.' ('.$entry->hours.' hrs)',
                    'quantity' => $entry->hours,
                    'rate' => $entry->hourly_rate,
                    'tax' => 0,
                    'total_amount' => $entry->amount,
                ]);

                $entry->update(['billed' => true, 'invoice_item_id' => $item->id]);
            }

            $invoice->refreshTotals();

            return $invoice;
        });

        return $this->response(true, 'Draft invoice generated', ['invoice_id' => $invoice->id], 200);
    }
}
