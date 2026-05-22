<?php

namespace App\Models\Mapping;

use App\Models\BaseModel;
use App\Models\Terminology\Loinc;

/** Mapping tindakan radiologi lokal ke kode LOINC */
class RadMap extends BaseModel
{
    protected $table = 'map_rad';

    protected $fillable = [
        'local_code',
        'system_code',
        'system_term',
        'system_display',
    ];

    public function loincDetail()
    {
        return $this->belongsTo(Loinc::class, 'system_code', 'loinc_num');
    }
}
