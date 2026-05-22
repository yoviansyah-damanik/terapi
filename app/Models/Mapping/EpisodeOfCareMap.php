<?php

namespace App\Models\Mapping;

use App\Models\BaseModel;
use App\Models\FhirDictionary;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class EpisodeOfCareMap extends BaseModel
{
    protected $table = 'map_episode_of_care';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'eoc_code',
        'icd10_code',
        'notes',
    ];

    /** Ambil semua mapping dari cache, di-groupBy eoc_code */
    public static function getCached(): Collection
    {
        return Cache::remember(
            'episode_of_care.mappings',
            3600,
            fn() => static::all()->groupBy('eoc_code')
        );
    }

    /** Hapus cache mapping */
    public static function clearCache(): void
    {
        Cache::forget('episode_of_care.mappings');
    }

    /** Relasi ke FhirDictionary (episode type) */
    public function episodeType()
    {
        return $this->belongsTo(FhirDictionary::class, 'eoc_code', 'system_code')
            ->where('type', 'episode-of-care-type');
    }
}
