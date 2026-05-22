<?php

namespace App\Models\Mapping;

use App\Models\BaseModel;

/** Mapping jenis pemeriksaan radiologi lokal ke SNOMED CT jenis spesimen/prosedur */
class RadProcedureMap extends BaseModel
{
    protected $table = 'map_rad_procedure';

    protected $fillable = [
        'local_code',
        'system_code',
        'system_term',
        'system_display',
    ];
}
