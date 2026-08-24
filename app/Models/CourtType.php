<?php

namespace App\Models;

use App\AppUtils\DefaultAppModel;

class CourtType extends DefaultAppModel
{
    protected $fillable = [
        'name',
        'updated_by',
        'deleted_by',
    ];
}
