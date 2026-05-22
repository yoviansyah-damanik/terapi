<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportSatusehatRuleNumberJob implements ShouldQueue
{
    use Queueable;

    public string $filePath;
    public bool $replaceExisting;

    public function __construct(string $filePath, bool $replaceExisting = false)
    {
        $this->filePath = $filePath;
        $this->replaceExisting = $replaceExisting;
        $this->onQueue('imports');
    }

    public function handle(): void
    {
        try {
            if ($this->replaceExisting) {
                DB::table('satusehat_rule_numbers')->truncate();
            }

            $handle = fopen($this->filePath, 'r');
            if ($handle === false) {
                Log::error("ImportSatusehatRuleNumberJob: Cannot open file at {$this->filePath}");
                return;
            }

            // Detect delimiter (comma or semicolon)
            $firstLine = fgets($handle);
            $delimiter = strpos($firstLine, ';') !== false ? ';' : ',';
            rewind($handle);

            $rawHeaders = fgetcsv($handle, 0, $delimiter);
            // Clean BOM from first header if present
            $rawHeaders[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $rawHeaders[0]);
            
            $headers = array_map(function($h) {
                return strtolower(trim(str_replace([' ', '#', '-'], '_', $h)));
            }, $rawHeaders ?: []);

            $batchSize = 250;
            $batch = [];

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                // If the row length isn't exact, we can pad or slice it
                if (count($row) < count($headers)) {
                    $row = array_pad($row, count($headers), '');
                } elseif (count($row) > count($headers)) {
                    $row = array_slice($row, 0, count($headers));
                }

                $data = array_combine($headers, array_map('trim', $row));

                // Mapping based on CSV "Rule No #;Path;Terminologi Used;Deskripsi Error;Last Update;Version"
                $ruleNo = $data['rule_no__'] ?? $data['rule_no'] ?? $data['rule_no_#'] ?? '';
                if (!$ruleNo) {
                    continue;
                }

                $batch[] = [
                    'id'                 => \Illuminate\Support\Str::uuid()->toString(),
                    'rule_no'            => $ruleNo,
                    'path'               => $data['path'] ?? null,
                    'terminology_used'   => $data['terminologi_used'] ?? $data['terminology_used'] ?? null,
                    'error_description'  => $data['deskripsi_error'] ?? $data['error_description'] ?? null,
                    'rule_last_update'   => $data['last_update'] ?? null,
                    'version'            => $data['version'] ?? null,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ];

                if (count($batch) >= $batchSize) {
                    // Because rule_no is unique, we use upsert
                    DB::table('satusehat_rule_numbers')->upsert($batch, ['rule_no'], [
                        'path', 'terminology_used', 'error_description', 'rule_last_update', 'version', 'updated_at'
                    ]);
                    $batch = [];
                }
            }

            if (!empty($batch)) {
                DB::table('satusehat_rule_numbers')->upsert($batch, ['rule_no'], [
                    'path', 'terminology_used', 'error_description', 'rule_last_update', 'version', 'updated_at'
                ]);
            }

            fclose($handle);

            if (file_exists($this->filePath)) {
                unlink($this->filePath);
            }
        } catch (\Exception $e) {
            Log::error("ImportSatusehatRuleNumberJob failed: " . $e->getMessage());
            throw $e;
        }
    }
}
