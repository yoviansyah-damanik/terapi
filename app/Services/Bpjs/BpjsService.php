<?php

namespace App\Services\Bpjs;

use Illuminate\Support\Facades\Http;

class BpjsService
{
    /** Daftar key modul BPJS yang tersedia */
    public function getModules(): array
    {
        return ['vclaim', 'antrian_online', 'apotek_online', 'icare', 'erm', 'antrian_rs'];
    }

    /** Ambil konfigurasi modul tertentu */
    public function getModuleConfig(string $module): ?array
    {
        return config("bpjs.{$module}");
    }

    /** Cek apakah konfigurasi modul sudah lengkap */
    public function isConfigured(string $module): bool
    {
        $config = $this->getModuleConfig($module);

        if (!$config) {
            return false;
        }

        // Antrian RS pakai username/password, bukan HMAC
        if ($module === 'antrian_rs') {
            return !empty($config['base_url'])
                && !empty($config['username'])
                && !empty($config['password']);
        }

        return !empty($config['base_url'])
            && !empty($config['cons_id'])
            && !empty($config['secret_key'])
            && !empty($config['user_key']);
    }

    /** Generate header autentikasi BPJS (HMAC-SHA256) */
    public function generateAuthHeaders(string $module): array
    {
        $config = $this->getModuleConfig($module);

        // Antrian RS pakai x-username / x-password
        if ($module === 'antrian_rs') {
            return [
                'x-username' => $config['username'],
                'x-password' => $config['password'],
                'Content-Type' => 'application/json',
            ];
        }

        $consId = $config['cons_id'];
        $secretKey = $config['secret_key'];
        $userKey = $config['user_key'];

        $timestamp = time();
        $signature = base64_encode(
            hash_hmac('sha256', $consId . '&' . $timestamp, $secretKey, true)
        );

        return [
            'X-cons-id' => $consId,
            'X-timestamp' => $timestamp,
            'X-signature' => $signature,
            'user_key' => $userKey,
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Tes koneksi ke satu endpoint BPJS.
     *
     * @param  string       $module        Key modul (vclaim, antrian_online, dll)
     * @param  string       $httpMethod    HTTP method (get, post, delete)
     * @param  string       $path          Path endpoint yang ditest
     * @param  string|null  $antrianRsToken Token untuk Antrian RS (selain /auth)
     * @return array{success: bool, message: string, response_time: float|null, http_status: int|null, meta_code: string|null, token: string|null}
     */
    public function testEndpoint(string $module, string $httpMethod, string $path, ?string $antrianRsToken = null): array
    {
        if (!$this->isConfigured($module)) {
            return [
                'success' => false,
                'message' => 'Konfigurasi belum lengkap',
                'response_time' => null,
                'http_status' => null,
                'meta_code' => null,
                'token' => null,
            ];
        }

        $config = $this->getModuleConfig($module);
        $url = rtrim($config['base_url']) . $path;

        // Tentukan header berdasarkan modul
        if ($module === 'antrian_rs' && $antrianRsToken) {
            // Endpoint Antrian RS selain /auth → pakai token
            $headers = [
                'x-token' => $antrianRsToken,
                'x-username' => $config['username'],
                'Content-Type' => 'application/json',
            ];
        } else {
            $headers = $this->generateAuthHeaders($module);
        }

        $httpMethod = strtolower($httpMethod);
        $startTime = microtime(true);

        try {
            $response = Http::withHeaders($headers)
                        ->timeout(15)
                ->$httpMethod($url, $httpMethod !== 'get' ? new \stdClass : null);

            $responseTime = round((microtime(true) - $startTime) * 1000);
            $body = $response->json();
            $metaCode = $body['metaData']['code'] ?? $body['metadata']['code'] ?? ($body['code'] ?? null);
            $metaMessage = $body['metaData']['message'] ?? $body['metadata']['message'] ?? ($body['message'] ?? null);

            // Berhasil terhubung = mendapat respons HTTP dari server BPJS
            $isConnected = $response->status() > 0;

            // Ekstrak token dari respons Get Token Antrian RS
            $token = $body['response']['token'] ?? null;

            return [
                'success' => $isConnected,
                'message' => $metaMessage ?? ($isConnected ? 'Terhubung' : 'Tidak ada respons'),
                'response_time' => $responseTime,
                'http_status' => $response->status(),
                'meta_code' => (string) ($metaCode ?? ''),
                'token' => $token,
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $responseTime = round((microtime(true) - $startTime) * 1000);

            return [
                'success' => false,
                'message' => 'Tidak dapat terhubung: ' . $e->getMessage(),
                'response_time' => $responseTime,
                'http_status' => null,
                'meta_code' => null,
                'token' => null,
            ];
        } catch (\Exception $e) {
            $responseTime = round((microtime(true) - $startTime) * 1000);

            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'response_time' => $responseTime,
                'http_status' => null,
                'meta_code' => null,
                'token' => null,
            ];
        }
    }

    /**
     * Kirim eRM Bundle ke BPJS
     *
     * @param  array  $bundle  FHIR Bundle data
     * @return array{success: bool, message: string, response: array|null, http_status: int|null}
     */
    public function sendErm(array $bundle): array
    {
        if (!$this->isConfigured('erm')) {
            return [
                'success' => false,
                'message' => 'Konfigurasi eRM belum lengkap',
                'response' => null,
                'http_status' => null,
            ];
        }

        $config = $this->getModuleConfig('erm');
        $url = rtrim($config['base_url']) . '/eclaim/rekammedis/insert';
        $headers = $this->generateAuthHeaders('erm');

        try {
            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->post($url, $bundle);

            $body = $response->json();
            $metaCode = $body['metaData']['code'] ?? $body['metadata']['code'] ?? ($body['code'] ?? null);
            $metaMessage = $body['metaData']['message'] ?? $body['metadata']['message'] ?? ($body['message'] ?? 'Unknown error');

            $isSuccess = $response->successful() && in_array($metaCode, ['200', 200]);

            return [
                'success' => $isSuccess,
                'message' => $metaMessage,
                'response' => $body,
                'http_status' => $response->status(),
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return [
                'success' => false,
                'message' => 'Tidak dapat terhubung ke server BPJS: ' . $e->getMessage(),
                'response' => null,
                'http_status' => null,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'response' => null,
                'http_status' => null,
            ];
        }
    }
}
