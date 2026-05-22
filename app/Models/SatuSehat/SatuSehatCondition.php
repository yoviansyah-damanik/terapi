<?php

namespace App\Models\SatuSehat;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SatuSehatCondition extends BaseModel
{
    protected $table = 'satu_sehat_conditions';

    protected $fillable = [
        'ihs_number',
        'local_id',
        'identifier',
        'patient_ihs',
        'encounter_ihs',
        'clinical_status',
        'verification_status',
        'category',
        'icd_code',
        'icd_display',
        'onset_datetime',
        'abatement_datetime',
        'recorder_ihs',
        'note',
        'raw_response',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'onset_datetime' => 'datetime',
            'abatement_datetime' => 'datetime',
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

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(SatuSehatPractitioner::class, 'recorder_ihs', 'ihs_number');
    }

    public static function findByIhs(string $ihsNumber): ?self
    {
        return static::where('ihs_number', $ihsNumber)->first();
    }

    public function isActive(): bool
    {
        return $this->clinical_status === 'active';
    }
}
