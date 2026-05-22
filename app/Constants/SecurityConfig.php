<?php

namespace App\Constants;

use App\Helpers\ConfigurationHelper;

/**
 * Centralisasi key + default untuk konfigurasi keamanan API.
 * Semua nilai disimpan di tabel `configurations` via ConfigurationHelper.
 */
class SecurityConfig
{
    const DEFAULTS = [
        // Rate Limiter
        'api.security.rate_limit.enabled'       => '1',
        'api.security.rate_limit.auth_max'      => '10',   // maks request
        'api.security.rate_limit.auth_window'   => '5',    // per N menit
        'api.security.rate_limit.general_max'   => '300',  // per menit per token
        'api.security.rate_limit.webhook_max'   => '60',   // per menit per IP

        // Input Size Limit
        'api.security.input_size.enabled'       => '1',
        'api.security.input_size.auth_kb'       => '256',
        'api.security.input_size.simrs_kb'      => '2048',
        'api.security.input_size.wa_kb'         => '5120',
        'api.security.input_size.tte_kb'        => '20480',

        // Anomaly Detection
        'api.security.anomaly.enabled'          => '1',
        'api.security.anomaly.window_minutes'   => '15',
        'api.security.anomaly.min_requests'     => '20',   // minimal request agar error rate dihitung
        'api.security.anomaly.error_rate_pct'   => '30',   // % error yang dianggap anomali
        'api.security.anomaly.high_volume'      => '500',  // request per window
        'api.security.anomaly.brute_force'      => '20',   // gagal auth per window

        // CORS
        'api.security.cors.allowed_origins'     => '*',
    ];

    public static function get(string $key): string
    {
        return ConfigurationHelper::get($key, static::DEFAULTS[$key] ?? '') ?? '';
    }

    public static function bool(string $key): bool
    {
        return static::get($key) === '1';
    }

    public static function int(string $key): int
    {
        return (int) static::get($key);
    }

    public static function set(string $key, mixed $value): void
    {
        ConfigurationHelper::set($key, (string) $value);
    }

    /** Kembalikan semua nilai (DB ?? default) sebagai array asosiatif */
    public static function all(): array
    {
        return collect(static::DEFAULTS)
            ->mapWithKeys(fn($default, $key) => [$key => ConfigurationHelper::get($key, $default) ?? $default])
            ->all();
    }
}
