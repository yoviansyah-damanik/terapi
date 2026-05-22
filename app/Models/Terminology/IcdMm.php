<?php

namespace App\Models\Terminology;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class IcdMm extends Model
{
    protected $table = 'icd_mm';

    protected $fillable = ['annex', 'annex_display', 'group_code', 'group_display', 'code', 'display', 'version'];

    /** Daftar versi yang tersedia (di-cache 1 jam) */
    public static function getVersions(): array
    {
        return Cache::remember(
            'icd_mm.versions',
            3600,
            fn() =>
            static::distinct()->orderBy('version')->pluck('version')->toArray()
        );
    }

    /** Hapus cache versi */
    public static function clearCache(): void
    {
        Cache::forget('icd_mm.versions');
    }

    /** Daftar annex yang tersedia untuk versi tertentu */
    public static function getAnnexes(string $version = ''): array
    {
        return static::when($version, fn($q) => $q->where('version', $version))
            ->whereNotNull('annex')->distinct()->orderBy('annex')->pluck('annex')->toArray();
    }

    /** Daftar group yang tersedia */
    public static function getGroups(string $version = '', string $annex = ''): \Illuminate\Support\Collection
    {
        return static::when($version, fn($q) => $q->where('version', $version))
            ->when($annex, fn($q) => $q->where('annex', $annex))
            ->whereNotNull('group_code')->distinct()->orderBy('group_code')
            ->select('group_code', 'group_display')->get()->unique('group_code');
    }

    /** Jumlah data per versi */
    public static function countByVersion(string $version): int
    {
        return static::where('version', $version)->count();
    }
}
