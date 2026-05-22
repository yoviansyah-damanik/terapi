<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
class IpBlacklist extends BaseModel
{
    protected $fillable = ['ip_address', 'reason', 'blocked_by', 'expires_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    /** Filter hanya entri yang masih aktif (belum kedaluwarsa) */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public static function isBlocked(string $ip): bool
    {
        return static::active()->where('ip_address', $ip)->exists();
    }

    /** Tambahkan atau perbarui IP di blacklist */
    public static function block(string $ip, ?string $reason = null, ?\DateTimeInterface $expiresAt = null, string $blockedBy = 'system'): static
    {
        return static::updateOrCreate(
            ['ip_address' => $ip],
            ['reason' => $reason, 'blocked_by' => $blockedBy, 'expires_at' => $expiresAt],
        );
    }
}
