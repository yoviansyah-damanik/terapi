<?php

namespace App\Models\SatuSehat;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SatuSehatImmunization extends BaseModel
{
    protected $table = 'satu_sehat_immunizations';

    protected $fillable = [
        'ihs_number',
        'local_id',
        'identifier',
        'patient_ihs',
        'encounter_ihs',
        'performer_ihs',
        'location_ihs',
        'status',
        'vaccine_code',
        'vaccine_display',
        'occurrence_datetime',
        'lot_number',
        'expiration_date',
        'site',
        'route',
        'dose_quantity',
        'dose_unit',
        'dose_number',
        'reason_code',
        'note',
        'raw_response',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'occurrence_datetime' => 'datetime',
            'expiration_date' => 'date',
            'dose_quantity' => 'decimal:2',
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

    public function location(): BelongsTo
    {
        return $this->belongsTo(SatuSehatLocation::class, 'location_ihs', 'ihs_number');
    }

    public static function findByIhs(string $ihsNumber): ?self
    {
        return static::where('ihs_number', $ihsNumber)->first();
    }
}
