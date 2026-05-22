<?php

namespace App\Models\Mapping;

use App\Models\BaseModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/** Mapping alergi lokal (SIMRS) ke konsep SNOMED CT (substance/allergen) */
class AlergiMap extends BaseModel
{
    protected $table = 'map_alergi';

    protected $fillable = [
        'alergi_id',
        'system_code',
        'system_term',
        'system_display',
    ];

    /** Ambil semua mapping dari cache, di-keyBy alergi_id */
    public static function getCached(): Collection
    {
        return Cache::remember(
            'alergi.mappings',
            3600,
            fn() => static::all()->keyBy('alergi_id'),
        );
    }

    /** Hapus cache mapping */
    public static function clearCache(): void
    {
        Cache::forget('alergi.mappings');
    }
}
