<?php

namespace Tests\Feature;

use App\Models\Cases;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesTestUsers;
use Tests\TestCase;

/**
 * ExportController regression coverage.
 *
 * fullFirmExport is the one export in the app that deliberately ignores
 * per-record scoping — it is meant to be a complete, admin-only
 * extraction of every client, case, invoice, and payment in the firm.
 * A permission-check regression here means silent firm-wide data
 * exfiltration rather than one bad record, so its access gate is the
 * highest-priority thing to protect. The scoped per-module exports
 * (cases/expenses/invoices) reuse the same LIST_* permissions and
 * ownership scoping as their controllers, so those are covered too.
 */
class ExportControllerTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    public function test_full_firm_export_requires_export_firm_data_permission(): void
    {
        // A user with plenty of ordinary list permissions, but not the
        // dedicated export-firm-data permission, must still be refused.
        $user = $this->userWithPermissions(['list-cases', 'list-invoice', 'list-expenses']);

        $this->actingAs($user)->getJson('/api/export/firm-data')->assertStatus(403);
    }

    public function test_full_firm_export_succeeds_for_a_user_with_permission(): void
    {
        $user = $this->userWithPermissions(['export-firm-data']);
        Cases::factory()->create();

        $response = $this->actingAs($user)->get('/api/export/firm-data');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/zip');
    }

    public function test_cases_export_requires_list_cases_permission(): void
    {
        $user = $this->userWithPermissions([]);

        $this->actingAs($user)->getJson('/api/export/cases')->assertStatus(403);
    }

    public function test_cases_export_succeeds_for_a_user_with_permission(): void
    {
        $user = $this->userWithPermissions(['list-cases']);
        Cases::factory()->create();

        $response = $this->actingAs($user)->get('/api/export/cases');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('Case Number', $content);
    }

    public function test_invoices_export_is_scoped_to_the_creators_own_invoices_for_non_admins(): void
    {
        // Mirrors InvoiceController::index's own-invoices-only rule for
        // non-admins — the export must not let a non-admin see invoices
        // they didn't create just because it's a different output format.
        $owner = $this->userWithPermissions(['list-invoice']);
        $otherUser = $this->userWithPermissions(['list-invoice']);

        $case = Cases::factory()->create();

        $this->actingAs($owner);
        Invoice::create([
            'case_id' => $case->id,
            'client_id' => $case->client_id,
            'invoice_date' => now()->format('Y-m-d'),
            'invoice_due_date' => now()->addDays(30)->format('Y-m-d'),
            'workflow_status' => 'draft',
        ]);

        $response = $this->actingAs($otherUser)->get('/api/export/invoices');

        $response->assertStatus(200);
        $content = $response->streamedContent();

        // Only the header row should be present — otherUser owns none of
        // the invoices that exist.
        $this->assertEquals(1, substr_count($content, "\n"));
    }

    public function test_invoices_export_includes_all_invoices_for_an_admin(): void
    {
        $admin = $this->adminUser();
        $creator = $this->userWithPermissions(['list-invoice']);

        $case = Cases::factory()->create();

        $this->actingAs($creator);
        Invoice::create([
            'case_id' => $case->id,
            'client_id' => $case->client_id,
            'invoice_date' => now()->format('Y-m-d'),
            'invoice_due_date' => now()->addDays(30)->format('Y-m-d'),
            'workflow_status' => 'draft',
        ]);

        $response = $this->actingAs($admin)->get('/api/export/invoices');

        $response->assertStatus(200);
        $content = $response->streamedContent();

        // Header row plus the one invoice created by a different user.
        $this->assertEquals(2, substr_count($content, "\n"));
    }
}
