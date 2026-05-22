<?php

namespace Database\Seeders;

use App\Models\Mapping\GeneralMap;
use Illuminate\Database\Seeder;

class SnomedGeneralMapSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $presets = [
            'status_perkawinan' => [
                ['code' => 'MENIKAH', 'term' => 'Menikah', 'snomed_code' => '87915002', 'snomed_display' => 'Married (finding)', 'system_display' => 'http://snomed.info/sct'],
                ['code' => 'BELUM MENIKAH', 'term' => 'Belum Menikah', 'snomed_code' => '125681006', 'snomed_display' => 'Single person (finding)', 'system_display' => 'http://snomed.info/sct'],
                ['code' => 'JANDA', 'term' => 'Janda', 'snomed_code' => '34230005', 'snomed_display' => 'Widowed (finding)', 'system_display' => 'http://snomed.info/sct'],
                ['code' => 'DUDHA', 'term' => 'Duda', 'snomed_code' => '34230005', 'snomed_display' => 'Widowed (finding)', 'system_display' => 'http://snomed.info/sct'],
                ['code' => 'CERAI HIDUP', 'term' => 'Cerai Hidup', 'snomed_code' => '20340000', 'snomed_display' => 'Divorced (finding)', 'system_display' => 'http://snomed.info/sct'],
                ['code' => 'CERAI MATI', 'term' => 'Cerai Mati', 'snomed_code' => '34230005', 'snomed_display' => 'Widowed (finding)', 'system_display' => 'http://snomed.info/sct'],
            ],
            'jenis_kelamin' => [
                ['code' => 'L', 'term' => 'Laki-laki', 'snomed_code' => '248153007', 'snomed_display' => 'Male (finding)', 'system_display' => 'http://snomed.info/sct'],
                ['code' => 'P', 'term' => 'Perempuan', 'snomed_code' => '248152002', 'snomed_display' => 'Female (finding)', 'system_display' => 'http://snomed.info/sct'],
            ],
            'status_pulang' => [
                ['code' => 'Sudah', 'term' => 'Sudah (Pulang)', 'snomed_code' => '306689006', 'snomed_display' => 'Discharge to home (procedure)', 'system_display' => 'http://snomed.info/sct'],
                ['code' => 'Batal', 'term' => 'Batal', 'snomed_code' => '183932001', 'snomed_display' => 'Procedure cancelled (situation)', 'system_display' => 'http://snomed.info/sct'],
                ['code' => 'Dirujuk', 'term' => 'Dirujuk', 'snomed_code' => '306694006', 'snomed_display' => 'Discharge to other hospital (procedure)', 'system_display' => 'http://snomed.info/sct'],
                ['code' => 'Meninggal', 'term' => 'Meninggal', 'snomed_code' => '419099009', 'snomed_display' => 'Dead (finding)', 'system_display' => 'http://snomed.info/sct'],
                ['code' => 'Dirawat', 'term' => 'Dirawat', 'snomed_code' => '32485007', 'snomed_display' => 'Hospital admission (procedure)', 'system_display' => 'http://snomed.info/sct'],
                ['code' => 'Pulang Paksa', 'term' => 'Pulang Paksa', 'snomed_code' => '306237005', 'snomed_display' => 'Discharge against medical advice (procedure)', 'system_display' => 'http://snomed.info/sct'],
            ],
        ];

        foreach ($presets as $category => $items) {
            foreach ($items as $item) {
                GeneralMap::updateOrCreate(
                    [
                        'category' => $category,
                        'local_code' => $item['code'],
                    ],
                    [
                        'local_term' => $item['term'],
                        'system_code' => $item['snomed_code'],
                        'system_term' => $item['snomed_display'],
                        'system_display' => $item['system_display'] ?? 'http://snomed.info/sct',
                    ]
                );
            }
        }
    }
}
