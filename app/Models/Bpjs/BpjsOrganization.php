<?php

namespace App\Models\Bpjs;

use App\Models\BaseModel;

class BpjsOrganization extends BaseModel
{
    protected $table = 'bpjs_organizations';

    protected $fillable = [
        'identifier',
        'name',
        'address',
    ];
}
