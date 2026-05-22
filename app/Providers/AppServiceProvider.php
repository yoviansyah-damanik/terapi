<?php

namespace App\Providers;

use App\Constants\SecurityConfig;
use App\Helpers\ConfigurationHelper;
use App\Http\ViewComposers\SidebarComposer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->overrideCorsFromDb();
        $this->overrideConfigFromDb();

        Vite::macro('image', fn(string $asset) => $this->asset("resources/images/{$asset}"));

        View::composer('layouts::app', SidebarComposer::class);

        Gate::define('viewPulse', fn($user) => $user->isAdmin());
    }

    /**
     * Override nilai config() dari DB agar seluruh codebase otomatis membaca nilai terbaru
     * tanpa perlu mengubah setiap panggilan config() satu per satu.
     */
    protected function overrideConfigFromDb(): void
    {
        try {
            // Hospital
            $hospitalMap = [
                'hospital.name'        => 'hospital.name',
                'hospital.alias'       => 'hospital.alias',
                'hospital.phone'       => 'hospital.phone',
                'hospital.email'       => 'hospital.email',
                'hospital.website'     => 'hospital.website',
                'hospital.address'     => 'hospital.address',
                'hospital.city'        => 'hospital.city',
                'hospital.province'    => 'hospital.province',
                'hospital.postal_code' => 'hospital.postal_code',
                'hospital.country'     => 'hospital.country',
                // kode wilayah: DB key berbeda dari config key
                'hospital.propinsi_code'  => 'hospital.propinsi',
                'hospital.kabupaten_code' => 'hospital.kabupaten',
                'hospital.kecamatan_code' => 'hospital.kecamatan',
                'hospital.kelurahan_code' => 'hospital.kelurahan',
            ];
            foreach ($hospitalMap as $dbKey => $configKey) {
                if (($val = ConfigurationHelper::get($dbKey)) !== null) {
                    Config::set($configKey, $val);
                }
            }

            // Satu Sehat
            foreach (['auth_url', 'base_url', 'fhir_url', 'consent_url', 'client_id', 'client_secret',
                      'organization_id', 'kode_ppk_kemenkes', 'nama_ppk_kemenkes'] as $key) {
                if (($val = ConfigurationHelper::get("satusehat.{$key}")) !== null) {
                    Config::set("satusehat.{$key}", $val);
                }
            }

            // BPJS — per modul
            $bpjsModules  = ['vclaim', 'antrian_online', 'apotek_online', 'erm', 'icare', 'aplicare'];
            $hmacKeys     = ['base_url', 'cons_id', 'secret_key', 'user_key'];
            foreach ($bpjsModules as $module) {
                foreach ($hmacKeys as $key) {
                    if (($val = ConfigurationHelper::get("bpjs.{$module}.{$key}")) !== null) {
                        Config::set("bpjs.{$module}.{$key}", $val);
                    }
                }
            }
            foreach (['base_url', 'username', 'password'] as $key) {
                if (($val = ConfigurationHelper::get("bpjs.antrian_rs.{$key}")) !== null) {
                    Config::set("bpjs.antrian_rs.{$key}", $val);
                }
            }
            foreach (['kode_ppk', 'nama_ppk', 'kode_ppk_apotek', 'nama_ppk_apotek'] as $key) {
                if (($val = ConfigurationHelper::get("bpjs.{$key}")) !== null) {
                    Config::set("bpjs.{$key}", $val);
                }
            }

            // TTE & Snowstorm
            foreach (['base_url' => 'tte.base_url', 'username' => 'tte.username', 'password' => 'tte.password'] as $dbSuffix => $dbKey) {
                if (($val = ConfigurationHelper::get($dbKey)) !== null) {
                    Config::set("services.tte.{$dbSuffix}", $val);
                }
            }
            if (($val = ConfigurationHelper::get('snowstorm.url')) !== null) {
                Config::set('services.snowstorm.url', $val);
            }
            if (($val = ConfigurationHelper::get('snowstorm.branch')) !== null) {
                Config::set('services.snowstorm.branch', $val);
            }
        } catch (\Throwable) {
            // DB belum tersedia — biarkan nilai dari config/*.php
        }
    }

    protected function overrideCorsFromDb(): void
    {
        try {
            $origins = SecurityConfig::get('api.security.cors.allowed_origins');
            if ($origins) {
                config(['cors.allowed_origins' => array_map('trim', explode(',', $origins))]);
            }
        } catch (\Throwable) {
            // Tabel belum ada atau DB tidak tersedia — biarkan default dari config/cors.php
        }
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(
            fn(): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }
}
