<?php

namespace App\Models;

use App\AppUtils\DefaultAppModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends DefaultAppModel
{
    protected $fillable = [
        'description',
        'title',
        'assigned_to',
        'due_date',
        'priority',
        'task_status',
        'updated_by',
        'deleted_by',
    ];

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }
}
