<?php

namespace Database\Seeders;

use App\Models\Mapping\EpisodeOfCareMap;
use Illuminate\Database\Seeder;

class EpisodeOfCareMapSeeder extends Seeder
{
    /** Preset mapping Episode of Care → ICD-10 */
    private array $presets = [
        'diab' => [
            'codes' => ['E10', 'E11', 'E12', 'E13', 'E14', 'R73'],
            'notes' => 'Program diabetes terkoordinasi — Diabetes Mellitus dan gangguan toleransi glukosa.',
        ],
        'da' => [
            'codes' => [
                'F10', 'F11', 'F12', 'F13', 'F14', 'F15', 'F16', 'F17', 'F18', 'F19',
                'T40', 'T41', 'T42', 'T43', 'T44', 'T45', 'T46', 'T47', 'T48', 'T49', 'T50',
            ],
            'notes' => 'Rehabilitasi penyalahgunaan obat dan alkohol — gangguan pemakaian zat psikoaktif dan keracunan.',
        ],
        'cacp' => [
            'codes' => [
                'Z00', 'Z01', 'Z02', 'Z03', 'Z04', 'Z08', 'Z09', 'Z10', 'Z11', 'Z12', 'Z13',
                'Z54', 'Z55', 'Z56', 'Z57', 'Z58', 'Z59', 'Z60', 'Z61', 'Z62', 'Z63', 'Z64', 'Z65',
                'R54',
                'I10', 'I11', 'I12', 'I13', 'I14', 'I15',
                'I20', 'I21', 'I22', 'I23', 'I24', 'I25',
                'I60', 'I61', 'I62', 'I63', 'I64', 'I65', 'I66', 'I67', 'I68', 'I69',
            ],
            'notes' => 'Pelayanan kesehatan berbasis komunitas untuk lansia — perawatan kesehatan umum, frailty, dan komorbiditas kronik.',
        ],
        'hacc' => [
            'codes' => ['Z51', 'Z74', 'Z75'],
            'notes' => 'Pelayanan homecare & komunitas — ketergantungan perawat, keterbatasan akses layanan kesehatan.',
        ],
        'pac' => [
            'codes' => ['Z48', 'Z51', 'T81', 'T82', 'T83', 'T84', 'T85', 'T86', 'T87', 'T88'],
            'notes' => 'Perawatan pasca fase akut — pemulihan pasca operasi, komplikasi prosedur, dan perawatan lanjutan.',
        ],
        'CNC' => [
            'codes' => [
                'C00', 'C01', 'C02', 'C03', 'C04', 'C05', 'C06', 'C07', 'C08', 'C09',
                'C10', 'C11', 'C12', 'C13', 'C14', 'C15', 'C16', 'C17', 'C18', 'C19',
                'C20', 'C21', 'C22', 'C23', 'C24', 'C25', 'C26',
                'C30', 'C31', 'C32', 'C33', 'C34', 'C37', 'C38', 'C39',
                'C40', 'C41', 'C43', 'C44', 'C45', 'C46', 'C47', 'C48', 'C49',
                'C50', 'C51', 'C52', 'C53', 'C54', 'C55', 'C56', 'C57', 'C58',
                'C60', 'C61', 'C62', 'C63', 'C64', 'C65', 'C66', 'C67', 'C68', 'C69',
                'C70', 'C71', 'C72', 'C73', 'C74', 'C75', 'C76',
                'C77', 'C78', 'C79', 'C80',
                'C81', 'C82', 'C83', 'C84', 'C85', 'C86', 'C88',
                'C90', 'C91', 'C92', 'C93', 'C94', 'C95', 'C96', 'C97',
                'D00', 'D01', 'D02', 'D03', 'D04', 'D05', 'D06', 'D07', 'D08', 'D09',
                'Z51',
            ],
            'notes' => 'Manajemen kanker — neoplasma ganas, karsinoma in-situ, dan terapi kanker.',
        ],
        'CAD' => [
            'codes' => ['I20', 'I21', 'I22', 'I23', 'I24', 'I25'],
            'notes' => 'Penyakit arteri koroner — angina, infark miokard akut, dan penyakit jantung iskemik kronik.',
        ],
        'CVD' => [
            'codes' => ['I60', 'I61', 'I62', 'I63', 'I64', 'I65', 'I66', 'I67', 'I68', 'I69'],
            'notes' => 'Penyakit serebrovaskular — perdarahan intrakranial, stroke, TIA, dan sekuele serebrovaskular.',
        ],
        'CKD' => [
            'codes' => ['N18', 'N19', 'Z99'],
            'notes' => 'Penyakit ginjal kronik — stadium CKD, gagal ginjal, dan ketergantungan dialisis.',
        ],
        'TB-SO' => [
            'codes' => ['A15', 'A16', 'A17', 'A18', 'A19'],
            'notes' => 'Tuberkulosis sensitif obat — TB respirasi, TB milier, dan TB organ lainnya.',
        ],
        'TB-RO' => [
            'codes' => ['A15', 'A16', 'A17', 'A18', 'A19', 'Z16'],
            'notes' => 'Tuberkulosis resisten obat — TB dengan resistensi antimikroba yang dikonfirmasi.',
        ],
        'ANC' => [
            'codes' => [
                'Z34', 'Z35',
                'O00', 'O01', 'O02', 'O03', 'O04', 'O05', 'O06', 'O07', 'O08', 'O09',
                'O10', 'O11', 'O12', 'O13', 'O14', 'O15', 'O16',
                'O20', 'O21', 'O22', 'O23', 'O24', 'O25', 'O26', 'O28', 'O29',
                'O30', 'O31', 'O32', 'O33', 'O34', 'O35', 'O36',
                'O40', 'O41', 'O42', 'O43', 'O44', 'O45', 'O46', 'O47', 'O48',
                'O60', 'O61', 'O62', 'O63', 'O64', 'O65', 'O66', 'O67', 'O68', 'O69',
            ],
            'notes' => 'Pelayanan antenatal — pengawasan kehamilan normal & risiko tinggi, komplikasi obstetrik.',
        ],
        'Neonate' => [
            'codes' => [
                'P00', 'P01', 'P02', 'P03', 'P04', 'P05', 'P06', 'P07', 'P08', 'P09',
                'P10', 'P11', 'P12', 'P13', 'P14', 'P15',
                'P20', 'P21', 'P22', 'P23', 'P24', 'P25', 'P26', 'P27', 'P28', 'P29',
                'P35', 'P36', 'P37', 'P38', 'P39',
                'P50', 'P51', 'P52', 'P53', 'P54', 'P55', 'P56', 'P57', 'P58', 'P59',
                'P60', 'P61',
                'P70', 'P71', 'P72', 'P73', 'P74', 'P75', 'P76', 'P77', 'P78',
                'P80', 'P81', 'P83',
                'P90', 'P91', 'P92', 'P93', 'P94', 'P95', 'P96',
            ],
            'notes' => 'Perawatan neonatus (0–28 hari) — kondisi perinatal, gangguan pernapasan, infeksi, dan kelainan metabolik neonatal.',
        ],
        'PNC' => [
            'codes' => ['Z39', 'O85', 'O86', 'O87', 'O88', 'O89', 'O90', 'O91', 'O92'],
            'notes' => 'Pelayanan postnatal — komplikasi pasca persalinan, infeksi, mastitis, dan pemantauan ibu nifas.',
        ],
    ];

    public function run(): void
    {
        foreach ($this->presets as $eocCode => $preset) {
            foreach ($preset['codes'] as $parentCode) {
                // Ambil semua kode icd10 yang cocok: kode persis ATAU subkode (misal P00, P00.0, P00.1, ...)
                $expandedCodes = \Illuminate\Support\Facades\DB::table('icd10')
                    ->where(function ($q) use ($parentCode) {
                        $q->where('code', $parentCode)
                          ->orWhere('code', 'like', "{$parentCode}.%");
                    })
                    ->pluck('code')
                    ->toArray();

                // Fallback: jika tidak ada di tabel icd10, tetap simpan kode aslinya
                if (empty($expandedCodes)) {
                    $expandedCodes = [$parentCode];
                }

                foreach ($expandedCodes as $icd10Code) {
                    EpisodeOfCareMap::firstOrCreate(
                        ['eoc_code' => $eocCode, 'icd10_code' => $icd10Code],
                        ['notes' => $preset['notes']]
                    );
                }
            }
        }
    }
}
