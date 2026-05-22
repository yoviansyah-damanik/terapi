<?php

namespace App\Models\Mapping;

use App\Models\BaseModel;
use App\Models\Terminology\Loinc;

/** Mapping tindakan lab lokal ke kode LOINC */
class LabMap extends BaseModel
{
    protected $table = 'map_lab';

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
