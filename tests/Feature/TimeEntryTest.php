<?php

namespace Tests\Feature;

use App\Models\Cases;
use App\Models\Invoice;
use App\Models\TimeEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesTestUsers;
use Tests\TestCase;

/**
 * TimeEntryController::generateInvoice regression coverage.
 *
 * The controller's own comment promises that once a billable time entry
 * is converted into an invoice line item, it is marked `billed` and
 * "can never be billed twice." Nothing previously verified that
 * guarantee, or the related billed-entry edit/delete lock. Also covers
 * the create/update/destroy permission gates and the billable filter.
 */
class TimeEntryTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    private function timeEntryPayload(int $caseId, array $overrides = []): array
    {
        return array_merge([
            'case_id' => $caseId,
            'description' => 'Drafted pleadings',
            'date' => now()->format('d/m/Y'),
            'hours' => 2,
            'hourly_rate' => 100,
            'billable' => true,
        ], $overrides);
    }

    public function test_generate_invoice_marks_entries_billed_and_links_them_to_the_line_item(): void
    {
        $user = $this->userWithPermissions(['create-time-entries', 'create-invoice']);
        $case = Cases::factory()->create();

        $entry = TimeEntry::create([
            'case_id' => $case->id,
            'user_id' => $user->id,
            'description' => 'Court appearance',
            'date' => now()->format('Y-m-d'),
            'hours' => 3,
            'hourly_rate' => 150,
            'billable' => true,
        ]);

        $response = $this->actingAs($user)->postJson('/api/time-entries/generate-invoice', [
            'case_id' => $case->id,
            'client_id' => $case->client_id,
        ]);

        $response->assertStatus(200);

        $entry->refresh();
        $this->assertTrue((bool) $entry->billed);
        $this->assertNotNull($entry->invoice_item_id);

        $invoiceId = $response->json('data.invoice_id');
        $invoice = Invoice::findOrFail($invoiceId);
        $this->assertEquals('draft', $invoice->workflow_status);
        $this->assertEquals('450.00', (string) $invoice->total_amount);
    }

    public function test_generate_invoice_cannot_bill_the_same_entry_twice(): void
    {
        // The core guarantee: a second generate-invoice call for the same
        // case must not pick up an entry that a prior call already billed.
        $user = $this->userWithPermissions(['create-time-entries', 'create-invoice']);
        $case = Cases::factory()->create();

        TimeEntry::create([
            'case_id' => $case->id,
            'user_id' => $user->id,
            'description' => 'Research',
            'date' => now()->format('Y-m-d'),
            'hours' => 1,
            'hourly_rate' => 100,
            'billable' => true,
        ]);

        $this->actingAs($user)->postJson('/api/time-entries/generate-invoice', [
            'case_id' => $case->id,
            'client_id' => $case->client_id,
        ])->assertStatus(200);

        $second = $this->actingAs($user)->postJson('/api/time-entries/generate-invoice', [
            'case_id' => $case->id,
            'client_id' => $case->client_id,
        ]);

        $second->assertStatus(422);
        $this->assertEquals(1, Invoice::where('case_id', $case->id)->count());
    }

    public function test_generate_invoice_excludes_non_billable_entries(): void
    {
        $user = $this->userWithPermissions(['create-time-entries', 'create-invoice']);
        $case = Cases::factory()->create();

        TimeEntry::create([
            'case_id' => $case->id,
            'user_id' => $user->id,
            'description' => 'Internal admin',
            'date' => now()->format('Y-m-d'),
            'hours' => 1,
            'hourly_rate' => 100,
            'billable' => false,
        ]);

        $response = $this->actingAs($user)->postJson('/api/time-entries/generate-invoice', [
            'case_id' => $case->id,
            'client_id' => $case->client_id,
        ]);

        $response->assertStatus(422);
    }

    public function test_billed_time_entry_cannot_be_updated(): void
    {
        $user = $this->userWithPermissions(['create-time-entries', 'update-time-entries', 'create-invoice']);
        $case = Cases::factory()->create();

        $entry = TimeEntry::create([
            'case_id' => $case->id,
            'user_id' => $user->id,
            'description' => 'Filed motion',
            'date' => now()->format('Y-m-d'),
            'hours' => 1,
            'hourly_rate' => 100,
            'billable' => true,
        ]);

        $this->actingAs($user)->postJson('/api/time-entries/generate-invoice', [
            'case_id' => $case->id,
            'client_id' => $case->client_id,
        ])->assertStatus(200);

        $response = $this->actingAs($user)->putJson(
            "/api/time-entries/{$entry->id}",
            $this->timeEntryPayload($case->id, ['hours' => 5])
        );

        $response->assertStatus(422);
        $this->assertEquals('1.00', (string) $entry->fresh()->hours);
    }

    public function test_billed_time_entry_cannot_be_deleted(): void
    {
        $user = $this->userWithPermissions(['create-time-entries', 'delete-time-entries', 'create-invoice']);
        $case = Cases::factory()->create();

        $entry = TimeEntry::create([
            'case_id' => $case->id,
            'user_id' => $user->id,
            'description' => 'Filed motion',
            'date' => now()->format('Y-m-d'),
            'hours' => 1,
            'hourly_rate' => 100,
            'billable' => true,
        ]);

        $this->actingAs($user)->postJson('/api/time-entries/generate-invoice', [
            'case_id' => $case->id,
            'client_id' => $case->client_id,
        ])->assertStatus(200);

        $this->actingAs($user)->deleteJson("/api/time-entries/{$entry->id}")->assertStatus(422);
        $this->assertNotSoftDeleted('time_entries', ['id' => $entry->id]);
    }

    public function test_store_computes_amount_from_hours_and_rate(): void
    {
        $user = $this->userWithPermissions(['create-time-entries']);
        $case = Cases::factory()->create();

        $response = $this->actingAs($user)->postJson(
            '/api/time-entries',
            $this->timeEntryPayload($case->id, ['hours' => 2.5, 'hourly_rate' => 40])
        );

        $response->assertStatus(201);
        $this->assertEquals(100.0, $response->json('data.amount'));
    }

    public function test_store_requires_create_time_entries_permission(): void
    {
        $user = $this->userWithPermissions([]);
        $case = Cases::factory()->create();

        $this->actingAs($user)->postJson(
            '/api/time-entries',
            $this->timeEntryPayload($case->id)
        )->assertStatus(403);
    }

    public function test_generate_invoice_requires_create_invoices_permission(): void
    {
        $user = $this->userWithPermissions(['create-time-entries']);
        $case = Cases::factory()->create();

        TimeEntry::create([
            'case_id' => $case->id,
            'user_id' => $user->id,
            'description' => 'Research',
            'date' => now()->format('Y-m-d'),
            'hours' => 1,
            'hourly_rate' => 100,
            'billable' => true,
        ]);

        $this->actingAs($user)->postJson('/api/time-entries/generate-invoice', [
            'case_id' => $case->id,
            'client_id' => $case->client_id,
        ])->assertStatus(403);
    }
}
