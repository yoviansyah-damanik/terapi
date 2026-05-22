<?php

namespace App\Models\Bpjs;

use App\Models\BaseModel;

class BpjsObservationRadiology extends BaseModel
{
    protected $table = 'bpjs_observation_radiologies';

    protected $fillable = [
        'local_code',
        'name',
    ];
}
