<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ImportFhirDictionaryJob implements ShouldQueue
{
    use Queueable;

    public string $filePath;
    public string $source;
    public array $typesToReplace;

    public function __construct(string $filePath, string $source, array $typesToReplace = [])
    {
        $this->filePath = $filePath;
        $this->source = $source;
        $this->typesToReplace = $typesToReplace;
        $this->onQueue('imports');
    }

    public function handle(): void
    {
        try {
            if (!empty($this->typesToReplace)) {
                DB::table('fhir_dictionaries')
                    ->where('source', $this->source)
                    ->whereIn('type', $this->typesToReplace)
                    ->delete();
            }

            $handle = fopen($this->filePath, 'r');
            if ($handle === false) {
                Log::error("ImportFhirDictionaryJob: Cannot open file at {$this->filePath}");
                return;
            }

            $rawHeaders = fgetcsv($handle, 0, ',');
            $headers = array_map('trim', $rawHeaders ?: []);

            $batchSize = 250;
            $batch = [];

            // Default display/system base URL if not provided in CSV
            $defaultDisplay = match($this->source) {
                'hl7'    => 'http://terminology.hl7.org/CodeSystem',
                'kemkes' => 'https://terminology.kemkes.go.id',
                default  => ''
            };

            while (($row = fgetcsv($handle, 0, ',')) !== false) {
                if (count($headers) !== count($row)) {
                    continue;
                }

                $data = array_combine($headers, array_map('trim', $row));

                $type = $data['type'] ?? '';
                $systemCode = $data['system_code'] ?? $data['code'] ?? '';

                if (!$type || !$systemCode) {
                    continue;
                }

                $batch[] = [
                    'source'             => $this->source,
                    'type'               => $type,
                    'system_code'        => (string)$systemCode,
                    'system_term'        => $data['system_term'] ?? $data['name'] ?? $data['display'] ?? ($data['term'] ?? ''),
                    'system_defenition'  => ($data['system_definition'] ?? $data['system_defenition'] ?? ($data['definition'] ?? '')) ?: null,
                    'system_display'     => ($data['system_display'] ?? ($data['display_url'] ?? $defaultDisplay)) ?: null,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ];

                if (count($batch) >= $batchSize) {
                    DB::table('fhir_dictionaries')->insertOrIgnore($batch);
                    $batch = [];
                }
            }

            if (!empty($batch)) {
                DB::table('fhir_dictionaries')->insertOrIgnore($batch);
            }

            fclose($handle);

            if (file_exists($this->filePath)) {
                unlink($this->filePath);
            }
        } catch (\Exception $e) {
            Log::error("ImportFhirDictionaryJob ({$this->source}) failed: " . $e->getMessage());
            throw $e;
        }
    }
}
