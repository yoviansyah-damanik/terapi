<?php

namespace App\Models\Bpjs;

use App\Models\BaseModel;

class BpjsIcd10 extends BaseModel
{
    protected $table = 'bpjs_icd10';

    protected $fillable = [
        'code',
        'display',
    ];
}
