<?php

namespace App\Models\Mapping;

use App\Models\BaseModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/** Mapping reaksi alergi lokal (SIMRS) ke konsep SNOMED CT (clinical finding) */
class AllergyReactionMap extends BaseModel
{
    protected $table = 'map_allergy_reaction';

    protected $fillable = [
        'reaction_id',
        'system_code',
        'system_term',
        'system_display',
    ];

    /** Ambil semua mapping dari cache, di-keyBy reaction_id */
    public static function getCached(): Collection
    {
        return Cache::remember(
            'allergy_reaction.mappings',
            3600,
            fn() => static::all()->keyBy('reaction_id'),
        );
    }

    /** Hapus cache mapping */
    public static function clearCache(): void
    {
        Cache::forget('allergy_reaction.mappings');
    }
}
