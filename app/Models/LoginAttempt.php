<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class LoginAttempt extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'username',
        'ip_address',
        'user_agent',
        'success',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'success'    => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    // ========== SCOPES ==========

    public function scopeFailed(Builder $query): Builder
    {
        return $query->whereSuccess(false);
    }

    public function scopeForIp(Builder $query, string $ip): Builder
    {
        return $query->where('ip_address', $ip);
    }

    /**
     * Filter percobaan dalam N menit terakhir
     */
    public function scopeRecent(Builder $query, int $minutes = 15): Builder
    {
        return $query->where('created_at', '>=', now()->subMinutes($minutes));
    }

    // ========== HELPERS ==========

    /**
     * Catat percobaan login ke database
     */
    public static function log(
        string $username,
        string $ip,
        ?string $userAgent,
        bool $success,
        ?string $reason = null,
    ): static {
        return static::create([
            'username'       => $username,
            'ip_address'     => $ip,
            'user_agent'     => $userAgent,
            'success'        => $success,
            'failure_reason' => $reason,
        ]);
    }
}
