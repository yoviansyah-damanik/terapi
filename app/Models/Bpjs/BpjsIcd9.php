<?php

namespace App\Models\Bpjs;

use App\Models\BaseModel;

class BpjsIcd9 extends BaseModel
{
    protected $table = 'bpjs_icd9';

    protected $fillable = [
        'code',
        'display',
    ];
}
