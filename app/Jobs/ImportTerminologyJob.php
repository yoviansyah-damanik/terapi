<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportTerminologyJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param string $filePath      Path file CSV sementara
     * @param string $type          Tipe terminologi (icd10, icd9, icd_o_morphology, dll)
     * @param array  $forceVersions Versi yang dihapus dulu sebelum import
     */
    public function __construct(
        public readonly string $filePath,
        public readonly string $type,
        public readonly array $forceVersions = []
    ) {
        $this->onQueue('imports');
    }

    public function handle(): void
    {
        $table = $this->getTableName();

        try {
            // Hapus versi yang akan diganti
            if (!empty($this->forceVersions)) {
                $query = DB::table($table)->whereIn('version', $this->forceVersions);

                // Tabel source_terminologies butuh filter tambahan berdasarkan type
                if ($table === 'source_terminologies') {
                    $query->where('type', $this->type);
                }

                $query->delete();
            }

            $handle = fopen($this->filePath, 'r');
            if ($handle === false) {
                Log::error("ImportTerminologyJob: Gagal membuka file {$this->filePath}");
                return;
            }

            $rawHeaders = fgetcsv($handle, 0, ',');
            $headers = array_map(fn($h) => strtolower(trim($h)), $rawHeaders);

            $batch = [];
            $now = now()->toDateTimeString();

            while (($row = fgetcsv($handle, 0, ',')) !== false) {
                if (count($headers) !== count($row)) continue;

                $data = array_combine($headers, $row);
                $code    = trim($data['code']    ?? '');
                $version = trim($data['version'] ?? '');

                if (empty($code) || empty($version)) continue;

                $record = [
                    'code'       => $code,
                    'display'    => trim($data['display'] ?? ''),
                    'version'    => $version,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                // Tabel source_terminologies membutuhkan kolom type
                if ($table === 'source_terminologies') {
                    $record['type'] = $this->type;
                }

                $batch[] = $record;

                if (count($batch) >= 1000) {
                    DB::table($table)->insertOrIgnore($batch);
                    $batch = [];
                }
            }

            if (!empty($batch)) {
                DB::table($table)->insertOrIgnore($batch);
            }

            fclose($handle);

            if (file_exists($this->filePath)) {
                unlink($this->filePath);
            }
        } catch (\Exception $e) {
            Log::error("ImportTerminologyJob [{$this->type}] gagal: {$e->getMessage()}");
            throw $e;
        }
    }

    /** Tentukan nama tabel berdasarkan tipe terminologi */
    private function getTableName(): string
    {
        return match($this->type) {
            'icd10'            => 'icd10',
            'icd9'             => 'icd9',
            'icd_o_topography' => 'icd_o_topography',
            'icd_o_morphology' => 'icd_o_morphology',
            default            => 'source_terminologies',
        };
    }
}
