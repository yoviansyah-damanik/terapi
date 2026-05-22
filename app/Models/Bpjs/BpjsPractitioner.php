<?php

namespace App\Models\Bpjs;

use App\Models\BaseModel;

class BpjsPractitioner extends BaseModel
{
    protected $table = 'bpjs_practitioners';

    protected $fillable = [
        'identifier',
    ];
}
