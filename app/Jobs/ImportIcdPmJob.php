<?php

namespace App\Jobs;

use App\Models\Terminology\IcdPm;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Import file CSV ICD-PM ke tabel icd_pm.
 *
 * Format CSV: CODE, DISPLAY, VERSION
 * Hierarki header (tanpa VERSION):
 *   - 1 huruf kapital (A/I/N/M)     → kategori utama
 *   - Huruf + angka (A1, N10, dst.) → subkategori
 *   - Kode range (Q00-Q07, dst.)    → diabaikan
 * Baris dengan VERSION non-kosong → kode ICD aktual yang disimpan
 */
class ImportIcdPmJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param string $filePath      Path file CSV sementara
     * @param array  $forceVersions Versi yang dihapus dulu sebelum import
     */
    public function __construct(
        public readonly string $filePath,
        public readonly array $forceVersions = []
    ) {
        $this->onQueue('imports');
    }

    public function handle(): void
    {
        try {
            if (!empty($this->forceVersions)) {
                DB::table('icd_pm')->whereIn('version', $this->forceVersions)->delete();
            }

            $handle = fopen($this->filePath, 'r');
            if ($handle === false) {
                Log::error("ImportIcdPmJob: Gagal membuka file {$this->filePath}");
                return;
            }

            $rawHeaders = fgetcsv($handle, 0, ',');
            $headers    = array_map(fn($h) => strtolower(trim($h)), $rawHeaders ?? []);

            $batch                   = [];
            $now                     = now()->toDateTimeString();
            $currentCategory         = null;
            $currentCategoryDisplay  = null;
            $currentSubcategory      = null;
            $currentSubcatDisplay    = null;

            while (($row = fgetcsv($handle, 0, ',')) !== false) {
                if (count($headers) !== count($row)) continue;

                $data    = array_combine($headers, $row);
                $code    = trim($data['code']    ?? '');
                $display = trim($data['display'] ?? '');
                $version = trim($data['version'] ?? '');

                // Kategori utama: satu huruf kapital tanpa versi
                if (preg_match('/^[A-Z]$/', $code) && empty($version)) {
                    $currentCategory        = $code;
                    $currentCategoryDisplay = $display;
                    $currentSubcategory     = null;
                    $currentSubcatDisplay   = null;
                    continue;
                }

                // Subkategori: huruf + angka (A1, A2, N10) tanpa versi
                if (preg_match('/^[A-Z]\d+$/', $code) && empty($version)) {
                    $currentSubcategory   = $code;
                    $currentSubcatDisplay = $display;
                    continue;
                }

                // Lewati kode range (mengandung "-") atau kosong
                if (empty($version) || empty($code) || str_contains($code, '-')) continue;

                $batch[] = [
                    'category'            => $currentCategory,
                    'category_display'    => $currentCategoryDisplay,
                    'subcategory'         => $currentSubcategory,
                    'subcategory_display' => $currentSubcatDisplay,
                    'code'                => $code,
                    'display'             => $display,
                    'version'             => $version,
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ];

                if (count($batch) >= 500) {
                    DB::table('icd_pm')->insertOrIgnore($batch);
                    $batch = [];
                }
            }

            if (!empty($batch)) {
                DB::table('icd_pm')->insertOrIgnore($batch);
            }

            fclose($handle);

            if (file_exists($this->filePath)) {
                unlink($this->filePath);
            }
        } catch (\Exception $e) {
            Log::error("ImportIcdPmJob gagal: {$e->getMessage()}");
            throw $e;
        }
    }
}
