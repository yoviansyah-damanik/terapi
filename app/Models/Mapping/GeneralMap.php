<?php

namespace App\Models\Mapping;

use App\Models\BaseModel;

class GeneralMap extends BaseModel
{
    protected $table = 'map_general';

    protected $fillable = [
        'category',
        'local_code',
        'local_term',
        'system_code',
        'system_term',
        'system_display',
    ];
}
