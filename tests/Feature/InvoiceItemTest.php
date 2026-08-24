<?php

namespace Tests\Feature;

use App\Models\Cases;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesTestUsers;
use Tests\TestCase;

/**
 * InvoiceItemController regression coverage.
 *
 * The controller's own comments document a real bug that shipped: line
 * item total_amount used to be accepted verbatim from the request and
 * summed straight into the invoice total. That (1) let the frontend's
 * tax-excluding computeTotal() silently drop VAT from every invoice, and
 * (2) let any user with invoice permissions submit a total unrelated to
 * quantity/rate/tax. The fix always derives total_amount server-side as
 * quantity * rate + tax. These tests exist so that fix can't regress
 * silently.
 */
class InvoiceItemTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    private function draftInvoice(): Invoice
    {
        $case = Cases::factory()->create();

        return Invoice::create([
            'case_id' => $case->id,
            'client_id' => $case->client_id,
            'invoice_date' => now()->format('Y-m-d'),
            'invoice_due_date' => now()->addDays(30)->format('Y-m-d'),
            'workflow_status' => 'draft',
        ]);
    }

    public function test_store_derives_total_amount_from_quantity_rate_and_tax(): void
    {
        $user = $this->userWithPermissions(['create-invoice']);
        $invoice = $this->draftInvoice();

        $response = $this->actingAs($user)->postJson('/api/invoiceItems', [
            'invoice_id' => $invoice->id,
            'description' => 'Filing fee',
            'quantity' => 3,
            'rate' => 33.33,
            'tax' => 0.01,
        ]);

        $response->assertStatus(200);

        $item = InvoiceItem::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertEquals('100.00', (string) $item->total_amount);
    }

    public function test_store_ignores_a_spoofed_total_amount_in_the_request(): void
    {
        // SEC/DATA regression: a client submitting an unrelated
        // total_amount must not influence the persisted line total —
        // it must always be recomputed from quantity/rate/tax.
        $user = $this->userWithPermissions(['create-invoice']);
        $invoice = $this->draftInvoice();

        $this->actingAs($user)->postJson('/api/invoiceItems', [
            'invoice_id' => $invoice->id,
            'description' => 'Filing fee',
            'quantity' => 2,
            'rate' => 10,
            'tax' => 0,
            'total_amount' => 999999,
        ])->assertStatus(200);

        $item = InvoiceItem::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertEquals('20.00', (string) $item->total_amount);
    }

    public function test_store_refreshes_the_parent_invoice_totals_including_tax(): void
    {
        // DATA-4/regression: subtotal must be rate*quantity only, and
        // tax_total/total_amount must actually include tax — this is
        // exactly the bug the fix comment describes (VAT silently
        // excluded from the grand total).
        $user = $this->userWithPermissions(['create-invoice']);
        $invoice = $this->draftInvoice();

        $this->actingAs($user)->postJson('/api/invoiceItems', [
            'invoice_id' => $invoice->id,
            'description' => 'Consultation',
            'quantity' => 2,
            'rate' => 50,
            'tax' => 8,
        ])->assertStatus(200);

        $invoice->refresh();
        $this->assertEquals('100.00', (string) $invoice->subtotal);
        $this->assertEquals('8.00', (string) $invoice->tax_total);
        $this->assertEquals('108.00', (string) $invoice->total_amount);
    }

    public function test_update_also_ignores_a_spoofed_total_amount(): void
    {
        $user = $this->userWithPermissions(['create-invoice', 'update-invoice']);
        $invoice = $this->draftInvoice();

        $item = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Original',
            'quantity' => 1,
            'rate' => 10,
            'tax' => 0,
            'total_amount' => 10,
        ]);
        $invoice->refreshTotals();

        $this->actingAs($user)->putJson("/api/invoiceItems/{$item->id}", [
            'invoice_id' => $invoice->id,
            'description' => 'Updated',
            'quantity' => 4,
            'rate' => 25,
            'tax' => 5,
            'total_amount' => 1,
        ])->assertStatus(200);

        $this->assertEquals('105.00', (string) $item->fresh()->total_amount);
        $this->assertEquals('105.00', (string) $invoice->fresh()->total_amount);
    }

    public function test_destroy_refreshes_the_parent_invoice_totals(): void
    {
        $user = $this->userWithPermissions(['create-invoice', 'delete-invoice']);
        $invoice = $this->draftInvoice();

        $item = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Line item',
            'quantity' => 1,
            'rate' => 50,
            'tax' => 0,
            'total_amount' => 50,
        ]);
        $invoice->refreshTotals();
        $this->assertEquals('50.00', (string) $invoice->fresh()->total_amount);

        $this->actingAs($user)->deleteJson("/api/invoiceItems/{$item->id}")->assertStatus(200);

        $this->assertSoftDeleted('invoice_items', ['id' => $item->id]);
        $this->assertEquals($user->id, $item->fresh()->deleted_by);
        $this->assertEquals('0.00', (string) $invoice->fresh()->total_amount);
    }

    public function test_store_requires_create_invoices_permission(): void
    {
        $user = $this->userWithPermissions([]);
        $invoice = $this->draftInvoice();

        $this->actingAs($user)->postJson('/api/invoiceItems', [
            'invoice_id' => $invoice->id,
            'description' => 'Filing fee',
            'quantity' => 1,
            'rate' => 10,
            'tax' => 0,
        ])->assertStatus(403);
    }

    public function test_update_requires_update_invoices_permission(): void
    {
        $creator = $this->userWithPermissions(['create-invoice']);
        $invoice = $this->draftInvoice();
        $item = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Line item',
            'quantity' => 1,
            'rate' => 10,
            'tax' => 0,
            'total_amount' => 10,
        ]);

        $unprivileged = $this->userWithPermissions([]);

        $this->actingAs($unprivileged)->putJson("/api/invoiceItems/{$item->id}", [
            'invoice_id' => $invoice->id,
            'description' => 'Line item',
            'quantity' => 1,
            'rate' => 10,
            'tax' => 0,
        ])->assertStatus(403);
    }

    public function test_destroy_requires_delete_invoices_permission(): void
    {
        $invoice = $this->draftInvoice();
        $item = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Line item',
            'quantity' => 1,
            'rate' => 10,
            'tax' => 0,
            'total_amount' => 10,
        ]);

        $unprivileged = $this->userWithPermissions([]);

        $this->actingAs($unprivileged)->deleteJson("/api/invoiceItems/{$item->id}")->assertStatus(403);
    }
}
