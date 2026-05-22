<?php

namespace App\Services\Bpjs;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AntrianRsService extends BpjsBaseService
{
    protected string $module = 'antrian_rs';

    /** Override: Antrian RS tidak pakai HMAC */
    protected function headers(): array
    {
        return [
            'x-username' => $this->config('username'),
            'x-password' => $this->config('password'),
            'Content-Type' => 'application/json',
        ];
    }

    /** Header untuk endpoint yang butuh token */
    protected function tokenHeaders(): array
    {
        return [
            'x-token' => $this->getToken(),
            'x-username' => $this->config('username'),
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Ambil token autentikasi. Disimpan di cache selama 12 jam.
     * POST /auth
     */
    public function getToken(): ?string
    {
        return Cache::remember('bpjs_antrian_rs_token', 43200, function () {
            $url = $this->baseUrl() . '/auth';

            $response = Http::withHeaders($this->headers())
                ->timeout(30)
                ->post($url);

            $body = $response->json();
            return $body['response']['token'] ?? null;
        });
    }

    /** Hapus token dari cache agar di-refresh pada request berikutnya */
    public function forgetToken(): void
    {
        Cache::forget('bpjs_antrian_rs_token');
    }

    /**
     * Ambil data antrian.
     * POST /ambilantrean
     */
    public function ambilAntrian(array $data): array
    {
        $url = $this->baseUrl() . '/ambilantrean';

        $response = Http::withHeaders($this->tokenHeaders())
            ->timeout(30)
            ->post($url, $data);

        return $this->parseResponse($response);
    }

    /**
     * Check in antrian.
     * POST /checkinantrean
     */
    public function checkIn(array $data): array
    {
        $url = $this->baseUrl() . '/checkinantrean';

        $response = Http::withHeaders($this->tokenHeaders())
            ->timeout(30)
            ->post($url, $data);

        return $this->parseResponse($response);
    }

    /**
     * Jadwal operasi RS.
     * POST /jadwaloperasirs
     */
    public function jadwalOperasi(array $data): array
    {
        $url = $this->baseUrl() . '/jadwaloperasirs';

        $response = Http::withHeaders($this->tokenHeaders())
            ->timeout(30)
            ->post($url, $data);

        return $this->parseResponse($response);
    }
}
