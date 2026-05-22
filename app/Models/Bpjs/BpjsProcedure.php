<?php

namespace App\Models\Bpjs;

use App\Models\BaseModel;

class BpjsProcedure extends BaseModel
{
    protected $table = 'bpjs_procedures';

    protected $fillable = [
        'type',
        'local_code',
        'name',
    ];
}
