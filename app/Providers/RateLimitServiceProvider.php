<?php

namespace App\Providers;

use App\Constants\SecurityConfig;
use App\Models\Api\ApiSecurityLog;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class RateLimitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configureRateLimiters();
    }

    private function configureRateLimiters(): void
    {
        // Auth: cegah brute force token — konfigurabel
        RateLimiter::for('api.auth', function (Request $request) {
            if (!SecurityConfig::bool('api.security.rate_limit.enabled')) {
                return Limit::none();
            }

            $max    = SecurityConfig::int('api.security.rate_limit.auth_max');
            $window = SecurityConfig::int('api.security.rate_limit.auth_window');

            return Limit::perMinutes($window, $max)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) use ($max, $window) {
                    $this->logRateLimited($request, [
                        'limiter'     => 'api.auth',
                        'max'         => $max,
                        'window_min'  => $window,
                        'retry_after' => (int) ($headers['Retry-After'] ?? ($window * 60)),
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => "Terlalu banyak percobaan. Batas: {$max}× per {$window} menit. Coba lagi setelah beberapa saat.",
                    ], 429, $headers);
                });
        });

        // API umum: 300 request per menit per token — konfigurabel
        RateLimiter::for('api.general', function (Request $request) {
            if (!SecurityConfig::bool('api.security.rate_limit.enabled')) {
                return Limit::none();
            }

            $max      = SecurityConfig::int('api.security.rate_limit.general_max');
            $apiToken = $request->attributes->get('api_token');
            $key      = $apiToken ? 'token:' . $apiToken->id : 'ip:' . $request->ip();

            return Limit::perMinute($max)
                ->by($key)
                ->response(function (Request $request, array $headers) use ($max) {
                    $this->logRateLimited($request, [
                        'limiter'     => 'api.general',
                        'max'         => $max,
                        'window_min'  => 1,
                        'retry_after' => (int) ($headers['Retry-After'] ?? 60),
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => "Terlalu banyak request. Batas: {$max} per menit.",
                    ], 429, $headers);
                });
        });

        // Webhook: 60 request per menit per IP — konfigurabel
        RateLimiter::for('api.webhook', function (Request $request) {
            if (!SecurityConfig::bool('api.security.rate_limit.enabled')) {
                return Limit::none();
            }

            $max = SecurityConfig::int('api.security.rate_limit.webhook_max');

            return Limit::perMinute($max)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) use ($max) {
                    $this->logRateLimited($request, [
                        'limiter'     => 'api.webhook',
                        'max'         => $max,
                        'window_min'  => 1,
                        'retry_after' => (int) ($headers['Retry-After'] ?? 60),
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => "Terlalu banyak request webhook. Batas: {$max} per menit.",
                    ], 429, $headers);
                });
        });
    }

    private function logRateLimited(Request $request, array $detail): void
    {
        try {
            $apiToken = $request->attributes->get('api_token');
            $apiUser  = $request->attributes->get('api_user');

            ApiSecurityLog::create([
                'type'          => 'rate_limited',
                'ip_address'    => $request->ip(),
                'method'        => $request->method(),
                'path'          => $request->path(),
                'user_agent'    => substr($request->userAgent() ?? '', 0, 255),
                'api_user_id'   => $apiToken?->api_user_id,
                'api_user_name' => $apiUser?->name,
                'detail'        => $detail,
            ]);
        } catch (\Throwable) {
            // Jangan gagalkan response hanya karena logging error
        }
    }
}
