<?php

namespace App\Services\RsOnline;

use App\Helpers\ConfigurationHelper;
use Illuminate\Support\Facades\Http;

class RsOnlineService
{
    private string $baseUrl;
    private string $rsId;
    private string $password;

    /** Daftar tipe referensi yang tersedia */
    public const REFERENSI_TYPES = [
        'status_rawat'    => 'Status Rawat',
        'status_isolasi'  => 'Status Isolasi',
        'sebab_penularan' => 'Sumber Penularan',
        'status_keluar'   => 'Status Keluar',
        'kewarganegaraan' => 'Kewarganegaraan',
        'gender'          => 'Gender',
        'propinsi'        => 'Provinsi',
        'Kabupaten'       => 'Kabupaten/Kota',
        'Kecamatan'       => 'Kecamatan',
        'kebutuhan_sdm'   => 'Kebutuhan SDM',
        'kebutuhan_apd'   => 'Kebutuhan APD',
    ];

    public function __construct()
    {
        $this->baseUrl  = rtrim(ConfigurationHelper::get('rsonline.base_url', ''), '/');
        $this->rsId     = ConfigurationHelper::get('rsonline.rs_id', '');
        $this->password = ConfigurationHelper::get('rsonline.password', '');
    }

    /** Cek apakah konfigurasi sudah lengkap */
    public function isConfigured(): bool
    {
        return !empty($this->baseUrl) && !empty($this->rsId) && !empty($this->password);
    }

    /** Header autentikasi RS Online */
    private function headers(): array
    {
        return [
            'X-rs-id'     => $this->rsId,
            'X-pass'      => md5($this->password),
            'X-Timestamp' => (string) time(),
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];
    }

    /** Bangun URL endpoint */
    private function url(string $path): string
    {
        return $this->baseUrl . '/fo/index.php/' . ltrim($path, '/');
    }

    /** Wrapper response — normalisasi ke array standar */
    private function wrap(callable $fn): array
    {
        $start = microtime(true);

        try {
            $response = $fn();
            $responseTime = (int) round((microtime(true) - $start) * 1000);

            return [
                'success'       => $response->successful(),
                'http_status'   => $response->status(),
                'response_time' => $responseTime,
                'data'          => $response->json() ?? $response->body(),
                'message'       => $response->successful() ? 'Berhasil' : ($response->json('message') ?? 'Gagal'),
            ];
        } catch (\Throwable $e) {
            return [
                'success'       => false,
                'http_status'   => null,
                'response_time' => (int) round((microtime(true) - $start) * 1000),
                'data'          => null,
                'message'       => $e->getMessage(),
            ];
        }
    }

    /** Test koneksi ke API RS Online */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Konfigurasi belum lengkap. Isi Base URL, RS ID, dan Password.',
            ];
        }

        return $this->wrap(fn () => Http::withHeaders($this->headers())
            ->timeout(10)
            ->get($this->url('Referensi/status_rawat')));
    }

    // ===================== Referensi =====================

    /** Ambil data master referensi berdasarkan tipe */
    public function getReferensi(string $type): array
    {
        return $this->wrap(fn () => Http::withHeaders($this->headers())
            ->timeout(15)
            ->get($this->url("Referensi/{$type}")));
    }

    // ===================== Pasien =====================

    /** GET data pasien */
    public function getPasien(): array
    {
        return $this->wrap(fn () => Http::withHeaders($this->headers())
            ->timeout(30)
            ->get($this->url('Pasien')));
    }

    /** POST kirim data pasien baru */
    public function kirimPasien(array $data): array
    {
        return $this->wrap(fn () => Http::withHeaders($this->headers())
            ->timeout(30)
            ->post($this->url('Pasien'), $data));
    }

    /** PUT update data pasien */
    public function updatePasien(array $data): array
    {
        return $this->wrap(fn () => Http::withHeaders($this->headers())
            ->timeout(30)
            ->put($this->url('Pasien'), $data));
    }

    /** DELETE hapus data pasien */
    public function deletePasien(array $data): array
    {
        return $this->wrap(fn () => Http::withHeaders($this->headers())
            ->timeout(30)
            ->delete($this->url('Pasien'), $data));
    }

    // ===================== Diagnosis =====================

    /** GET data diagnosis */
    public function getDiagnosis(): array
    {
        return $this->wrap(fn () => Http::withHeaders($this->headers())
            ->timeout(30)
            ->get($this->url('Pasien/diagnosis')));
    }

    /** POST kirim diagnosis */
    public function kirimDiagnosis(array $data): array
    {
        return $this->wrap(fn () => Http::withHeaders($this->headers())
            ->timeout(30)
            ->post($this->url('Pasien/diagnosis'), $data));
    }

    /** PUT update diagnosis */
    public function updateDiagnosis(array $data): array
    {
        return $this->wrap(fn () => Http::withHeaders($this->headers())
            ->timeout(30)
            ->put($this->url('Pasien/diagnosis'), $data));
    }

    /** DELETE hapus diagnosis */
    public function deleteDiagnosis(array $data): array
    {
        return $this->wrap(fn () => Http::withHeaders($this->headers())
            ->timeout(30)
            ->delete($this->url('Pasien/diagnosis'), $data));
    }

    // ===================== Fasyankes (Tempat Tidur) =====================

    /** GET data tempat tidur */
    public function getFasyankes(): array
    {
        return $this->wrap(fn () => Http::withHeaders($this->headers())
            ->timeout(30)
            ->get($this->url('Fasyankes')));
    }

    /** POST kirim data tempat tidur */
    public function kirimFasyankes(array $data): array
    {
        return $this->wrap(fn () => Http::withHeaders($this->headers())
            ->timeout(30)
            ->post($this->url('Fasyankes'), $data));
    }

    /** PUT update data tempat tidur */
    public function updateFasyankes(array $data): array
    {
        return $this->wrap(fn () => Http::withHeaders($this->headers())
            ->timeout(30)
            ->put($this->url('Fasyankes'), $data));
    }

    /** DELETE hapus data tempat tidur */
    public function deleteFasyankes(array $data): array
    {
        return $this->wrap(fn () => Http::withHeaders($this->headers())
            ->timeout(30)
            ->delete($this->url('Fasyankes'), $data));
    }

    // ===================== SDM =====================

    /** GET data SDM */
    public function getSdm(): array
    {
        return $this->wrap(fn () => Http::withHeaders($this->headers())
            ->timeout(30)
            ->get($this->url('Fasyankes/sdm')));
    }

    /** POST kirim data SDM */
    public function kirimSdm(array $data): array
    {
        return $this->wrap(fn () => Http::withHeaders($this->headers())
            ->timeout(30)
            ->post($this->url('Fasyankes/sdm'), $data));
    }

    /** PUT update data SDM */
    public function updateSdm(array $data): array
    {
        return $this->wrap(fn () => Http::withHeaders($this->headers())
            ->timeout(30)
            ->put($this->url('Fasyankes/sdm'), $data));
    }

    /** DELETE hapus data SDM */
    public function deleteSdm(array $data): array
    {
        return $this->wrap(fn () => Http::withHeaders($this->headers())
            ->timeout(30)
            ->delete($this->url('Fasyankes/sdm'), $data));
    }

    // ===================== APD / Alkes =====================

    /** GET data APD */
    public function getApd(): array
    {
        return $this->wrap(fn () => Http::withHeaders($this->headers())
            ->timeout(30)
            ->get($this->url('Fasyankes/apd')));
    }

    /** POST kirim data APD */
    public function kirimApd(array $data): array
    {
        return $this->wrap(fn () => Http::withHeaders($this->headers())
            ->timeout(30)
            ->post($this->url('Fasyankes/apd'), $data));
    }

    /** PUT update data APD */
    public function updateApd(array $data): array
    {
        return $this->wrap(fn () => Http::withHeaders($this->headers())
            ->timeout(30)
            ->put($this->url('Fasyankes/apd'), $data));
    }

    /** DELETE hapus data APD */
    public function deleteApd(array $data): array
    {
        return $this->wrap(fn () => Http::withHeaders($this->headers())
            ->timeout(30)
            ->delete($this->url('Fasyankes/apd'), $data));
    }
}
