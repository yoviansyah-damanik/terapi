<?php

namespace App\Services\Bpjs;

use App\Helpers\ConfigurationHelper;
use Illuminate\Support\Facades\Http;

class AplicareService extends BpjsBaseService
{
    protected string $module = 'aplicare';

    public function isConfigured(): bool
    {
        return !empty($this->baseUrl())
            && !empty($this->consId())
            && !empty($this->secretKey())
            && !empty($this->userKey())
            && !empty($this->kodePpk());
    }

    public function kodePpk(): string
    {
        return ConfigurationHelper::get('bpjs.kode_ppk') ?? (string) config('bpjs.kode_ppk', '');
    }

    private function wrap(callable $fn): array
    {
        try {
            $response = $fn();
            $data = $response->json();
            return [
                'success'     => $response->successful(),
                'http_status' => $response->status(),
                'data'        => $data,
                'message'     => $response->successful() ? 'Berhasil' : ($data['message'] ?? 'Gagal'),
            ];
        } catch (\Throwable $e) {
            return [
                'success'     => false,
                'http_status' => null,
                'data'        => null,
                'message'     => $e->getMessage(),
            ];
        }
    }

    /** GET /rest/bed/read/{kodePpk}/{start}/{end} */
    public function getBeds(int $start = 1, int $end = 1000): array
    {
        return $this->wrap(fn() => Http::withHeaders($this->headers())
            ->timeout(30)
            ->get($this->baseUrl() . "/rest/bed/read/{$this->kodePpk()}/{$start}/{$end}"));
    }

    /** POST /rest/bed/create/{kodePpk} */
    public function createBed(array $data): array
    {
        return $this->wrap(fn() => Http::withHeaders($this->headers())
            ->timeout(30)
            ->post($this->baseUrl() . "/rest/bed/create/{$this->kodePpk()}", $data));
    }

    /** POST /rest/bed/update/{kodePpk} */
    public function updateBed(array $data): array
    {
        return $this->wrap(fn() => Http::withHeaders($this->headers())
            ->timeout(30)
            ->post($this->baseUrl() . "/rest/bed/update/{$this->kodePpk()}", $data));
    }

    /** GET /rest/ref/kelas */
    public function getKelas(): array
    {
        return $this->wrap(fn() => Http::withHeaders($this->headers())
            ->timeout(30)
            ->get($this->baseUrl() . '/rest/ref/kelas'));
    }

    /** POST /rest/bed/delete/{kodePpk} */
    public function deleteBed(array $data): array
    {
        return $this->wrap(fn() => Http::withHeaders($this->headers())
            ->timeout(30)
            ->post($this->baseUrl() . "/rest/bed/delete/{$this->kodePpk()}", $data));
    }
}
