<?php

namespace App\Models\SatuSehat;

use App\Models\BaseModel;

class SatusehatRuleNumber extends BaseModel
{
    protected $table = 'satusehat_rule_numbers';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'rule_no',
        'path',
        'terminology_used',
        'error_description',
        'rule_last_update',
        'version',
    ];
}
