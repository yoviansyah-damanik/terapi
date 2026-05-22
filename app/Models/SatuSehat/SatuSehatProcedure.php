<?php

namespace App\Models\SatuSehat;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SatuSehatProcedure extends BaseModel
{
    protected $table = 'satu_sehat_procedures';

    protected $fillable = [
        'ihs_number',
        'local_id',
        'identifier',
        'patient_ihs',
        'encounter_ihs',
        'performer_ihs',
        'status',
        'category',
        'code',
        'code_display',
        'performed_datetime',
        'performed_period_start',
        'performed_period_end',
        'reason_code',
        'body_site',
        'outcome',
        'note',
        'raw_response',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'performed_datetime' => 'datetime',
            'performed_period_start' => 'datetime',
            'performed_period_end' => 'datetime',
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
}
