<?php

namespace App\Models\SatuSehat;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SatuSehatMedicationDispense extends BaseModel
{
    protected $table = 'satu_sehat_medication_dispenses';

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
        'quantity_value',
        'quantity_unit',
        'when_prepared',
        'when_handed_over',
        'dosage_instruction',
        'raw_response',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity_value' => 'decimal:2',
            'when_prepared' => 'datetime',
            'when_handed_over' => 'datetime',
            'dosage_instruction' => 'array',
            'raw_response' => 'array',
            'synced_at' => 'datetime',
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

    public static function findByIhs(string $ihsNumber): ?self
    {
        return static::where('ihs_number', $ihsNumber)->first();
    }
}
