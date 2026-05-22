<?php

namespace App\Models\Mapping;

use App\Models\BaseModel;

/** Mapping jenis pemeriksaan lab lokal ke SNOMED CT jenis spesimen */
class LabSpecimenMap extends BaseModel
{
    protected $table = 'map_lab_specimen';

    protected $fillable = [
        'local_code',
        'system_code',
        'system_term',
        'system_display',
    ];
}
