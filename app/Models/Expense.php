<?php

namespace App\Models;

use App\AppUtils\DefaultAppModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends DefaultAppModel
{
    protected $fillable = [
        'case_id',
        'date',
        'amount',
        'category',
        'description',
        'vendor',
        'payment_method',
        'invoice_number',
        'user_id',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public function categoryName(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(Cases::class, 'case_id')->withTrashed();
    }
}
