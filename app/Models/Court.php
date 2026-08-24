<?php

namespace App\Models;

use App\AppUtils\DefaultAppModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Court extends DefaultAppModel
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'updated_by',
        'deleted_by',
    ];

    public function courtType(): BelongsTo
    {
        return $this->belongsTo(CourtType::class, 'type')->withTrashed();
    }
}
