<?php

namespace App\Models\SatuSehat;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SatuSehatEpisodeOfCare extends BaseModel
{
    protected $table = 'satu_sehat_episode_of_cares';

    protected $fillable = [
        'ihs_number',
        'identifier',
        'local_id',
        'patient_ihs',
        'managing_organization_ihs',
        'care_manager_ihs',
        'status',
        'type_code',
        'type_display',
        'diagnosis',
        'period_start',
        'period_end',
        'raw_response',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'diagnosis' => 'array',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'raw_response' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(SatuSehatPatient::class, 'patient_ihs', 'ihs_number');
    }

    public function managingOrganization(): BelongsTo
    {
        return $this->belongsTo(SatuSehatOrganization::class, 'managing_organization_ihs', 'ihs_number');
    }

    public function careManager(): BelongsTo
    {
        return $this->belongsTo(SatuSehatPractitioner::class, 'care_manager_ihs', 'ihs_number');
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
