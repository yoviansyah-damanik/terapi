<?php

namespace App\Models\SatuSehat;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SatuSehatBundle extends BaseModel
{
    const STATUS_QUEUED    = 'queued';
    const STATUS_RUNNING   = 'running';
    const STATUS_COMPLETED = 'completed';
    const STATUS_PARTIAL   = 'partial';
    const STATUS_FAILED    = 'failed';

    protected $table = 'satu_sehat_bundles';

    protected $fillable = [
        'no_rawat',
        'triggered_by',
        'status',
        'total_sent',
        'total_errors',
        'encounter_finished',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'encounter_finished' => 'boolean',
            'started_at'         => 'datetime',
            'completed_at'       => 'datetime',
        ];
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(SatuSehatBundleLog::class, 'bundle_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SatuSehatBundleItem::class, 'bundle_log_id');
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_COMPLETED => 'green',
            self::STATUS_PARTIAL   => 'amber',
            self::STATUS_FAILED    => 'red',
            self::STATUS_RUNNING   => 'blue',
            default                => 'zinc',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_COMPLETED => 'Selesai',
            self::STATUS_PARTIAL   => 'Sebagian Gagal',
            self::STATUS_FAILED    => 'Gagal',
            self::STATUS_RUNNING   => 'Berjalan',
            self::STATUS_QUEUED    => 'Antrian',
            default                => $this->status,
        };
    }

    public function getDurationAttribute(): ?string
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }
        $seconds = $this->started_at->diffInSeconds($this->completed_at);
        return $seconds >= 60
            ? floor($seconds / 60) . 'm ' . ($seconds % 60) . 's'
            : $seconds . 's';
    }

    public function scopeForNoRawat($query, string $noRawat)
    {
        return $query->where('no_rawat', $noRawat);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
