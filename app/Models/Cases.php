<?php

namespace App\Models;

use App\AppUtils\DefaultAppModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cases extends DefaultAppModel
{
    protected $fillable = [
        'case_number',
        'description',
        'client_id',
        'assigned_to',
        'start_date',
        'end_date',
        'case_type',
        'police_station',
        'court_id',
        'opposing_party',
        'lifecycle_status',
        'updated_by',
        'deleted_by',
    ];

    // Valid forward transitions. 'open' is the only entry state; from there
    // a case can move to any of the three end states, and 'appeal' can
    // return to 'open' (case reopened on appeal) or move on to 'settled'.
    public const TRANSITIONS = [
        'open' => ['closed', 'appeal', 'settled'],
        'appeal' => ['open', 'settled', 'closed'],
        'closed' => ['open'],   // reopening a closed case
        'settled' => [],         // terminal
    ];

    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, self::TRANSITIONS[$this->lifecycle_status] ?? [], true);
    }

    // withTrashed() so cases that reference a since-deleted client/attorney/
    // court still resolve a name instead of crashing (see DATA-6).
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id')->withTrashed();
    }

    public function attorney(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class, 'court_id')->withTrashed();
    }
}
