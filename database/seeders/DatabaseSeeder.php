<?php

namespace Database\Seeders;

use App\Jobs\ImportFhirDictionaryJob;
use App\Jobs\ImportIcdMmJob;
use App\Jobs\ImportIcdPmJob;
use App\Jobs\ImportTerminologyJob;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->importTerminologies();
        $this->importLoinc();
        $this->importHl7CodeSystem();
        $this->importSatuSehatCodeSystem();

        $this->call([
            ConfigurationSeeder::class,
                // SpecialtySeeder::class,
            SnomedSemanticTagSeeder::class,
            SnomedGeneralMapSeeder::class,
            SnomedSeeder::class,
            ApiSeeder::class
        ]);

        User::factory()->create([
            'username' => 'it.rumkittnipsp',
            'name' => 'IT Rumkit',
            'email' => 'it.rumkittnipsp@gmail.com',
            'role' => 'admin'
        ]);
    }

    /** Import semua terminologi ICD dari folder resources/files */
    private function importTerminologies(): void
    {
        $sourceTerms = [
            ['icd-o.csv', 'icd_o_topography', 'ICD-O Topography'],
            ['icd-o3.csv', 'icd_o_morphology', 'ICD-O Morphology'],
            ['icd9.csv', 'icd9', 'ICD-9CM'],
            ['icd10.csv', 'icd10', 'ICD-10'],
        ];

        foreach ($sourceTerms as [$file, $type, $label]) {
            $this->importSourceTerminology($file, $type, $label);
        }

        $this->importIcdMm();
        $this->importIcdPm();
    }

    /** Import satu tipe terminologi ke tabel yang sesuai */
    private function importSourceTerminology(string $fileName, string $type, string $label): void
    {
        $source = resource_path("files/{$fileName}");

        if (!file_exists($source)) {
            $this->command->warn("File {$label} tidak ditemukan: {$source}");
            return;
        }

        $tableName = match ($type) {
            'icd10' => 'icd10',
            'icd9' => 'icd9',
            'icd_o_topography' => 'icd_o_topography',
            'icd_o_morphology' => 'icd_o_morphology',
            default => 'source_terminologies',
        };

        $this->command->info("Mengimpor {$label}...");
        DB::table($tableName)->truncate();

        (new ImportTerminologyJob($this->copyToTemp($source), $type))->handle();

        $count = DB::table($tableName)->count();
        $this->command->info("  Selesai: {$count} baris disimpan.");
    }

    /** Import ICD-MM ke tabel icd_mm */
    private function importIcdMm(): void
    {
        $source = resource_path('files/icd-mm.csv');

        if (!file_exists($source)) {
            $this->command->warn('File ICD-MM tidak ditemukan: ' . $source);
            return;
        }

        $this->command->info('Mengimpor ICD-MM...');
        DB::table('icd_mm')->truncate();

        (new ImportIcdMmJob($this->copyToTemp($source)))->handle();

        $count = DB::table('icd_mm')->count();
        $this->command->info("  Selesai: {$count} baris disimpan.");
    }

    /** Import ICD-PM ke tabel icd_pm */
    private function importIcdPm(): void
    {
        $source = resource_path('files/icd-pm.csv');

        if (!file_exists($source)) {
            $this->command->warn('File ICD-PM tidak ditemukan: ' . $source);
            return;
        }

        $this->command->info('Mengimpor ICD-PM...');
        DB::table('icd_pm')->truncate();

        (new ImportIcdPmJob($this->copyToTemp($source)))->handle();

        $count = DB::table('icd_pm')->count();
        $this->command->info("  Selesai: {$count} baris disimpan.");
    }

    /** Import HL7 CodeSystem dari resources/files/hl7.csv */
    private function importHl7CodeSystem(): void
    {
        $source = resource_path('files/hl7.csv');

        if (!file_exists($source)) {
            $this->command->warn('File HL7 CodeSystem tidak ditemukan: ' . $source);
            return;
        }

        $this->command->info('Mengimpor HL7 CodeSystem...');
        DB::table('fhir_dictionaries')->where('source', 'hl7')->delete();

        (new ImportFhirDictionaryJob($this->copyToTemp($source), 'hl7', []))->handle();

        $count = DB::table('fhir_dictionaries')->where('source', 'hl7')->count();
        $this->command->info("  Selesai: {$count} baris disimpan.");
    }

    /** Import SatuSehat CodeSystem dari resources/files/satusehat.csv */
    private function importSatuSehatCodeSystem(): void
    {
        $source = resource_path('files/satusehat.csv');

        if (!file_exists($source)) {
            $this->command->warn('File SatuSehat CodeSystem tidak ditemukan: ' . $source);
            return;
        }

        $this->command->info('Mengimpor SatuSehat CodeSystem...');
        DB::table('fhir_dictionaries')->where('source', 'kemkes')->delete();

        (new ImportFhirDictionaryJob($this->copyToTemp($source), 'kemkes', []))->handle();

        $count = DB::table('fhir_dictionaries')->where('source', 'kemkes')->count();
        $this->command->info("  Selesai: {$count} baris disimpan.");
    }

    /**
     * Copy file ke direktori temp agar job dapat menghapusnya setelah selesai
     * tanpa mempengaruhi file asli di resources/files.
     */
    private function copyToTemp(string $sourcePath): string
    {
        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $tempPath = $tempDir . '/' . uniqid('seed_') . '_' . basename($sourcePath);
        copy($sourcePath, $tempPath);

        return $tempPath;
    }

    /** Import data LOINC dari file CSV ke tabel loinc */
    private function importLoinc(): void
    {
        $filePath = resource_path('files/loinc.csv');

        if (!file_exists($filePath)) {
            $this->command->warn('File LOINC tidak ditemukan: ' . $filePath);
            return;
        }

        $this->command->info('Mengimpor data LOINC...');

        DB::table('loinc')->truncate();

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            $this->command->error('Gagal membuka file LOINC.');
            return;
        }

        $headers = fgetcsv($handle, 0, ',');
        $batchSize = 1000;
        $batch = [];
        $now = now();
        $count = 0;

        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            if (count($headers) !== count($row)) {
                continue;
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
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= $batchSize) {
                DB::table('loinc')->insert($batch);
                $count += count($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            DB::table('loinc')->insert($batch);
            $count += count($batch);
        }

        fclose($handle);

        $this->command->info("Import LOINC selesai: {$count} baris diimpor.");
    }
}
