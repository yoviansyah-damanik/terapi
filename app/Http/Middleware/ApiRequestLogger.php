<?php

namespace App\Http\Middleware;

use App\Models\Api\ApiLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiRequestLogger
{
    private const SENSITIVE_KEYS = ['password', 'token', 'secret', 'key', 'authorization', 'credential'];

    /** Nilai string di atas batas ini (byte) diganti metadata — hindari menyimpan base64 besar */
    private const MAX_VALUE_BYTES = 10_000;

    /** Batas ukuran response body yang disimpan (10 MB) */
    private const MAX_BODY_BYTES = 10_485_760;

    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        $duration = (int) round((microtime(true) - $start) * 1000);

        try {
            $apiToken = $request->attributes->get('api_token');
            $apiUser  = $apiToken?->apiUser;
            $path     = $request->path();

            ApiLog::create([
                'api_user_id'      => $apiUser?->id,
                'api_user_name'    => $apiUser?->name,
                'method'           => $request->method(),
                'path'             => $path,
                'scope'            => $this->detectScope($path),
                'query_string'     => $request->getQueryString() ?: null,
                'ip_address'       => $request->ip(),
                'user_agent'       => $request->userAgent(),
                'request_headers'  => $this->safeRequestHeaders($request),
                'request_body'     => $this->safeRequestBody($request),
                'response_status'  => $response->getStatusCode(),
                'response_time_ms' => $duration,
                'response_body'    => $this->safeResponseBody($response),
            ]);
        } catch (\Throwable) {
            // Jangan gagalkan request hanya karena logging error
        }

        return $response;
    }

    private function detectScope(string $path): ?string
    {
        return match (true) {
            str_contains($path, '/whatsapp') || str_contains($path, '/gowa') => 'whatsapp-gateway',
            str_contains($path, '/simrs')     => 'simrs',
            str_contains($path, '/tte')       => 'tte',
            str_contains($path, '/qrcode')    => 'qrcode',
            str_contains($path, '/worklist') || str_contains($path, '/dicom') => 'dicom',
            str_contains($path, '/ai')        => 'ai',
            str_contains($path, '/hospital')  => 'hospital',
            default => null,
        };
    }

    private function safeRequestHeaders(Request $request): ?array
    {
        $headers = $request->headers->all();
        // Flatten the associative array where values are arrays of strings into strings
        $flatHeaders = [];
        foreach ($headers as $key => $values) {
            $flatHeaders[$key] = is_array($values) ? implode(', ', $values) : $values;
        }

        return $this->scrub($flatHeaders);
    }

    private function safeRequestBody(Request $request): ?array
    {
        $body = $request->isJson()
            ? $request->json()->all()
            : $request->except(['_token']);

        if (empty($body)) {
            return null;
        }

        return $this->scrub($body);
    }

    private function safeResponseBody(Response $response): ?array
    {
        $contentType = $response->headers->get('Content-Type', '');

        // Lewati response binary (PDF, gambar, dll.)
        $binaryTypes = ['application/pdf', 'image/', 'audio/', 'video/', 'application/octet-stream', 'application/zip'];
        foreach ($binaryTypes as $type) {
            if (str_contains($contentType, $type)) {
                return ['_type' => 'binary', '_content_type' => $contentType];
            }
        }

        $content = $response->getContent();

        if (empty($content)) {
            return null;
        }

        // Batasi ukuran — simpan metadata jika terlalu besar
        if (strlen($content) > self::MAX_BODY_BYTES) {
            return ['_truncated' => true, '_size_bytes' => strlen($content), '_content_type' => $contentType];
        }

        // Decode JSON response
        if (str_contains($contentType, 'application/json') || str_contains($contentType, 'text/json')) {
            $decoded = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $this->scrub($decoded);
            }
        }

        // Teks non-JSON (plain text, HTML singkat, dll.)
        return ['_text' => $content];
    }

    private function scrub(array $data): array
    {
        foreach ($data as $key => $value) {
            $lower = strtolower($key);
            foreach (self::SENSITIVE_KEYS as $sensitive) {
                if (str_contains($lower, $sensitive)) {
                    $data[$key] = '[REDACTED]';
                    break;
                }
            }

            if (isset($data[$key]) && $data[$key] === '[REDACTED]') {
                continue;
            }

            // Ganti string panjang (mis. base64 gambar/PDF) dengan metadata ukuran
            if (is_string($value) && strlen($value) > self::MAX_VALUE_BYTES) {
                $data[$key] = '[TRUNCATED:' . strlen($value) . 'B]';
                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->scrub($value);
            }
        }

        return $data;
    }
}
