<?php

namespace App\Models\SatuSehat;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SatuSehatMedicationRequest extends BaseModel
{
    protected $table = 'satu_sehat_medication_requests';

    protected $fillable = [
        'ihs_number',
        'identifier',
        'local_id',
        'patient_ihs',
        'encounter_ihs',
        'medication_ihs',
        'requester_ihs',
        'status',
        'intent',
        'authored_on',
        'dosage_instruction',
        'dispense_request',
        'reason_code',
        'note',
        'is_vaccine',
        'raw_response',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'authored_on' => 'datetime',
            'dosage_instruction' => 'array',
            'dispense_request' => 'array',
            'is_vaccine' => 'boolean',
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

    public function requester(): BelongsTo
    {
        return $this->belongsTo(SatuSehatPractitioner::class, 'requester_ihs', 'ihs_number');
    }

    public function dispenses(): HasMany
    {
        return $this->hasMany(SatuSehatMedicationDispense::class, 'medication_request_ihs', 'ihs_number');
    }

    public static function findByIhs(string $ihsNumber): ?self
    {
        return static::where('ihs_number', $ihsNumber)->first();
    }
}
