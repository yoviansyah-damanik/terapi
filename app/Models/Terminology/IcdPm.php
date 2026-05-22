<?php

namespace App\Models\Terminology;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class IcdPm extends Model
{
    protected $table = 'icd_pm';

    protected $fillable = ['category', 'category_display', 'subcategory', 'subcategory_display', 'code', 'display', 'version'];

    /** Daftar versi yang tersedia (di-cache 1 jam) */
    public static function getVersions(): array
    {
        return Cache::remember(
            'icd_pm.versions',
            3600,
            fn() =>
            static::distinct()->orderBy('version')->pluck('version')->toArray()
        );
    }

    /** Hapus cache versi */
    public static function clearCache(): void
    {
        Cache::forget('icd_pm.versions');
    }

    /** Daftar kategori utama yang tersedia */
    public static function getCategories(string $version = ''): \Illuminate\Support\Collection
    {
        return static::when($version, fn($q) => $q->where('version', $version))
            ->whereNotNull('category')->distinct()->orderBy('category')
            ->select('category', 'category_display')->get()->unique('category');
    }

    /** Daftar subkategori untuk kategori tertentu */
    public static function getSubcategories(string $version = '', string $category = ''): \Illuminate\Support\Collection
    {
        return static::when($version, fn($q) => $q->where('version', $version))
            ->when($category, fn($q) => $q->where('category', $category))
            ->whereNotNull('subcategory')->distinct()->orderBy('subcategory')
            ->select('subcategory', 'subcategory_display')->get()->unique('subcategory');
    }

    /** Jumlah data per versi */
    public static function countByVersion(string $version): int
    {
        return static::where('version', $version)->count();
    }
}
