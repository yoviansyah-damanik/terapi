<?php

namespace App\Helpers;

use App\Models\Configuration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class ConfigurationHelper
{
    private const CACHE_KEY = 'app_configurations';
    private const CACHE_TTL = 300; // 5 menit

    /** In-request cache agar tidak hit Laravel Cache berkali-kali dalam satu request. */
    private static ?array $requestCache = null;

    /**
     * Load semua konfigurasi dari DB ke cache.
     * Struktur: ['key' => ['value' => ..., 'is_encrypted' => bool]]
     */
    private static function load(): array
    {
        if (static::$requestCache !== null) {
            return static::$requestCache;
        }

        static::$requestCache = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            try {
                return Configuration::all()
                    ->keyBy('key')
                    ->map(fn ($c) => [
                        'value'        => $c->value,
                        'is_encrypted' => (bool) $c->is_encrypted,
                    ])
                    ->all();
            } catch (\Throwable) {
                return [];
            }
        });

        return static::$requestCache;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $entry = static::load()[$key] ?? null;

        if ($entry === null) {
            return $default;
        }

        $value = $entry['value'];

        if ($value === null || $value === '') {
            return $default;
        }

        if ($entry['is_encrypted']) {
            try {
                return Crypt::decryptString($value);
            } catch (\Exception) {
                Log::warning("ConfigurationHelper: gagal dekripsi key '{$key}'");
                return $default;
            }
        }

        return $value;
    }

    /**
     * @param bool $encrypted Enkripsi nilai menggunakan APP_KEY sebelum disimpan.
     *                        Gunakan untuk kunci API, secret, password.
     */
    public static function set(string $key, ?string $value, bool $encrypted = false): void
    {
        $shouldEncrypt = $encrypted && $value !== null && $value !== '';

        Configuration::updateOrCreate(
            ['key' => $key],
            [
                'value'        => $shouldEncrypt ? Crypt::encryptString($value) : $value,
                'is_encrypted' => $shouldEncrypt,
            ]
        );

        Cache::forget(self::CACHE_KEY);
        static::$requestCache = null;
    }
}
