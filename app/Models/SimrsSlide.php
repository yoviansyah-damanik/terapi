<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class SimrsSlide extends BaseModel
{
    protected $fillable = [
        'title',
        'href',
        'file_path',
        'mime_type',
        'file_size',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'file_size'  => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('created_at');
    }

    public function getFileSizeLabelAttribute(): string
    {
        $size = $this->file_size;
        foreach (['B', 'KB', 'MB'] as $unit) {
            if ($size < 1024) {
                return round($size, 1) . ' ' . $unit;
            }
            $size /= 1024;
        }
        return round($size, 1) . ' GB';
    }
}
