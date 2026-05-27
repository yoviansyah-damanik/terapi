<?php

namespace App\Models\SatuSehat;

use App\Models\BaseModel;

class SatuSehatMedicationStatement extends BaseModel
{
    protected $table = 'satu_sehat_medication_statements';

    protected $fillable = [
        'ihs_number',
        'identifier',
        'local_id',
        'patient_ihs',
        'encounter_ihs',
        'medication_ihs',
        'medication_request_ihs',
        'performer_ihs',
        'status',
        'category',
        'is_vaccine',
        'effective_start',
        'effective_end',
        'dosage_route_code',
        'dosage_route_display',
        'dosage_dose_value',
        'dosage_dose_unit',
        'raw_response',
        'synced_at',
    ];

    protected $casts = [
        'is_vaccine' => 'boolean',
        'raw_response' => 'array',
        'synced_at' => 'datetime',
    ];
}
