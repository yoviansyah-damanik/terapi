<?php

namespace App\Models\Mapping;

use App\Models\BaseModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class IcdMmMap extends BaseModel
{
    protected $table = 'map_icd_mm';

    protected $fillable = [
        'code',
        'version',
        'system_code',
        'system_term',
        'system_display',
    ];

    /** Ambil semua mapping dari cache, di-keyBy "code|version" */
    public static function getCached(): Collection
    {
        return Cache::remember(
            'icd_mm.mappings',
            3600,
            fn() => static::all()->keyBy(fn($m) => $m->code . '|' . $m->version)
        );
    }

    /** Hapus cache mapping */
    public static function clearCache(): void
    {
        Cache::forget('icd_mm.mappings');
    }
}
