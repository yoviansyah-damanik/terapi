<?php

namespace App\Http\Middleware;

use App\Constants\SecurityConfig;
use App\Models\Api\ApiSecurityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LimitRequestSize
{
    public function handle(Request $request, Closure $next, int $defaultMaxKb = 1024): Response
    {
        if (!SecurityConfig::bool('api.security.input_size.enabled')) {
            return $next($request);
        }

        $maxBytes      = $defaultMaxKb * 1024;
        $contentLength = (int) $request->header('Content-Length', 0);

        if ($contentLength > $maxBytes) {
            $this->logOversizedRequest($request, $contentLength, $maxBytes);

            $label = $defaultMaxKb >= 1024
                ? round($defaultMaxKb / 1024, 1) . ' MB'
                : $defaultMaxKb . ' KB';

            return response()->json([
                'success' => false,
                'message' => "Request body terlalu besar. Maksimum {$label}.",
            ], 413);
        }

        return $next($request);
    }

    private function logOversizedRequest(Request $request, int $actual, int $max): void
    {
        try {
            $apiToken = $request->attributes->get('api_token');
            $apiUser  = $request->attributes->get('api_user');

            ApiSecurityLog::create([
                'type'          => 'oversized_request',
                'ip_address'    => $request->ip(),
                'method'        => $request->method(),
                'path'          => $request->path(),
                'user_agent'    => substr($request->userAgent() ?? '', 0, 255),
                'api_user_id'   => $apiToken?->api_user_id,
                'api_user_name' => $apiUser?->name,
                'detail'        => [
                    'content_length_bytes' => $actual,
                    'max_bytes'            => $max,
                ],
            ]);
        } catch (\Throwable) {
            // Jangan gagalkan request hanya karena logging error
        }
    }
}
