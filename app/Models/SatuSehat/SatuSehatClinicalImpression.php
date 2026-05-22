<?php

namespace App\Models\SatuSehat;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SatuSehatClinicalImpression extends BaseModel
{
    protected $table = 'satu_sehat_clinical_impressions';

    protected $fillable = [
        'ihs_number',
        'identifier',
        'local_id',
        'patient_ihs',
        'encounter_ihs',
        'assessor_ihs',
        'status',
        'effective_datetime',
        'description',
        'finding',
        'problem',
        'summary',
        'prognosis_codeable_concept',
        'raw_response',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_datetime' => 'datetime',
            'finding' => 'array',
            'problem' => 'array',
            'prognosis_codeable_concept' => 'array',
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

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(SatuSehatPractitioner::class, 'assessor_ihs', 'ihs_number');
    }

    public static function findByIhs(string $ihsNumber): ?self
    {
        return static::where('ihs_number', $ihsNumber)->first();
    }
}
