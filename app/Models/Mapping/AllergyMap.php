<?php

namespace App\Models\Mapping;

use App\Models\BaseModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/** Mapping alergi lokal (SIMRS) ke konsep SNOMED CT (substance/allergen) */
class AllergyMap extends BaseModel
{
    protected $table = 'map_allergy';

    protected $fillable = [
        'allergy_id',
        'system_code',
        'system_term',
        'system_display',
    ];

    /** Ambil semua mapping dari cache, di-keyBy allergy_id */
    public static function getCached(): Collection
    {
        return Cache::remember(
            'allergy.mappings',
            3600,
            fn() => static::all()->keyBy('allergy_id'),
        );
    }

    /** Hapus cache mapping */
    public static function clearCache(): void
    {
        Cache::forget('allergy.mappings');
    }
}
