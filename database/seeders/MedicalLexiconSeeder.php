<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MedicalLexiconSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $lexicons = [
            ['layman_term' => 'kencing manis', 'clinical_term' => 'Diabetes mellitus', 'snomed_concept_id' => '73211009'],
            ['layman_term' => 'darah tinggi', 'clinical_term' => 'Hypertensive disorder', 'snomed_concept_id' => '38341003'],
            ['layman_term' => 'sesak napas', 'clinical_term' => 'Dyspnea', 'snomed_concept_id' => '267036007'],
            ['layman_term' => 'masuk angin', 'clinical_term' => 'Common cold', 'snomed_concept_id' => '82272006'],
            ['layman_term' => 'sakit maag', 'clinical_term' => 'Dyspepsia', 'snomed_concept_id' => '267287002'],
            ['layman_term' => 'muntaber', 'clinical_term' => 'Gastroenteritis', 'snomed_concept_id' => '252320005'],
            ['layman_term' => 'rabun jauh', 'clinical_term' => 'Myopia', 'snomed_concept_id' => '57190000'],
            ['layman_term' => 'rabun dekat', 'clinical_term' => 'Hypermetropia', 'snomed_concept_id' => '312912001'],
            ['layman_term' => 'kurang darah', 'clinical_term' => 'Anemia', 'snomed_concept_id' => '271737000'],
            ['layman_term' => 'sakit gigi', 'clinical_term' => 'Toothache', 'snomed_concept_id' => '27355003'],
            ['layman_term' => 'biduran', 'clinical_term' => 'Urticaria', 'snomed_concept_id' => '126485001'],
            ['layman_term' => 'kutu air', 'clinical_term' => 'Tinea pedis', 'snomed_concept_id' => '11545000'],
            ['layman_term' => 'panu', 'clinical_term' => 'Pityriasis versicolor', 'snomed_concept_id' => '17424009'],
            ['layman_term' => 'campak', 'clinical_term' => 'Measles', 'snomed_concept_id' => '14189004'],
            ['layman_term' => 'cacar air', 'clinical_term' => 'Varicella', 'snomed_concept_id' => '10134002'],
            ['layman_term' => 'tifus', 'clinical_term' => 'Typhoid fever', 'snomed_concept_id' => '4834000'],
            ['layman_term' => 'tipes', 'clinical_term' => 'Typhoid fever', 'snomed_concept_id' => '4834000'],
            ['layman_term' => 'dbd', 'clinical_term' => 'Dengue fever', 'snomed_concept_id' => '38362002'],
            ['layman_term' => 'demam berdarah', 'clinical_term' => 'Dengue fever', 'snomed_concept_id' => '38362002'],
            ['layman_term' => 'sariawan', 'clinical_term' => 'Aphthous ulcer', 'snomed_concept_id' => '422316003'],
            ['layman_term' => 'gondongan', 'clinical_term' => 'Mumps', 'snomed_concept_id' => '36989005'],
            ['layman_term' => 'batuk rejan', 'clinical_term' => 'Pertussis', 'snomed_concept_id' => '27836007'],
            ['layman_term' => 'tbc', 'clinical_term' => 'Tuberculosis', 'snomed_concept_id' => '56717001'],
        ];

        foreach ($lexicons as $item) {
            \App\Models\Terminology\MedicalLexicon::updateOrCreate(
                ['layman_term' => $item['layman_term']],
                [
                    'clinical_term' => $item['clinical_term'],
                    'snomed_concept_id' => $item['snomed_concept_id']
                ]
            );
        }
    }
}
