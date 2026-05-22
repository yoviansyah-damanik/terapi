<?php

namespace App\Models\Bpjs;

use App\Models\BaseModel;

class BpjsDevice extends BaseModel
{
    protected $table = 'bpjs_devices';

    protected $fillable = [
        'local_code',
        'name',
    ];
}
