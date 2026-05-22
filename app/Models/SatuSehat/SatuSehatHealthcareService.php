<?php

namespace App\Models\SatuSehat;

use App\Models\BaseModel;

class SatuSehatHealthcareService extends BaseModel
{
    protected $table = 'satu_sehat_healthcare_services';

    protected $fillable = [
        'ihs_number',
        'identifier',
        'name',
        'status',
        'raw_response',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_response' => 'array',
            'synced_at'    => 'datetime',
        ];
    }

    public function getActiveAttribute(): bool
    {
        return $this->status === 'active';
    }

    public static function findByIdentifier(string $identifier): ?self
    {
        return static::where('identifier', $identifier)->first();
    }
}
