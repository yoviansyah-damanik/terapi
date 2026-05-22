<?php

namespace App\Models\Bpjs;

use App\Models\BaseModel;

class BpjsObservationLab extends BaseModel
{
    protected $table = 'bpjs_observation_labs';

    protected $fillable = [
        'local_code',
        'name',
    ];
}
