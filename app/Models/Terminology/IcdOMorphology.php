<?php

namespace App\Models\Terminology;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class IcdOMorphology extends Model
{
    protected $table = 'icd_o_morphology';

    protected $fillable = ['code', 'display', 'version'];

    /** Daftar versi yang tersedia (di-cache 1 jam) */
    public static function getVersions(): array
    {
        return Cache::remember(
            'icd_o_morphology.versions',
            3600,
            fn() =>
            static::distinct()->orderBy('version')->pluck('version')->toArray()
        );
    }

    /** Hapus cache versi */
    public static function clearCache(): void
    {
        Cache::forget('icd_o_morphology.versions');
    }

    /** Jumlah data per versi */
    public static function countByVersion(string $version): int
    {
        return static::where('version', $version)->count();
    }
}
