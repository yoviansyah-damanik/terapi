<?php

namespace App\Constants;

use App\Helpers\ConfigurationHelper;

/**
 * Centralisasi key + default untuk konfigurasi keamanan umum aplikasi.
 * Semua nilai disimpan di tabel `configurations` via ConfigurationHelper.
 */
class AppSecurityConfig
{
    const DEFAULTS = [
        // Login throttling
        'app.security.login.enabled'         => '1',
        'app.security.login.max_attempts'    => '5',
        'app.security.login.lockout_minutes' => '15',

        // HTTP Security Headers
        'app.security.headers.enabled'       => '1',
        'app.security.headers.frame_options' => 'SAMEORIGIN', // SAMEORIGIN | DENY | ALLOWALL
        'app.security.headers.hsts'          => '0',
        'app.security.headers.csp_enabled'   => '0',
        'app.security.headers.csp_value'     => "default-src 'self'",

        // Force HTTPS
        'app.security.force_https'           => '0',

        // CAPTCHA anti-robot
        'app.security.captcha.enabled'               => '1',
        'app.security.captcha.trigger_after'         => '2', // tampilkan setelah N kegagalan

        // IP Blacklist
        'app.security.blacklist.auto_enabled'        => '0',
        'app.security.blacklist.auto_threshold'      => '20', // gagal dalam 60 menit → blacklist
        'app.security.blacklist.auto_duration_hours' => '24', // 0 = permanen
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
