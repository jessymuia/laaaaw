<?php

namespace App\Models;

use App\AppUtils\DefaultAppModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEntry extends DefaultAppModel
{
    protected $fillable = [
        'case_id',
        'user_id',
        'description',
        'date',
        'hours',
        'hourly_rate',
        'billable',
        'billed',
        'invoice_item_id',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'hours' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'billable' => 'boolean',
        'billed' => 'boolean',
    ];

    public function getAmountAttribute(): float
    {
        return round((float) $this->hours * (float) $this->hourly_rate, 2);
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(Cases::class, 'case_id')->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class, 'invoice_item_id');
    }
}
