<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasUuids, HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'name',
        'email',
        'password',
        'role',
        'permissions',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
            'permissions'       => 'array',
        ];
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function hasPermission(string $key): bool
    {
        if ($this->isAdmin()) return true;

        $perms = $this->permissions ?? [];

        if (in_array($key, $perms)) return true;

        // Punya child → parent group terlihat (misal: punya 'bpjs.erm' → 'bpjs' true)
        foreach ($perms as $p) {
            if (str_starts_with($p, $key . '.')) return true;
        }

        // Punya parent → semua child accessible (misal: punya 'bpjs' → 'bpjs.erm' true)
        $prefix = explode('.', $key)[0];
        if ($prefix !== $key && in_array($prefix, $perms)) return true;

        return false;
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    public static function getRoles(): array
    {
        return [
            'admin' => 'Administrator',
            'user' => 'Pengguna',
        ];
    }
}
