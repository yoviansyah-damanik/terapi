<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SatuSehatService
{
    /** Cek apakah konfigurasi SatuSehat sudah lengkap */
    public function isConfigured(): bool
    {
        return !empty(config('satusehat.auth_url'))
            && !empty(config('satusehat.fhir_url'))
            && !empty(config('satusehat.client_id'))
            && !empty(config('satusehat.client_secret'));
    }

    /**
     * Tes Get Token (OAuth2 client_credentials).
     *
     * @return array{success: bool, message: string, response_time: float|null, http_status: int|null, token: string|null}
     */
    public function testToken(): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Konfigurasi belum lengkap (client_id / client_secret)',
                'response_time' => null,
                'http_status' => null,
                'token' => null,
            ];
        }

        $url = rtrim(config('satusehat.auth_url'), '/') . '/accesstoken?grant_type=client_credentials';
        $startTime = microtime(true);

        try {
            $response = Http::asForm()
                ->timeout(15)
                ->post($url, [
                    'client_id' => config('satusehat.client_id'),
                    'client_secret' => config('satusehat.client_secret'),
                ]);

            $responseTime = round((microtime(true) - $startTime) * 1000);
            $body = $response->json();

            $token = $body['access_token'] ?? null;
            $isSuccess = $response->successful() && $token;

            return [
                'success' => $isSuccess,
                'message' => $isSuccess
                    ? 'Token berhasil didapatkan (expires: ' . ($body['expires_in'] ?? '?') . 's)'
                    : ($body['error_description'] ?? $body['issue'][0]['diagnostics'] ?? 'Gagal mendapatkan token'),
                'response_time' => $responseTime,
                'http_status' => $response->status(),
                'token' => $token,
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return [
                'success' => false,
                'message' => 'Tidak dapat terhubung: ' . $e->getMessage(),
                'response_time' => round((microtime(true) - $startTime) * 1000),
                'http_status' => null,
                'token' => null,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'response_time' => round((microtime(true) - $startTime) * 1000),
                'http_status' => null,
                'token' => null,
            ];
        }
    }

    /**
     * Tes koneksi ke FHIR Resource endpoint (GET /{ResourceType}?_count=1).
     *
     * @return array{success: bool, message: string, response_time: float|null, http_status: int|null}
     */
    public function testResource(string $resourceType, string $token): array
    {
        $url = rtrim(config('satusehat.fhir_url'), '/') . '/' . $resourceType;
        $startTime = microtime(true);

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(15)
                ->get($url, ['_count' => 1]);

            $responseTime = round((microtime(true) - $startTime) * 1000);
            $body = $response->json();

            $isSuccess = $response->status() > 0;
            $resourceCount = $body['total'] ?? null;

            $message = match (true) {
                $response->successful() && $resourceCount !== null => "OK ({$resourceCount} data)",
                $response->successful() => 'Terhubung',
                default => $body['issue'][0]['diagnostics'] ?? ('HTTP ' . $response->status()),
            };

            return [
                'success' => $isSuccess,
                'message' => $message,
                'response_time' => $responseTime,
                'http_status' => $response->status(),
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return [
                'success' => false,
                'message' => 'Tidak dapat terhubung: ' . $e->getMessage(),
                'response_time' => round((microtime(true) - $startTime) * 1000),
                'http_status' => null,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'response_time' => round((microtime(true) - $startTime) * 1000),
                'http_status' => null,
            ];
        }
    }
}
