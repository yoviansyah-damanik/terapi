<?php

namespace App\Models\Bpjs;

use App\Models\BaseModel;

class BpjsPatient extends BaseModel
{
    protected $table = 'bpjs_patients';

    protected $fillable = [
        'nik',
    ];
}
