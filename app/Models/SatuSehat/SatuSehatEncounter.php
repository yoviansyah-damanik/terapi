<?php

namespace App\Models\SatuSehat;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SatuSehatEncounter extends BaseModel
{
    protected $table = 'satu_sehat_encounters';

    protected $fillable = [
        'ihs_number',
        'local_id',
        'status',
        'class',
        'patient_ihs',
        'patient_name',
        'practitioner_ihs',
        'practitioner_name',
        'location_ihs',
        'location_name',
        'service_provider',
        'period_start',
        'period_end',
        'diagnosis',
        'status_history',
        'raw_response',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'diagnosis' => 'array',
            'status_history' => 'array',
            'raw_response' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(SatuSehatPatient::class, 'patient_ihs', 'ihs_number');
    }

    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(SatuSehatPractitioner::class, 'practitioner_ihs', 'ihs_number');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(SatuSehatLocation::class, 'location_ihs', 'ihs_number');
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(SatuSehatCondition::class, 'encounter_ihs', 'ihs_number');
    }

    public function observations(): HasMany
    {
        return $this->hasMany(SatuSehatObservation::class, 'encounter_ihs', 'ihs_number');
    }

    public function medicationRequests(): HasMany
    {
        return $this->hasMany(SatuSehatMedicationRequest::class, 'encounter_ihs', 'ihs_number');
    }

    public function procedures(): HasMany
    {
        return $this->hasMany(SatuSehatProcedure::class, 'encounter_ihs', 'ihs_number');
    }

    public static function findByIhs(string $ihsNumber): ?self
    {
        return static::where('ihs_number', $ihsNumber)->first();
    }

    public function isFinished(): bool
    {
        return $this->status === 'finished';
    }
}
