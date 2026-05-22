<?php

namespace App\Models\Api;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiLog extends BaseModel
{
    protected $fillable = [
        'api_user_id',
        'api_user_name',
        'method',
        'path',
        'scope',
        'query_string',
        'ip_address',
        'user_agent',
        'request_headers',
        'request_body',
        'response_status',
        'response_time_ms',
        'response_body',
    ];

    protected function casts(): array
    {
        return [
            'request_headers' => 'array',
            'request_body' => 'array',
            'response_body' => 'array',
            'response_status' => 'integer',
            'response_time_ms' => 'integer',
        ];
    }

    public function apiUser(): BelongsTo
    {
        return $this->belongsTo(ApiUser::class);
    }

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('api_user_id', $userId);
    }

    public function scopeForStatus(Builder $query, string $range): Builder
    {
        return match ($range) {
            '2xx' => $query->whereBetween('response_status', [200, 299]),
            '4xx' => $query->whereBetween('response_status', [400, 499]),
            '5xx' => $query->whereBetween('response_status', [500, 599]),
            default => $query,
        };
    }

    public function scopeForScope(Builder $query, string $scope): Builder
    {
        return $scope === 'none'
            ? $query->whereNull('scope')
            : $query->where('scope', $scope);
    }

    public function scopeForMethod(Builder $query, string $method): Builder
    {
        return $query->where('method', strtoupper($method));
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function ($q) use ($term) {
            $q->where('path', 'like', "%{$term}%")
              ->orWhere('ip_address', 'like', "%{$term}%")
              ->orWhere('api_user_name', 'like', "%{$term}%");
        });
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('created_at', today());
    }

    /** Warna badge berdasarkan HTTP status */
    public function getStatusColorAttribute(): string
    {
        return match (true) {
            $this->response_status >= 500 => 'red',
            $this->response_status >= 400 => 'amber',
            $this->response_status >= 300 => 'zinc',
            $this->response_status >= 200 => 'green',
            default => 'zinc',
        };
    }
}
