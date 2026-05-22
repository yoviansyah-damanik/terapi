<?php

namespace App\Models\SatuSehat;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SatuSehatQuestionnaireResponse extends BaseModel
{
    protected $table = 'satu_sehat_questionnaire_responses';

    protected $fillable = [
        'ihs_number',
        'local_id',
        'identifier',
        'type',
        'questionnaire',
        'status',
        'patient_ihs',
        'encounter_ihs',
        'author_ihs',
        'authored',
        'raw_response',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'authored' => 'datetime',
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

    public function author(): BelongsTo
    {
        return $this->belongsTo(SatuSehatPractitioner::class, 'author_ihs', 'ihs_number');
    }

    public static function findByIhs(string $ihsNumber): ?self
    {
        return static::where('ihs_number', $ihsNumber)->first();
    }
}
