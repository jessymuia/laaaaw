<?php

namespace App\Models;

use App\AppUtils\DefaultAppModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Client extends DefaultAppModel
{
    protected $fillable = [
        'name',
        'phone_number',
        'extra_phone_number',
        'address',
        'advocate_id',
        'updated_by',
        'deleted_by',
    ];

    public function advocate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advocate_id')->withTrashed();
    }
}
