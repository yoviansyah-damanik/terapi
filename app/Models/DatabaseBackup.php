<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;

class DatabaseBackup extends BaseModel
{
    protected $table = 'database_backups';

    protected $fillable = [
        'connection',
        'status',
        'filename',
        'file_size',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at'   => 'datetime',
            'completed_at' => 'datetime',
            'file_size'    => 'integer',
        ];
    }

    public function getFileSizeHumanAttribute(): string
    {
        $bytes = $this->file_size ?? 0;
        if ($bytes >= 1_048_576) return round($bytes / 1_048_576, 2) . ' MB';
        if ($bytes >= 1_024)    return round($bytes / 1_024, 1) . ' KB';
        return $bytes . ' B';
    }

    public function getDurationAttribute(): ?string
    {
        if (!$this->started_at || !$this->completed_at) return null;
        $s = $this->started_at->diffInSeconds($this->completed_at);
        return $s >= 60 ? floor($s / 60) . 'm ' . ($s % 60) . 's' : $s . 's';
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'success' => 'green',
            'failed'  => 'red',
            default   => 'blue',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'success' => 'Berhasil',
            'failed'  => 'Gagal',
            default   => 'Berjalan',
        };
    }

    public function scopeForConnection($query, string $connection)
    {
        return $query->where('connection', $connection);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }
}
