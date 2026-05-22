<?php

namespace App\Models\SatuSehat;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SatuSehatMedication extends BaseModel
{
    protected $table = 'satu_sehat_medications';

    protected $fillable = [
        'ihs_number',
        'identifier',
        'kfa_code',
        'kfa_display',
        'status',
        'form_code',
        'form_display',
        'ingredient',
        'medication_type',
        'extension',
        'raw_response',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'ingredient' => 'array',
            'extension' => 'array',
            'raw_response' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function medicationRequests(): HasMany
    {
        return $this->hasMany(SatuSehatMedicationRequest::class, 'medication_ihs', 'ihs_number');
    }

    public function medicationDispenses(): HasMany
    {
        return $this->hasMany(SatuSehatMedicationDispense::class, 'medication_ihs', 'ihs_number');
    }

    public static function findByIhs(string $ihsNumber): ?self
    {
        return static::where('ihs_number', $ihsNumber)->first();
    }

    public static function findByKfaCode(string $kfaCode): ?self
    {
        return static::where('kfa_code', $kfaCode)->first();
    }
}
