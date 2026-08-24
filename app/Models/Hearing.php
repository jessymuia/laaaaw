<?php

namespace App\Models;

use App\AppUtils\DefaultAppModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Hearing extends DefaultAppModel
{
    protected $fillable = [
        'case_id',
        'court_id',
        'hearing_date',
        'hearing_type',
        'notes',
        'outcome',
        'reminder_sent_at',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
    ];

    public function hearingTypeName(): BelongsTo
    {
        return $this->belongsTo(HearingType::class, 'hearing_type');
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(Cases::class, 'case_id')->withTrashed();
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class, 'court_id')->withTrashed();
    }
}
