<?php

namespace App\Jobs;

use App\Models\Terminology\IcdMm;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Import file CSV ICD-MM ke tabel icd_mm.
 *
 * Format CSV: LEVEL, CODE, DISPLAY, VERSION
 * - Baris LEVEL non-kosong yang mengandung "Annex B*" → context annex
 * - Baris CODE yang diawali "GROUP-" → context group
 * - Baris dengan CODE mengandung spasi atau kosong → dilewati
 * - Baris dengan VERSION non-kosong dan CODE valid → disimpan
 */
class ImportIcdMmJob implements ShouldQueue
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
                DB::table('icd_mm')->whereIn('version', $this->forceVersions)->delete();
            }

            $handle = fopen($this->filePath, 'r');
            if ($handle === false) {
                Log::error("ImportIcdMmJob: Gagal membuka file {$this->filePath}");
                return;
            }

            $rawHeaders = fgetcsv($handle, 0, ',');
            $headers    = array_map(fn($h) => strtolower(trim($h)), $rawHeaders ?? []);

            // Kolom ICD-MM: level, code, display, version
            $batch               = [];
            $now                 = now()->toDateTimeString();
            $currentAnnex        = null;
            $currentAnnexDisplay = null;
            $currentGroupCode    = null;
            $currentGroupDisplay = null;

            while (($row = fgetcsv($handle, 0, ',')) !== false) {
                if (count($headers) !== count($row)) continue;

                $data    = array_combine($headers, $row);
                $level   = trim($data['level']   ?? '');
                $code    = trim($data['code']    ?? '');
                $display = trim($data['display'] ?? '');
                $version = trim($data['version'] ?? '');

                // Update context annex (baris LEVEL non-kosong)
                if ($level !== '' && preg_match('/Annex\s+(B\d+)/i', $level, $m)) {
                    $currentAnnex        = $m[1];
                    $currentAnnexDisplay = $level;
                    $currentGroupCode    = null;
                    $currentGroupDisplay = null;
                    continue;
                }

                // Update context group (CODE dimulai "GROUP-")
                if (str_starts_with($code, 'GROUP-')) {
                    $currentGroupCode    = $code;
                    $currentGroupDisplay = $display;
                    continue;
                }

                // Skip baris tanpa VERSION, tanpa CODE, atau CODE mengandung spasi
                if (empty($version) || empty($code) || str_contains($code, ' ')) continue;

                $batch[] = [
                    'annex'         => $currentAnnex,
                    'annex_display' => $currentAnnexDisplay,
                    'group_code'    => $currentGroupCode,
                    'group_display' => $currentGroupDisplay,
                    'code'          => $code,
                    'display'       => $display,
                    'version'       => $version,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];

                if (count($batch) >= 500) {
                    DB::table('icd_mm')->insertOrIgnore($batch);
                    $batch = [];
                }
            }

            if (!empty($batch)) {
                DB::table('icd_mm')->insertOrIgnore($batch);
            }

            fclose($handle);

            if (file_exists($this->filePath)) {
                unlink($this->filePath);
            }
        } catch (\Exception $e) {
            Log::error("ImportIcdMmJob gagal: {$e->getMessage()}");
            throw $e;
        }
    }
}
