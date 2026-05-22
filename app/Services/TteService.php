<?php

namespace App\Services;

use App\Helpers\ConfigurationHelper;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TteService
{
    protected string $baseUrl;
    protected string $username;
    protected string $password;

    public function __construct()
    {
        $this->baseUrl = rtrim(ConfigurationHelper::get('tte.base_url') ?? config('services.tte.base_url', ''), '/');
        $this->username = ConfigurationHelper::get('tte.username') ?? config('services.tte.username', '');
        $this->password = ConfigurationHelper::get('tte.password') ?? config('services.tte.password', '');
    }

    /**
     * Buat HTTP client dengan Basic Auth ke ESign Client Service
     */
    protected function client(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withBasicAuth($this->username, $this->password)
            ->acceptJson()
            ->timeout(120);
    }

    /**
     * Kirim request ke TTE API dan format response-nya
     */
    protected function sendRequest(string $endpoint, array $payload): array
    {
        try {
            $response = $this->client()->post("{$this->baseUrl}{$endpoint}", $payload);

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            Log::error("TTE API Error [{$endpoint}]: {$e->getMessage()}");

            return [
                'success' => false,
                'message' => 'Gagal terhubung ke server TTE',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Format response dari TTE API menjadi array standar
     */
    protected function formatResponse(Response $response): array
    {
        $body = $response->json() ?? [];

        return [
            'success' => $response->successful(),
            'status_code' => $response->status(),
            'data' => $body,
        ];
    }

    /**
     * Cek status koneksi ke server TTE — mengembalikan reachability, latency, dan status auth
     */
    public function checkConnection(): array
    {
        if (empty($this->baseUrl)) {
            return [
                'connected' => false,
                'authenticated' => false,
                'latency_ms' => null,
                'message' => 'Base URL TTE belum dikonfigurasi.',
            ];
        }

        $start = microtime(true);
        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->acceptJson()
                ->timeout(10)
                ->get($this->baseUrl);

            $latency = (int) round((microtime(true) - $start) * 1000);
            $authenticated = !in_array($response->status(), [401, 403]);

            return [
                'connected' => true,
                'authenticated' => $authenticated,
                'latency_ms' => $latency,
                'status_code' => $response->status(),
                'message' => $authenticated ? 'Koneksi dan autentikasi berhasil.' : 'Server dapat dijangkau namun autentikasi gagal.',
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return [
                'connected' => false,
                'authenticated' => false,
                'latency_ms' => null,
                'message' => 'Tidak dapat terhubung ke server TTE: ' . $e->getMessage(),
            ];
        } catch (\Exception $e) {
            Log::error("TTE connection check error: {$e->getMessage()}");

            return [
                'connected' => false,
                'authenticated' => false,
                'latency_ms' => null,
                'message' => 'Gagal memeriksa koneksi TTE: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Tanda tangan PDF (mendukung visible/invisible, koordinat/tag_koordinat, bulk signing)
     *
     * Identitas: gunakan salah satu dari nik atau email.
     * Kredensial: gunakan salah satu dari passphrase atau totp.
     */
    public function signPdf(array $payload): array
    {
        return $this->sendRequest('/api/v2/sign/pdf', $payload);
    }

    /**
     * Cek status user di sistem TTE (menggunakan NIK atau email)
     */
    public function checkUserStatus(?string $nik = null, ?string $email = null): array
    {
        $payload = array_filter([
            'nik' => $nik,
            'email' => $email,
        ]);

        return $this->sendRequest('/api/v2/user/check/status', $payload);
    }

    /**
     * Registrasi user baru di sistem TTE
     */
    public function registerUser(string $nama, string $email): array
    {
        return $this->sendRequest('/api/v2/user/registration', [
            'nama' => $nama,
            'email' => $email,
        ]);
    }

    /**
     * Verifikasi tanda tangan pada dokumen PDF (file base64 string, opsional password)
     */
    public function verifyPdf(string $file, ?string $password = null): array
    {
        $payload = ['file' => $file];

        if ($password !== null) {
            $payload['password'] = $password;
        }

        return $this->sendRequest('/api/v2/verify/pdf', $payload);
    }

    /**
     * Request TOTP untuk proses tanda tangan (menggunakan NIK atau email)
     *
     * Parameter data = jumlah file yang akan ditandatangani.
     */
    public function requestSignTotp(?string $nik = null, ?string $email = null, int $data = 1): array
    {
        $payload = array_filter([
            'nik' => $nik,
            'email' => $email,
            'data' => $data,
        ]);

        return $this->sendRequest('/api/v2/sign/get/totp', $payload);
    }

    /**
     * Aktivasi TOTP untuk Segel Elektronik (pertama kali)
     */
    public function sealGetActivation(string $idSubscriber): array
    {
        return $this->sendRequest('/api/v2/seal/get/activation', [
            'idSubscriber' => $idSubscriber,
        ]);
    }

    /**
     * Refresh/perpanjang masa berlaku aktivasi seal
     */
    public function sealRefreshActivation(string $idSubscriber, string $totp): array
    {
        return $this->sendRequest('/api/v2/seal/get/activation', [
            'idSubscriber' => $idSubscriber,
            'totp' => $totp,
        ]);
    }

    /**
     * Revoke/cabut aktivasi seal
     */
    public function sealRevokeActivation(string $idSubscriber, string $totp): array
    {
        return $this->sendRequest('/api/v2/seal/revoke/activation', [
            'idSubscriber' => $idSubscriber,
            'totp' => $totp,
        ]);
    }

    /**
     * Request TOTP untuk proses seal
     *
     * Parameter data = jumlah file yang akan disegel.
     * Parameter totp = TOTP dari aktivasi seal.
     */
    public function sealGetTotp(string $idSubscriber, int $data, string $totp): array
    {
        return $this->sendRequest('/api/v2/seal/get/totp', [
            'idSubscriber' => $idSubscriber,
            'data' => $data,
            'totp' => $totp,
        ]);
    }

    /**
     * Segel dokumen PDF (Segel Elektronik instansi)
     */
    public function sealPdf(
        string $idSubscriber,
        string $totp,
        array $signatureProperties,
        array $files,
    ): array {
        return $this->sendRequest('/api/v2/seal/pdf', [
            'idSubscriber' => $idSubscriber,
            'totp' => $totp,
            'signatureProperties' => $signatureProperties,
            'file' => $files,
        ]);
    }
}
