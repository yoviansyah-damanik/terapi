<?php

namespace App\Models\SatuSehat;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SatuSehatDiagnosticReport extends BaseModel
{
    protected $table = 'satu_sehat_diagnostic_reports';

    protected $fillable = [
        'ihs_number',
        'identifier',
        'local_id',
        'patient_ihs',
        'encounter_ihs',
        'service_request_ihs',
        'performer_ihs',
        'status',
        'category',
        'code',
        'code_display',
        'effective_datetime',
        'issued',
        'result',
        'specimen',
        'conclusion',
        'conclusion_code',
        'raw_response',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_datetime' => 'datetime',
            'issued' => 'datetime',
            'result' => 'array',
            'specimen' => 'array',
            'conclusion_code' => 'array',
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

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(SatuSehatServiceRequest::class, 'service_request_ihs', 'ihs_number');
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
