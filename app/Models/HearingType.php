<?php

namespace App\Models;

use App\AppUtils\DefaultAppModel;

class HearingType extends DefaultAppModel
{
    protected $fillable = [
        'name',
        'updated_by',
        'deleted_by',
    ];
}
