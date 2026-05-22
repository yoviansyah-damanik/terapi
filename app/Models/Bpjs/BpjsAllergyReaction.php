<?php

namespace App\Models\Bpjs;

use App\Models\BaseModel;

class BpjsAllergyReaction extends BaseModel
{
    protected $table = 'bpjs_allergy_reactions';

    protected $fillable = [
        'local_code',
        'name',
    ];
}
