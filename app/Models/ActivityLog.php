<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends BaseModel
{

    protected $fillable = [
        'user_id',
        'type',
        'subject',
        'description',
        'loggable_type',
        'loggable_id',
        'properties',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    // ========== RELATIONSHIPS ==========

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }

    // ========== SCOPES ==========

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    // ========== HELPERS ==========

    /**
     * Simpan activity log baru
     */
    public static function log(string $type, string $subject, ?Model $loggable = null, ?string $description = null, ?array $properties = null): static
    {
        return static::create([
            'user_id' => auth()->id(),
            'type' => $type,
            'subject' => $subject,
            'description' => $description,
            'loggable_type' => $loggable ? get_class($loggable) : null,
            'loggable_id' => $loggable?->getKey(),
            'properties' => $properties,
        ]);
    }

    /**
     * Icon berdasarkan tipe aktivitas
     */
    public function getIconAttribute(): string
    {
        return match ($this->type) {
            'letter_created' => 'document-plus',
            'letter_archived' => 'archive-box',
            'disposition_created' => 'paper-airplane',
            'user_login' => 'arrow-right-end-on-rectangle',
            'user_created' => 'user-plus',
            'user_updated' => 'user',
            'user_deleted' => 'user-minus',
            'user_status_changed' => 'arrow-path',
            'password_changed' => 'key',
            'profile_updated' => 'user-circle',
            default => 'information-circle',
        };
    }

    /**
     * Warna berdasarkan tipe aktivitas
     */
    public function getColorAttribute(): string
    {
        return match ($this->type) {
            'letter_created' => 'blue',
            'letter_archived' => 'purple',
            'disposition_created' => 'green',
            'user_login' => 'cyan',
            'user_created' => 'green',
            'user_updated' => 'blue',
            'user_deleted' => 'red',
            'user_status_changed' => 'amber',
            'password_changed' => 'amber',
            'profile_updated' => 'indigo',
            default => 'zinc',
        };
    }
}
