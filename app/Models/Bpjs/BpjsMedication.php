<?php

namespace App\Models\Bpjs;

use App\Models\BaseModel;

class BpjsMedication extends BaseModel
{
    protected $table = 'bpjs_medications';

    protected $fillable = [
        'local_code',
        'name',
    ];
}
