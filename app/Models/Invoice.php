<?php

namespace App\Models;

use App\AppUtils\DefaultAppModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Invoice extends DefaultAppModel
{
    protected $fillable = [
        'invoice_number',
        'case_id',
        'invoice_date',
        'invoice_due_date',
        'client_id',
        'currency',
        'workflow_status',
        'payment_status',
        'subtotal',
        'tax_total',
        'total_amount',
        'amount_paid',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice) {
            if (empty($invoice->invoice_number)) {
                $invoice->invoice_number = self::nextInvoiceNumber();
            }
        });
    }

    public static function nextInvoiceNumber(): string
    {
        // Sequential, human-readable numbers (INV-000123). Uses the next
        // auto-increment id as the sequence source so numbers stay unique
        // and gap-free under normal operation.
        $nextId = (DB::table('invoices')->max('id') ?? 0) + 1;

        return 'INV-'.str_pad($nextId, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Recalculate and persist the cached totals from this invoice's items.
     * Call after any invoice_items create/update/delete.
     */
    public function refreshTotals(): void
    {
        $totals = InvoiceItem::where('invoice_id', $this->id)
            ->selectRaw('COALESCE(SUM(rate * quantity), 0) as subtotal, COALESCE(SUM(tax), 0) as tax_total, COALESCE(SUM(total_amount), 0) as total_amount')
            ->first();

        $this->update([
            'subtotal' => $totals->subtotal,
            'tax_total' => $totals->tax_total,
            'total_amount' => $totals->total_amount,
        ]);

        $this->refreshPaymentStatus();
    }

    public function refreshPaymentStatus(): void
    {
        $paymentStatus = 'unpaid';

        if ($this->amount_paid > 0 && $this->amount_paid < $this->total_amount) {
            $paymentStatus = 'partially_paid';
        } elseif ($this->total_amount > 0 && $this->amount_paid >= $this->total_amount) {
            $paymentStatus = 'paid';
        }

        if ($paymentStatus !== $this->payment_status) {
            $this->update(['payment_status' => $paymentStatus]);
        }
    }

    /**
     * Recompute amount_paid from this invoice's non-voided payments, then
     * refresh the derived payment_status. Called whenever a Payment is
     * created or removed (see Payment::booted()).
     */
    public function syncAmountPaid(): void
    {
        $total = $this->payments()->sum('amount');

        if ((float) $total !== (float) $this->amount_paid) {
            $this->update(['amount_paid' => $total]);
        }

        $this->refreshPaymentStatus();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'invoice_id');
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(Cases::class, 'case_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id');
    }
}
