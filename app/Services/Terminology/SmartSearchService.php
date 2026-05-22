<?php

namespace App\Services\Terminology;

use App\Models\Terminology\Icd10;
use App\Models\Terminology\Icd9;
use App\Models\Terminology\IcdOTopography;
use App\Models\Terminology\IcdOMorphology;
use App\Models\Terminology\IcdPm;
use App\Models\Terminology\IcdMm;
use App\Models\Terminology\Loinc;
use App\Models\FhirDictionary;
use App\Services\Snomed\SnowstormService;
use App\Services\SatuSehat\KfaService;
use Illuminate\Support\Facades\Log;

class SmartSearchService
{
    /**
     * Cari query ke semua sumber terminologi yang dipilih.
     *
     * @param  string  $query    Kata kunci pencarian
     * @param  array   $sources  Sumber yang dipilih (subset dari semua sumber)
     * @param  int     $limit    Maksimum hasil per sumber
     * @return array  Keyed by source slug
     */
    public function searchAll(string $query, array $sources, int $limit = 5, ?string $scoreRef = null): array
    {
        $results = [];
        $ref = $scoreRef ?? $query; // Gunakan scoreRef dari AI jika tersedia

        foreach ($sources as $source) {
            $raw = match ($source) {
                'icd10' => $this->searchIcd10($query, 50),
                'icd9' => $this->searchIcd9($query, 50),
                'icd_o_topography' => $this->searchIcdOTopography($query, 50),
                'icd_o_morphology' => $this->searchIcdOMorphology($query, 50),
                'icd_pm' => $this->searchIcdPm($query, 50),
                'icd_mm' => $this->searchIcdMm($query, 50),
                'loinc' => $this->searchLoinc($query, 50),
                'hl7' => $this->searchHl7($query, 50),
                'kemkes' => $this->searchKemkes($query, 50),
                'snomed' => $this->searchSnomed($query, $limit),
                'kfa' => $this->searchKfa($query, 50),
                default => ['items' => [], 'error' => 'Sumber tidak dikenal'],
            };

            // Score menggunakan referensi term medis yang sudah dinormalisasi AI
            if (!empty($raw['items'])) {
                foreach ($raw['items'] as &$item) {
                    $item['score'] = \App\Helpers\StringHelper::calculateSimilarityScore($ref, $item['display']);
                }

                usort($raw['items'], fn($a, $b) => $b['score'] <=> $a['score']);
                $raw['items'] = array_slice($raw['items'], 0, $limit);
            }

            $results[$source] = $raw;
        }

        return $results;
    }

    private function searchIcd10(string $query, int $limit): array
    {
        try {
            $items = Icd10::where('code', 'like', "%{$query}%")
                ->orWhere('display', 'like', "%{$query}%")
                ->limit($limit)
                ->get(['code', 'display'])
                ->map(fn($r) => ['code' => $r->code, 'display' => $r->display])
                ->toArray();

            return ['items' => $items];
        } catch (\Exception $e) {
            Log::warning('SmartSearch ICD-10 error: ' . $e->getMessage());
            return ['items' => [], 'error' => $e->getMessage()];
        }
    }

    private function searchIcd9(string $query, int $limit): array
    {
        try {
            $items = Icd9::where('code', 'like', "%{$query}%")
                ->orWhere('display', 'like', "%{$query}%")
                ->limit($limit)
                ->get(['code', 'display'])
                ->map(fn($r) => ['code' => $r->code, 'display' => $r->display])
                ->toArray();

            return ['items' => $items];
        } catch (\Exception $e) {
            Log::warning('SmartSearch ICD-9 error: ' . $e->getMessage());
            return ['items' => [], 'error' => $e->getMessage()];
        }
    }

    private function searchIcdOTopography(string $query, int $limit): array
    {
        try {
            $items = IcdOTopography::where('code', 'like', "%{$query}%")
                ->orWhere('display', 'like', "%{$query}%")
                ->limit($limit)
                ->get(['code', 'display'])
                ->map(fn($r) => ['code' => $r->code, 'display' => $r->display])
                ->toArray();

            return ['items' => $items];
        } catch (\Exception $e) {
            Log::warning('SmartSearch ICD-O Topography error: ' . $e->getMessage());
            return ['items' => [], 'error' => $e->getMessage()];
        }
    }

    private function searchIcdOMorphology(string $query, int $limit): array
    {
        try {
            $items = IcdOMorphology::where('code', 'like', "%{$query}%")
                ->orWhere('display', 'like', "%{$query}%")
                ->limit($limit)
                ->get(['code', 'display'])
                ->map(fn($r) => ['code' => $r->code, 'display' => $r->display])
                ->toArray();

            return ['items' => $items];
        } catch (\Exception $e) {
            Log::warning('SmartSearch ICD-O Morphology error: ' . $e->getMessage());
            return ['items' => [], 'error' => $e->getMessage()];
        }
    }

    private function searchIcdPm(string $query, int $limit): array
    {
        try {
            $items = IcdPm::where('code', 'like', "%{$query}%")
                ->orWhere('display', 'like', "%{$query}%")
                ->limit($limit)
                ->get(['code', 'display'])
                ->map(fn($r) => ['code' => $r->code, 'display' => $r->display])
                ->toArray();

            return ['items' => $items];
        } catch (\Exception $e) {
            Log::warning('SmartSearch ICD-PM error: ' . $e->getMessage());
            return ['items' => [], 'error' => $e->getMessage()];
        }
    }

    private function searchIcdMm(string $query, int $limit): array
    {
        try {
            $items = IcdMm::where('code', 'like', "%{$query}%")
                ->orWhere('display', 'like', "%{$query}%")
                ->limit($limit)
                ->get(['code', 'display'])
                ->map(fn($r) => ['code' => $r->code, 'display' => $r->display])
                ->toArray();

            return ['items' => $items];
        } catch (\Exception $e) {
            Log::warning('SmartSearch ICD-MM error: ' . $e->getMessage());
            return ['items' => [], 'error' => $e->getMessage()];
        }
    }

    private function searchLoinc(string $query, int $limit): array
    {
        try {
            $items = Loinc::where('loinc_num', 'like', "%{$query}%")
                ->orWhere('long_common_name', 'like', "%{$query}%")
                ->orWhere('component', 'like', "%{$query}%")
                ->limit($limit)
                ->get(['loinc_num', 'long_common_name', 'component'])
                ->map(fn($r) => [
                    'code' => $r->loinc_num,
                    'display' => $r->long_common_name ?: $r->component,
                ])
                ->toArray();

            return ['items' => $items];
        } catch (\Exception $e) {
            Log::warning('SmartSearch LOINC error: ' . $e->getMessage());
            return ['items' => [], 'error' => $e->getMessage()];
        }
    }

    private function searchHl7(string $query, int $limit): array
    {
        return $this->searchDictionary($query, 'hl7', $limit);
    }

    private function searchKemkes(string $query, int $limit): array
    {
        return $this->searchDictionary($query, 'kemkes', $limit);
    }

    private function searchDictionary(string $query, string $source, int $limit): array
    {
        try {
            $items = FhirDictionary::fromSource($source)
                ->where(function($q) use ($query) {
                    $q->where('system_code', 'like', "%{$query}%")
                      ->orWhere('system_term', 'like', "%{$query}%")
                      ->orWhere('system_defenition', 'like', "%{$query}%");
                })
                ->limit($limit)
                ->get(['system_code', 'system_term', 'system_display', 'type'])
                ->map(fn($r) => [
                    'code' => $r->system_code,
                    'display' => $r->system_term ?: $r->system_display ?: $r->system_code,
                    'extra' => $r->type,
                ])
                ->toArray();

            return ['items' => $items];
        } catch (\Exception $e) {
            Log::warning("SmartSearch {$source} error: " . $e->getMessage());
            return ['items' => [], 'error' => $e->getMessage()];
        }
    }

    private function searchSnomed(string $query, int $limit): array
    {
        try {
            $snowstorm = app(SnowstormService::class);
            $response = $snowstorm->search($query, $limit);

            $items = collect($response['items'] ?? [])->map(fn($item) => [
                'code'    => $item['conceptId'] ?? '',
                'display' => $item['pt']['term'] ?? ($item['fsn']['term'] ?? ''),
                // FSN beda dari PT → tampilkan sebagai deskripsi klinik
                'extra'   => ($item['fsn']['term'] ?? '') !== ($item['pt']['term'] ?? '')
                             ? ($item['fsn']['term'] ?? null)
                             : null,
            ])->toArray();

            return ['items' => $items];
        } catch (\Exception $e) {
            Log::warning('SmartSearch SNOMED error: ' . $e->getMessage());
            return ['items' => [], 'error' => 'Snowstorm tidak tersedia'];
        }
    }

    private function searchKfa(string $query, int $limit): array
    {
        try {
            $kfa = app(KfaService::class);

            $farmasi = $kfa->searchFarmasi($query, 1, $limit);
            $alkes = $kfa->searchAlkesProducts($query, 1, $limit);

            $farmasiItems = collect($farmasi['items']['data'] ?? [])
                ->map(fn($item) => [
                    'code' => $item['kfa_code'] ?? '',
                    'display' => $item['name'] ?? '',
                    'extra' => 'Farmasi',
                ]);

            $alkesItems = collect($alkes['items']['data'] ?? [])
                ->map(fn($item) => [
                    'code' => $item['kfa_code'] ?? '',
                    'display' => $item['name'] ?? '',
                    'extra' => 'Alkes',
                ]);

            $items = $farmasiItems->merge($alkesItems)->take($limit)->values()->toArray();

            return ['items' => $items];
        } catch (\Exception $e) {
            Log::warning('SmartSearch KFA error: ' . $e->getMessage());
            return ['items' => [], 'error' => 'KFA tidak tersedia'];
        }
    }
}
