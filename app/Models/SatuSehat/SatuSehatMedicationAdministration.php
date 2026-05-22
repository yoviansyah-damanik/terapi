<?php

namespace App\Models\SatuSehat;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SatuSehatMedicationAdministration extends BaseModel
{
    protected $table = 'satu_sehat_medication_administrations';

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
        'effective_start',
        'effective_end',
        'dosage_route_code',
        'dosage_route_display',
        'dosage_dose_value',
        'dosage_dose_unit',
        'raw_response',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_start'   => 'datetime',
            'effective_end'     => 'datetime',
            'dosage_dose_value' => 'decimal:2',
            'raw_response'      => 'array',
            'synced_at'         => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(SatuSehatPatient::class, 'patient_ihs', 'ihs_number');
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(SatuSehatEncounter::class, 'encounter_ihs', 'ihs_number');
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(SatuSehatMedication::class, 'medication_ihs', 'ihs_number');
    }

    public function medicationRequest(): BelongsTo
    {
        return $this->belongsTo(SatuSehatMedicationRequest::class, 'medication_request_ihs', 'ihs_number');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(SatuSehatPractitioner::class, 'performer_ihs', 'ihs_number');
    }
}
