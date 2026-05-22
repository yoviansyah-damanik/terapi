<?php

namespace App\Jobs;

use App\Services\TerminologyCacheService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ImportLoincJob implements ShouldQueue
{
    use Queueable;

    public $filePath;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
        $this->onQueue('imports');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Truncate table
            \Illuminate\Support\Facades\DB::table('loinc')->truncate();

            // Open file
            $handle = fopen($this->filePath, 'r');
            if ($handle === false) {
                // Log error or throw exception
                return;
            }

            // Get headers
            $headers = fgetcsv($handle, 0, ','); // Assuming comma delimiter

            // Map CSV headers to DB columns (lowercase)
            // Expected headers: LOINC_NUM,"COMPONENT","PROPERTY","TIME_ASPCT","SYSTEM","SCALE_TYP","METHOD_TYP","CLASS","CLASSTYPE","LONG_COMMON_NAME","SHORTNAME","EXTERNAL_COPYRIGHT_NOTICE","STATUS","VersionFirstReleased","VersionLastChanged"

            $batchSize = 1000;
            $batch = [];

            while (($row = fgetcsv($handle, 0, ',')) !== false) {
                // Combine headers with row logic if needed, or just map by index if order is guaranteed
                // For safety, let's assume standard LOINC format or mapping by header index

                // Simplified mapping assuming standard order or simple key-value if we want to be robust
                // Let's create an associative array first
                if (count($headers) !== count($row)) {
                    continue; // Skip malformed rows
                }

                $data = array_combine($headers, $row);

                $batch[] = [
                    'loinc_num' => $data['LOINC_NUM'] ?? null,
                    'component' => $data['COMPONENT'] ?? null,
                    'property' => $data['PROPERTY'] ?? null,
                    'time_aspct' => $data['TIME_ASPCT'] ?? null,
                    'system' => $data['SYSTEM'] ?? null,
                    'scale_typ' => $data['SCALE_TYP'] ?? null,
                    'method_typ' => $data['METHOD_TYP'] ?? null,
                    'class' => $data['CLASS'] ?? null,
                    'classtype' => $data['CLASSTYPE'] ?? null,
                    'long_common_name' => $data['LONG_COMMON_NAME'] ?? null,
                    'shortname' => $data['SHORTNAME'] ?? null,
                    'external_copyright_notice' => $data['EXTERNAL_COPYRIGHT_NOTICE'] ?? null,
                    'status' => $data['STATUS'] ?? null,
                    'version_first_released' => $data['VersionFirstReleased'] ?? null,
                    'version_last_changed' => $data['VersionLastChanged'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (count($batch) >= $batchSize) {
                    \Illuminate\Support\Facades\DB::table('loinc')->insert($batch);
                    $batch = [];
                }
            }

            // Insert remaining
            if (!empty($batch)) {
                \Illuminate\Support\Facades\DB::table('loinc')->insert($batch);
            }

            fclose($handle);

            // Hapus file setelah diproses
            if (file_exists($this->filePath)) {
                unlink($this->filePath);
            }

            // Invalidasi cache LOINC agar halaman terminologi menampilkan data terbaru
            TerminologyCacheService::clearLoinc();

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Loinc Import Failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
