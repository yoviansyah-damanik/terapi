<?php

namespace App\Models\Bpjs;

use App\Models\BaseModel;

class BpjsAllergy extends BaseModel
{
    protected $table = 'bpjs_allergies';

    protected $fillable = [
        'local_code',
        'name',
    ];
}
