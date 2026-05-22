<?php

namespace App\Models\SatuSehat;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SatuSehatServiceRequest extends BaseModel
{
    protected $table = 'satu_sehat_service_requests';

    protected $fillable = [
        'ihs_number',
        'identifier',
        'local_id',
        'patient_ihs',
        'encounter_ihs',
        'requester_ihs',
        'status',
        'intent',
        'priority',
        'category',
        'code',
        'code_display',
        'authored_on',
        'occurrence_datetime',
        'reason_code',
        'performer_type',
        'location_code',
        'note',
        'raw_response',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'authored_on' => 'datetime',
            'occurrence_datetime' => 'datetime',
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

    public function requester(): BelongsTo
    {
        return $this->belongsTo(SatuSehatPractitioner::class, 'requester_ihs', 'ihs_number');
    }

    public function diagnosticReports(): HasMany
    {
        return $this->hasMany(SatuSehatDiagnosticReport::class, 'service_request_ihs', 'ihs_number');
    }

    public function specimens(): HasMany
    {
        return $this->hasMany(SatuSehatSpecimen::class, 'service_request_ihs', 'ihs_number');
    }

    public static function findByIhs(string $ihsNumber): ?self
    {
        return static::where('ihs_number', $ihsNumber)->first();
    }
}
