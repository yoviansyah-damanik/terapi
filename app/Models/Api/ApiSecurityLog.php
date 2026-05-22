<?php

namespace App\Models\Api;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiSecurityLog extends BaseModel
{
    protected $fillable = [
        'type',
        'ip_address',
        'method',
        'path',
        'user_agent',
        'api_user_id',
        'api_user_name',
        'detail',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'detail'      => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function apiUser(): BelongsTo
    {
        return $this->belongsTo(ApiUser::class);
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function getTypeColorAttribute(): string
    {
        return match ($this->type) {
            'rate_limited'         => 'amber',
            'oversized_request'    => 'orange',
            'anomaly_high_failure' => 'red',
            'anomaly_high_volume'  => 'violet',
            'anomaly_brute_force'  => 'red',
            default                => 'zinc',
        };
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'rate_limited'         => 'Rate Limited',
            'oversized_request'    => 'Request Terlalu Besar',
            'anomaly_high_failure' => 'Tingkat Error Tinggi',
            'anomaly_high_volume'  => 'Volume Tinggi',
            'anomaly_brute_force'  => 'Brute Force',
            default                => $this->type,
        };
    }
}
