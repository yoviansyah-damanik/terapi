<?php

namespace App\Models\Mapping;

use App\Models\BaseModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class Icd10Map extends BaseModel
{
    protected $table = 'map_icd10';

    protected $fillable = [
        'icd10_code',
        'system_code',
        'system_term',
        'system_display',
    ];

    /** Ambil semua mapping dari cache, di-keyBy icd10_code */
    public static function getCached(): Collection
    {
        return Cache::remember(
            'icd10.mappings',
            3600,
            fn() => static::all()->keyBy('icd10_code')
        );
    }

    /** Hapus cache mapping */
    public static function clearCache(): void
    {
        Cache::forget('icd10.mappings');
    }

    public function penyakit()
    {
        return $this->belongsTo(\App\Models\Simrs\Penyakit::class, 'icd10_code', 'kd_penyakit');
    }
}
