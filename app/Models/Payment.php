<?php

namespace App\Models;

use App\AppUtils\DefaultAppModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Payment extends DefaultAppModel
{
    protected $fillable = [
        'invoice_id',
        'receipt_number',
        'amount',
        'payment_date',
        'method',
        'reference_number',
        'received_by',
        'notes',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            if (empty($payment->receipt_number)) {
                $payment->receipt_number = self::nextReceiptNumber();
            }
        });

        // Keep the parent invoice's cached amount_paid / payment_status in
        // sync whenever a payment is recorded or removed.
        static::created(fn (Payment $payment) => $payment->invoice?->syncAmountPaid());
        static::deleted(fn (Payment $payment) => $payment->invoice?->syncAmountPaid());
    }

    public static function nextReceiptNumber(): string
    {
        $nextId = (DB::table('payments')->max('id') ?? 0) + 1;

        return 'RCT-'.str_pad($nextId, 6, '0', STR_PAD_LEFT);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by')->withTrashed();
    }
}
