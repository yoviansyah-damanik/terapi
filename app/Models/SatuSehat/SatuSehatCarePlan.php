<?php

namespace App\Models\SatuSehat;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SatuSehatCarePlan extends BaseModel
{
    protected $table = 'satu_sehat_care_plans';

    protected $fillable = [
        'ihs_number',
        'identifier',
        'local_id',
        'patient_ihs',
        'encounter_ihs',
        'author_ihs',
        'status',
        'intent',
        'title',
        'category_code',
        'category_display',
        'description',
        'period_start',
        'period_end',
        'created',
        'activity',
        'goal',
        'addresses',
        'note',
        'raw_response',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'created' => 'datetime',
            'activity' => 'array',
            'goal' => 'array',
            'addresses' => 'array',
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

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
