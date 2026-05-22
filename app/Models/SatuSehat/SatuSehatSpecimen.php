<?php

namespace App\Models\SatuSehat;

use App\Models\BaseModel;
use App\Models\SatuSehat\SatuSehatEncounter;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SatuSehatSpecimen extends BaseModel
{
    protected $table = 'satu_sehat_specimens';

    protected $fillable = [
        'ihs_number',
        'identifier',
        'local_id',
        'patient_ihs',
        'service_request_ihs',
        'encounter_ihs',
        'status',
        'type_code',
        'type_display',
        'collected_datetime',
        'collector_ihs',
        'received_time',
        'container',
        'note',
        'raw_response',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'collected_datetime' => 'datetime',
            'received_time' => 'datetime',
            'container' => 'array',
            'raw_response' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(SatuSehatPatient::class, 'patient_ihs', 'ihs_number');
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(SatuSehatServiceRequest::class, 'service_request_ihs', 'ihs_number');
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(SatuSehatEncounter::class, 'encounter_ihs', 'ihs_number');
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(SatuSehatPractitioner::class, 'collector_ihs', 'ihs_number');
    }

    public static function findByIhs(string $ihsNumber): ?self
    {
        return static::where('ihs_number', $ihsNumber)->first();
    }
}
