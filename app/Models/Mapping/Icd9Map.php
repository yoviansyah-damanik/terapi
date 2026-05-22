<?php

namespace App\Models\Mapping;

use App\Models\BaseModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class Icd9Map extends BaseModel
{
    protected $table = 'map_icd9';

    protected $fillable = [
        'icd9_code',
        'system_code',
        'system_term',
        'system_display',
    ];

    /** Ambil semua mapping dari cache, di-keyBy icd9_code */
    public static function getCached(): Collection
    {
        return Cache::remember(
            'icd9.mappings',
            3600,
            fn() => static::all()->keyBy('icd9_code')
        );
    }

    /** Hapus cache mapping */
    public static function clearCache(): void
    {
        Cache::forget('icd9.mappings');
    }

    public function icd9()
    {
        return $this->belongsTo(\App\Models\Simrs\Icd9::class, 'icd9_code', 'kode');
    }
}
