<?php

namespace App\Models\Bpjs;

use App\Models\BaseModel;

class BpjsVaccine extends BaseModel
{
    protected $table = 'bpjs_vaccines';

    protected $fillable = [
        'local_code',
        'name',
    ];
}
