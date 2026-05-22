<?php

namespace App\Models\Bpjs;

use App\Models\BaseModel;

class BpjsHealthcareService extends BaseModel
{
    protected $table = 'bpjs_healthcare_services';

    protected $fillable = [
        'type',
        'local_code',
        'name',
    ];
}
