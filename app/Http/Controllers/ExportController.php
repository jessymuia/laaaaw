<?php

namespace App\Http\Controllers;

use App\Constants\ModulePermissions;
use App\Models\Cases;
use App\Models\Client;
use App\Models\Expense;
use App\Models\Hearing;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Task;
use App\Models\TimeEntry;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /**
     * FUN-4: server-side CSV export of cases. Reuses the same LIST_CASES
     * permission as the normal case list — export is a different output
     * format for data the user could already see, not a way to see more.
     */
    public function cases(): StreamedResponse|JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::LIST_CASES)) {
            abort(403);
        }

        $rows = Cases::with(['client', 'attorney', 'court'])->orderBy('id')->cursor()->map(fn ($row) => [
            $row->id,
            $row->case_number,
            $row->description,
            $row->client?->name ?? 'Unknown client',
            $row->attorney?->name ?? 'Unassigned',
            $row->court?->name ?? 'Unknown court',
            $row->case_type,
            $row->lifecycle_status,
            $row->start_date,
            $row->end_date,
        ]);

        return $this->streamCsv('cases-'.now()->format('Y-m-d').'.csv', [
            'ID', 'Case Number', 'Description', 'Client', 'Attorney', 'Court', 'Type', 'Status', 'Start Date', 'End Date',
        ], $rows);
    }

    public function expenses(): StreamedResponse|JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::LIST_EXPENSES)) {
            abort(403);
        }

        $rows = Expense::with(['case', 'categoryName', 'user'])->orderBy('id')->cursor()->map(fn ($row) => [
            $row->id,
            $row->case?->case_number ?? 'Unknown case',
            Carbon::parse($row->date)->format('Y-m-d'),
            $row->amount,
            $row->categoryName?->name ?? '',
            $row->description,
            $row->vendor,
            $row->payment_method,
            $row->user?->name ?? 'Unknown user',
        ]);

        return $this->streamCsv('expenses-'.now()->format('Y-m-d').'.csv', [
            'ID', 'Case', 'Date', 'Amount', 'Category', 'Description', 'Vendor', 'Payment Method', 'Recorded By',
        ], $rows);
    }

    /**
     * Invoices are creator/admin-scoped, same as InvoiceController::index
     * — the export respects the same visibility rules.
     */
    public function invoices(): StreamedResponse|JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::LIST_INVOICES)) {
            abort(403);
        }

        $query = Invoice::with(['case', 'client'])->orderBy('id');

        if (! auth()->user()->hasRole('admin')) {
            $query->where('created_by', auth()->user()->id);
        }

        $rows = $query->cursor()->map(fn ($row) => [
            $row->id,
            $row->invoice_number,
            $row->case?->case_number ?? 'Unknown case',
            $row->client?->name ?? 'Unknown client',
            $row->currency,
            $row->subtotal,
            $row->tax_total,
            $row->total_amount,
            $row->amount_paid,
            $row->workflow_status,
            $row->payment_status,
            $row->invoice_date,
            $row->invoice_due_date,
        ]);

        return $this->streamCsv('invoices-'.now()->format('Y-m-d').'.csv', [
            'ID', 'Invoice Number', 'Case', 'Client', 'Currency', 'Subtotal', 'Tax', 'Total', 'Paid', 'Workflow Status', 'Payment Status', 'Invoice Date', 'Due Date',
        ], $rows);
    }

    /**
     * PDF export of a single invoice — a server-rendered version of what
     * the client-side jsPDF preview currently produces, but generated from
     * trusted server data rather than whatever the browser has in memory.
     */
    public function invoicePdf($id)
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::LIST_INVOICES)) {
            abort(403);
        }

        $invoice = Invoice::with(['case', 'client', 'items'])->findOrFail($id);

        if (! auth()->user()->hasRole('admin') && $invoice->created_by !== auth()->user()->id) {
            abort(403);
        }

        $pdf = Pdf::loadView('exports.invoice', ['invoice' => $invoice]);

        return $pdf->download($invoice->invoice_number.'.pdf');
    }

    /**
     * Full-firm data export: one CSV per module, bundled into a single
     * zip. Deliberately admin-only (EXPORT_FIRM_DATA) — this is the one
     * export that ignores per-record scoping (e.g. every invoice
     * regardless of creator) because its purpose is a complete backup/
     * extraction of the firm's data, not a personal work view.
     */
    public function fullFirmExport(): StreamedResponse|JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::EXPORT_FIRM_DATA)) {
            abort(403);
        }

        $tmpDir = storage_path('app/tmp-exports/'.uniqid());
        mkdir($tmpDir, 0755, true);

        $exports = [
            'clients.csv' => [
                'headers' => ['ID', 'Name', 'Phone', 'Address', 'Advocate'],
                'rows' => Client::with('advocate')->orderBy('id')->cursor()->map(fn ($r) => [
                    $r->id, $r->name, $r->phone_number, $r->address, $r->advocate?->name ?? '',
                ]),
            ],
            'cases.csv' => [
                'headers' => ['ID', 'Case Number', 'Description', 'Client', 'Attorney', 'Status'],
                'rows' => Cases::with(['client', 'attorney'])->orderBy('id')->cursor()->map(fn ($r) => [
                    $r->id, $r->case_number, $r->description, $r->client?->name ?? '', $r->attorney?->name ?? '', $r->lifecycle_status,
                ]),
            ],
            'hearings.csv' => [
                'headers' => ['ID', 'Case', 'Court', 'Date', 'Outcome'],
                'rows' => Hearing::with(['case', 'court'])->orderBy('id')->cursor()->map(fn ($r) => [
                    $r->id, $r->case?->case_number ?? '', $r->court?->name ?? '', $r->hearing_date, $r->outcome,
                ]),
            ],
            'invoices.csv' => [
                'headers' => ['ID', 'Invoice Number', 'Case', 'Client', 'Total', 'Paid', 'Status'],
                'rows' => Invoice::with(['case', 'client'])->orderBy('id')->cursor()->map(fn ($r) => [
                    $r->id, $r->invoice_number, $r->case?->case_number ?? '', $r->client?->name ?? '', $r->total_amount, $r->amount_paid, $r->workflow_status,
                ]),
            ],
            'payments.csv' => [
                'headers' => ['ID', 'Receipt Number', 'Invoice', 'Amount', 'Date', 'Method'],
                'rows' => Payment::with('invoice')->orderBy('id')->cursor()->map(fn ($r) => [
                    $r->id, $r->receipt_number, $r->invoice?->invoice_number ?? '', $r->amount, $r->payment_date, $r->method,
                ]),
            ],
            'expenses.csv' => [
                'headers' => ['ID', 'Case', 'Amount', 'Category', 'Date'],
                'rows' => Expense::with(['case', 'categoryName'])->orderBy('id')->cursor()->map(fn ($r) => [
                    $r->id, $r->case?->case_number ?? '', $r->amount, $r->categoryName?->name ?? '', $r->date,
                ]),
            ],
            'time_entries.csv' => [
                'headers' => ['ID', 'Case', 'User', 'Hours', 'Rate', 'Amount', 'Billed'],
                'rows' => TimeEntry::with(['case', 'user'])->orderBy('id')->cursor()->map(fn ($r) => [
                    $r->id, $r->case?->case_number ?? '', $r->user?->name ?? '', $r->hours, $r->hourly_rate, $r->amount, $r->billed ? 'yes' : 'no',
                ]),
            ],
            'tasks.csv' => [
                'headers' => ['ID', 'Title', 'Assigned To', 'Due Date', 'Status'],
                'rows' => Task::with('assignee')->orderBy('id')->cursor()->map(fn ($r) => [
                    $r->id, $r->title, $r->assignee?->name ?? '', $r->due_date, $r->task_status,
                ]),
            ],
        ];

        foreach ($exports as $filename => $export) {
            $handle = fopen($tmpDir.'/'.$filename, 'w');
            fputcsv($handle, $export['headers']);
            foreach ($export['rows'] as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }

        $zipPath = storage_path('app/tmp-exports/firm-export-'.now()->format('Y-m-d-His').'.zip');
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE);
        foreach (array_keys($exports) as $filename) {
            $zip->addFile($tmpDir.'/'.$filename, $filename);
        }
        $zip->close();

        // Clean up the loose per-module CSVs now that they're zipped;
        // only the zip itself needs to survive to be streamed back.
        array_map('unlink', glob($tmpDir.'/*'));
        @rmdir($tmpDir);

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }
}
