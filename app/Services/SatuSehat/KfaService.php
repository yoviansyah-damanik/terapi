<?php

namespace App\Services\SatuSehat;

use App\Services\SatuSehat\Concerns\HasAuthentication;
use App\Services\TerminologyCacheService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service untuk mengakses API KFA (Kamus Farmasi dan Alat Kesehatan) Satu Sehat.
 * Mendukung KFA Versi 2 (farmasi) dan KFA Versi 3 (alat kesehatan).
 *
 * Base URL diambil dari satusehat.base_url (domain yang sama dengan FHIR API).
 */
class KfaService
{
    use HasAuthentication;

    protected array $lastRequestInfo  = [];
    protected array $lastResponseInfo = [];

    public function getLastRequestInfo(): array  { return $this->lastRequestInfo; }
    public function getLastResponseInfo(): array { return $this->lastResponseInfo; }

    protected function authorizedGet(string $path, array $query = []): Response
    {
        $url = $this->getBaseUrl() . $path;

        $this->lastRequestInfo = [
            'method' => 'GET',
            'url'    => $url . ($query ? '?' . http_build_query($query) : ''),
            'params' => $query ?: null,
            'body'   => null,
        ];

        $response = Http::withToken($this->getAccessToken())
            ->timeout($this->getTimeout())
            ->get($url, $query);

        $this->lastResponseInfo = [
            'status' => $response->status(),
            'body'   => $response->json() ?? $response->body(),
        ];

        return $response;
    }

    protected function authorizedPost(string $path, array $body = []): Response
    {
        $url = $this->getBaseUrl() . $path;

        $this->lastRequestInfo = [
            'method' => 'POST',
            'url'    => $url,
            'params' => null,
            'body'   => $body ?: null,
        ];

        $response = Http::withToken($this->getAccessToken())
            ->timeout($this->getTimeout())
            ->asJson()
            ->post($url, $body);

        $this->lastResponseInfo = [
            'status' => $response->status(),
            'body'   => $response->json() ?? $response->body(),
        ];

        return $response;
    }

    // ------------------------------------------------------------------ //
    //  KFA v2 — Farmasi
    // ------------------------------------------------------------------ //

    /**
     * Cari produk farmasi dengan pagination.
     *
     * @param  string  $keyword   Kata kunci pencarian (fuzzy)
     * @param  int     $page      Halaman (mulai dari 1)
     * @param  int     $size      Jumlah item per halaman
     * @param  string  $fromDate  Filter tanggal mulai (YYYY-MM-DD)
     * @param  string  $toDate    Filter tanggal akhir (YYYY-MM-DD)
     */
    public function searchFarmasi(
        string $keyword = '',
        int $page = 1,
        int $size = 10,
        string $fromDate = '',
        string $toDate = '',
    ): array {
        $cacheKey = TerminologyCacheService::kfaKey('farmasi', compact('keyword', 'page', 'size', 'fromDate', 'toDate'));

        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        try {
            $query = array_filter([
                'page'         => $page,
                'size'         => $size,
                'product_type' => 'farmasi',
                'keyword'      => $keyword ?: null,
                'from_date'    => $fromDate ?: null,
                'to_date'      => $toDate ?: null,
            ]);

            $response = $this->authorizedGet('/kfa-v2/products/all', $query);

            if ($response->successful()) {
                $data = $response->json() ?? [];
                Cache::put($cacheKey, $data, TerminologyCacheService::TTL_KFA);
                return $data;
            }

            Log::warning('KFA Farmasi: response gagal', ['status' => $response->status()]);
            return ['total' => 0, 'page' => $page, 'size' => $size, 'items' => ['data' => []]];
        } catch (\Exception $e) {
            Log::error('KFA Farmasi error: ' . $e->getMessage());
            return ['total' => 0, 'page' => $page, 'size' => $size, 'items' => ['data' => []]];
        }
    }

    /**
     * Ambil detail produk berdasarkan kode KFA / NIE / LKPP.
     *
     * @param  string  $code        Kode produk
     * @param  string  $identifier  Sumber: 'kfa' | 'nie' | 'lkpp'
     */
    public function getFarmasiDetail(string $code, string $identifier = 'kfa'): ?array
    {
        try {
            $response = $this->authorizedGet('/kfa-v2/products', [
                'identifier' => $identifier,
                'code'       => $code,
            ]);

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('KFA Farmasi detail error: ' . $e->getMessage());
            return null;
        }
    }

    // ------------------------------------------------------------------ //
    //  KFA v3 — Alat Kesehatan
    // ------------------------------------------------------------------ //

    /**
     * Cari varian produk alat kesehatan.
     *
     * @param  string    $search        Kata kunci fuzzy
     * @param  int       $page          Halaman (mulai dari 1)
     * @param  int       $size          Jumlah item per halaman
     * @param  bool|null $active        Filter status aktif
     * @param  string    $categoryCode  Kode kategori level 1
     */
    public function searchAlkesProducts(
        string $search = '',
        int $page = 1,
        int $size = 10,
        ?bool $active = null,
        string $categoryCode = '',
    ): array {
        $cacheKey = TerminologyCacheService::kfaKey('alkes_products', compact('search', 'page', 'size', 'active', 'categoryCode'));

        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        try {
            $body = array_filter([
                'page'          => $page,
                'size'          => $size,
                'search'        => $search ?: null,
                'active'        => $active,
                'category_code' => $categoryCode ?: null,
                'state'         => 'valid',
            ], fn($v) => $v !== null && $v !== '');

            $response = $this->authorizedPost('/kfa-v3/alkes/products', $body);

            if ($response->successful()) {
                $data = $response->json() ?? [];
                Cache::put($cacheKey, $data, TerminologyCacheService::TTL_KFA);
                return $data;
            }

            Log::warning('KFA Alkes Products: response gagal', ['status' => $response->status()]);
            return $this->emptyAlkesResponse($page);
        } catch (\Exception $e) {
            Log::error('KFA Alkes Products error: ' . $e->getMessage());
            return $this->emptyAlkesResponse($page);
        }
    }

    /**
     * Cari template produk alat kesehatan.
     *
     * @param  string  $search        Kata kunci fuzzy
     * @param  int     $page          Halaman (mulai dari 1)
     * @param  int     $size          Jumlah item per halaman
     * @param  string  $categoryCode  Kode kategori level 1
     */
    public function searchAlkesTemplates(
        string $search = '',
        int $page = 1,
        int $size = 10,
        string $categoryCode = '',
    ): array {
        $cacheKey = TerminologyCacheService::kfaKey('alkes_templates', compact('search', 'page', 'size', 'categoryCode'));

        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        try {
            $body = array_filter([
                'page'          => $page,
                'size'          => $size,
                'search'        => $search ?: null,
                'category_code' => $categoryCode ?: null,
                'state'         => 'valid',
            ], fn($v) => $v !== null && $v !== '');

            $response = $this->authorizedPost('/kfa-v3/alkes/template', $body);

            if ($response->successful()) {
                $data = $response->json() ?? [];
                Cache::put($cacheKey, $data, TerminologyCacheService::TTL_KFA);
                return $data;
            }

            Log::warning('KFA Alkes Templates: response gagal', ['status' => $response->status()]);
            return $this->emptyAlkesResponse($page);
        } catch (\Exception $e) {
            Log::error('KFA Alkes Templates error: ' . $e->getMessage());
            return $this->emptyAlkesResponse($page);
        }
    }

    private function emptyAlkesResponse(int $page): array
    {
        return ['status' => 200, 'error' => false, 'meta' => ['item_count' => 0, 'page' => ['current' => $page, 'total' => 0], 'data' => []]];
    }
}
