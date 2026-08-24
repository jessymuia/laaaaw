<?php

namespace App\Models;

use App\AppUtils\DefaultAppModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends DefaultAppModel
{
    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'rate',
        'tax',
        'total_amount',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'quantity' => 'decimal:2',
        'rate' => 'decimal:2',
        'tax' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }
}
