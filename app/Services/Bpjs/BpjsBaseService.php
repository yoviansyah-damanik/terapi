<?php

namespace App\Services\Bpjs;

use App\Helpers\ConfigurationHelper;
use App\Helpers\LZString;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

abstract class BpjsBaseService
{
    protected string $module;

    protected int $timestamp;

    protected int $requestTimeout = 30;

    public function __construct()
    {
        $this->timestamp = time();
    }

    /** Set timeout per-request (dalam detik). Mengembalikan $this untuk chaining. */
    public function withTimeout(int $seconds): static
    {
        $this->requestTimeout = $seconds;
        return $this;
    }

    protected function config(?string $key = null): mixed
    {
        if ($key) {
            return ConfigurationHelper::get("bpjs.{$this->module}.{$key}")
                ?? config("bpjs.{$this->module}.{$key}");
        }

        return config("bpjs.{$this->module}");
    }

    public function baseUrl(): string
    {
        return rtrim($this->config('base_url') ?? '', '/ ');
    }

    protected function consId(): string
    {
        return (string) $this->config('cons_id');
    }

    protected function secretKey(): string
    {
        return (string) $this->config('secret_key');
    }

    protected function userKey(): string
    {
        return (string) $this->config('user_key');
    }

    /** Generate header HMAC-SHA256 untuk autentikasi BPJS */
    protected function headers(?array $headers = null): array
    {
        $this->timestamp = time();
        $signature = base64_encode(
            hash_hmac('sha256', $this->consId() . '&' . $this->timestamp, $this->secretKey(), true)
        );

        return [
            'X-cons-id' => $this->consId(),
            'X-timestamp' => (string) $this->timestamp,
            'X-signature' => $signature,
            'user_key' => $this->userKey(),
            'Content-Type' => ($headers['Content-Type'] ?? null) ?: 'application/json',
        ];
    }

    /** Dekripsi response BPJS menggunakan AES-256-CBC */
    protected function decrypt(string $encrypted): ?string
    {
        $key = $this->consId() . $this->secretKey() . $this->timestamp;
        $keyHash = hex2bin(hash('sha256', $key));
        $iv = substr($keyHash, 0, 16);

        $decrypted = openssl_decrypt(
            base64_decode($encrypted),
            'aes-256-cbc',
            $keyHash,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($decrypted === false) {
            return null;
        }

        return $decrypted;
    }

    /** Dekripsi dan dekompresi response BPJS (untuk VClaim dll yang pakai LZString) */
    protected function decryptAndDecompress(string $encrypted): ?string
    {
        $decrypted = $this->decrypt($encrypted);

        if ($decrypted === null) {
            return null;
        }

        // Coba JSON decode langsung
        $json = json_decode($decrypted, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decrypted;
        }

        // Jika gagal, coba dekompresi LZString
        return LZString::decompressFromEncodedURIComponent($decrypted);
    }

    /** Parse response BPJS: cek metaData, dekripsi response jika ada */
    protected function parseResponse(Response $response, bool $decompress = true): array
    {
        $body = $response->json();

        $metaCode = $body['metaData']['code'] ?? $body['metadata']['code'] ?? null;
        $metaMessage = $body['metaData']['message'] ?? $body['metadata']['message'] ?? null;

        $responseData = $body['response'] ?? null;

        // Dekripsi response jika berupa string (terenkripsi)
        if (is_string($responseData) && !empty($responseData)) {
            $decrypted = $decompress
                ? $this->decryptAndDecompress($responseData)
                : $this->decrypt($responseData);

            if ($decrypted !== null) {
                $responseData = json_decode($decrypted, true) ?? $decrypted;
            }
        }

        return [
            'code' => (string) ($metaCode ?? $response->status()),
            'message' => $metaMessage ?? '',
            'response' => $responseData,
        ];
    }

    /** Kirim GET request ke endpoint BPJS */
    protected function get(string $endpoint): array
    {
        $url = $this->baseUrl() . $endpoint;

        $response = Http::withHeaders($this->headers())
            ->timeout($this->requestTimeout)
            ->get($url);

        return $this->parseResponse($response);
    }

    /** Kirim POST request ke endpoint BPJS */
    protected function post(string $endpoint, array $data = [], ?array $headers = null): array
    {
        $url = $this->baseUrl() . $endpoint;

        $response = Http::withHeaders($this->headers($headers))
            ->timeout($this->requestTimeout)
            ->post($url, $data);

        return $this->parseResponse($response);
    }

    /** Kirim PUT request ke endpoint BPJS */
    protected function put(string $endpoint, array $data = []): array
    {
        $url = $this->baseUrl() . $endpoint;

        $response = Http::withHeaders($this->headers())
            ->timeout($this->requestTimeout)
            ->put($url, $data);

        return $this->parseResponse($response);
    }

    /** Kirim DELETE request ke endpoint BPJS */
    protected function delete(string $endpoint, array $data = []): array
    {
        $url = $this->baseUrl() . $endpoint;

        $response = Http::withHeaders($this->headers())
            ->timeout($this->requestTimeout)
            ->delete($url, $data);

        return $this->parseResponse($response);
    }
}
