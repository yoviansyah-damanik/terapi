<?php

namespace App\Models\Mapping;

use App\Models\BaseModel;

/** Mapping obat lokal → KFA Farmasi (map_medication). */
class MedicationMap extends BaseModel
{
    protected $table = 'map_medication';

    protected $fillable = [
        'local_code',
        'kfa_code',
        'kfa_name',
        'system_url',
        'form_code',
        'form_name',
        'form_display',
        'route_code',
        'route_name',
        'route_display',
        'numerator_code',
        'numerator_name',
        'numerator_display',
        'denominator_code',
        'denominator_name',
        'denominator_display',
        'controlled_drug_code',
        'controlled_drug_name',
        'controlled_drug_display',
        'medication_type_code',
        'medication_type_name',
        'medication_type_display',
        'immunization_reason_code',
        'immunization_reason_name',
        'immunization_routine_timing_code',
        'immunization_routine_timing_name',
        'kfa_payload',
    ];

    protected $casts = [
        'kfa_payload' => 'array',
    ];
}
