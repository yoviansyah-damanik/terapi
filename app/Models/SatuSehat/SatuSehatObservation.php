<?php

namespace App\Models\SatuSehat;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SatuSehatObservation extends BaseModel
{
    protected $table = 'satu_sehat_observations';

    protected $fillable = [
        'ihs_number',
        'local_id',
        'identifier',
        'patient_ihs',
        'encounter_ihs',
        'status',
        'category',
        'code',
        'code_display',
        'value_type',
        'value_quantity',
        'value_unit',
        'value_string',
        'value_codeable_concept',
        'effective_datetime',
        'performer_ihs',
        'interpretation',
        'reference_range',
        'raw_response',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'value_quantity' => 'decimal:4',
            'value_codeable_concept' => 'array',
            'effective_datetime' => 'datetime',
            'interpretation' => 'array',
            'reference_range' => 'array',
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

    public function performer(): BelongsTo
    {
        return $this->belongsTo(SatuSehatPractitioner::class, 'performer_ihs', 'ihs_number');
    }

    public static function findByIhs(string $ihsNumber): ?self
    {
        return static::where('ihs_number', $ihsNumber)->first();
    }

    public function getValue(): mixed
    {
        return match ($this->value_type) {
            'Quantity' => $this->value_quantity,
            'String' => $this->value_string,
            'CodeableConcept' => $this->value_codeable_concept,
            default => null,
        };
    }
}
