<?php

namespace App\Http\Middleware;

use App\Constants\AppSecurityConfig;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Redirect ke HTTPS jika dikonfigurasi dan bukan environment lokal
        try {
            if (AppSecurityConfig::bool('app.security.force_https') && !$request->secure() && !app()->isLocal()) {
                return redirect()->secure($request->getRequestUri(), 301);
            }
        } catch (\Throwable) {
            // Jangan biarkan error DB menghentikan redirect
        }

        $response = $next($request);

        try {
            if (!AppSecurityConfig::bool('app.security.headers.enabled')) {
                return $response;
            }

            $frameOptions = AppSecurityConfig::get('app.security.headers.frame_options');

            // Header dasar yang selalu diterapkan
            $response->headers->set('X-Content-Type-Options', 'nosniff');
            $response->headers->set('X-Frame-Options', $frameOptions === 'ALLOWALL' ? 'ALLOWALL' : $frameOptions);
            $response->headers->set('X-XSS-Protection', '1; mode=block');
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
            $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

            // HSTS — hanya aktifkan jika dikonfigurasi
            if (AppSecurityConfig::bool('app.security.headers.hsts')) {
                $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            }

            // Content Security Policy — hanya aktifkan jika dikonfigurasi
            if (AppSecurityConfig::bool('app.security.headers.csp_enabled')) {
                $cspValue = AppSecurityConfig::get('app.security.headers.csp_value');
                if ($cspValue) {
                    $response->headers->set('Content-Security-Policy', $cspValue);
                }
            }
        } catch (\Throwable) {
            // Error DB tidak boleh menggagalkan response
        }

        return $response;
    }
}
