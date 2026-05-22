<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SimrsVersion extends Model
{
    protected $fillable = [
        'version',
        'notes',
        'file_path',
        'checksum',
        'file_size',
        'is_active',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'released_at' => 'datetime',
            'is_active'   => 'boolean',
            'file_size'   => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected static function boot(): void
    {
        parent::boot();

        // Pastikan hanya satu versi yang aktif pada satu waktu
        static::saving(function (SimrsVersion $model) {
            if ($model->is_active) {
                static::where('id', '!=', $model->id ?? 0)->update(['is_active' => false]);
            }
        });
    }

    /** Format ukuran file ke string yang mudah dibaca */
    public function getFileSizeLabelAttribute(): string
    {
        if (!$this->file_size) return '—';
        $units = ['B', 'KB', 'MB', 'GB'];
        $size  = $this->file_size;
        $i     = 0;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }
        return round($size, 2) . ' ' . $units[$i];
    }
}
