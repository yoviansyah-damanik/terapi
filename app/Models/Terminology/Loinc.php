<?php

namespace App\Models\Terminology;

use Illuminate\Database\Eloquent\Model;

class Loinc extends Model
{
    protected $table = 'loinc';

    protected $primaryKey = 'loinc_num';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'loinc_num',
        'component',
        'property',
        'time_aspct',
        'system',
        'scale_typ',
        'method_typ',
        'class',
        'classtype',
        'long_common_name',
        'shortname',
        'external_copyright_notice',
        'status',
        'version_first_released',
        'version_last_changed',
    ];
}
