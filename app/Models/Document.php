<?php

namespace App\Models;

use App\AppUtils\DefaultAppModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends DefaultAppModel
{
    protected $fillable = [
        'case_id',
        'document_group_id',
        'version',
        'is_current',
        'title',
        'filename',
        'filepath',
        'full_path',
        'disk',
        'mimetype',
        'filesize',
        'extension',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'is_current' => 'boolean',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(Cases::class, 'case_id')->withTrashed();
    }

    /**
     * All versions of this document, oldest first.
     */
    public function versions(): HasMany
    {
        return $this->hasMany(Document::class, 'document_group_id', 'document_group_id')
            ->orderBy('version');
    }
}
