<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Layanan caching terpusat untuk data terminologi medis.
 *
 * Driver cache yang digunakan adalah database (tidak mendukung tag),
 * sehingga invalidasi grup cache LOINC dilakukan dengan strategi berbasis versi:
 * - Setiap key LOINC menyertakan nomor versi saat ini
 * - Saat import selesai, versi dinaikkan → key lama menjadi stale dan expired secara natural
 */
class TerminologyCacheService
{
    /** TTL dalam detik */
    const TTL_SNOMED = 86400; // 24 jam — data SNOMED CT jarang berubah
    const TTL_KFA = 21600; // 6 jam  — data KFA dapat update harian
    const TTL_LOINC_FILTERS = 86400; // 24 jam — daftar opsi filter (jarang berubah)
    const TTL_ICD_FILTERS = 86400; // 24 jam — daftar opsi filter ICD

    // ------------------------------------------------------------------ //
    //  SNOMED CT
    // ------------------------------------------------------------------ //

    /** Kembalikan cache key untuk pencarian SNOMED CT */
    public static function snomedKey(array $params): string
    {
        return 'terminology:snomed:' . md5(json_encode($params));
    }

    // ------------------------------------------------------------------ //
    //  KFA
    // ------------------------------------------------------------------ //

    /** Kembalikan cache key untuk pencarian KFA */
    public static function kfaKey(string $type, array $params): string
    {
        return "terminology:kfa:{$type}:" . md5(json_encode($params));
    }

    // ------------------------------------------------------------------ //
    //  LOINC — berbasis versi untuk invalidasi grup
    // ------------------------------------------------------------------ //

    /** Dapatkan nomor versi cache LOINC saat ini (default: 1) */
    public static function getLoincVersion(): int
    {
        return (int) Cache::get('terminology:loinc:version', 1);
    }

    /**
     * Cache daftar nilai distinct untuk satu kolom filter LOINC.
     * Key menyertakan nomor versi sehingga otomatis stale saat versi naik.
     */
    public static function rememberLoincFilter(string $field, Closure $callback): array
    {
        $v = self::getLoincVersion();
        $key = "terminology:loinc:v{$v}:filters:{$field}";

        return Cache::remember($key, self::TTL_LOINC_FILTERS, $callback);
    }

    /**
     * Invalidasi seluruh cache LOINC dengan menaikkan nomor versi.
     * Entry lama akan expired secara natural sesuai TTL masing-masing.
     */
    public static function clearLoinc(): void
    {
        $current = self::getLoincVersion();
        Cache::forever('terminology:loinc:version', $current + 1);
    }

    // ------------------------------------------------------------------ //
    //  ICD
    // ------------------------------------------------------------------ //

    /** Cache daftar nilai distinct untuk filter ICD (misal: status ICD-10) */
    public static function rememberIcdFilter(string $type, string $field, Closure $callback): array
    {
        $key = "terminology:{$type}:filters:{$field}";

        return Cache::remember($key, self::TTL_ICD_FILTERS, $callback);
    }
}
