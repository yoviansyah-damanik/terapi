<?php

namespace App\Services;

use App\Models\Simrs\Usg\HasilPemeriksaanUsg;
use App\Models\Simrs\Usg\HasilPemeriksaanUsgAbdomen;
use App\Models\Simrs\Usg\HasilPemeriksaanUsgAbdomenGambar;
use App\Models\Simrs\Usg\HasilPemeriksaanUsgAppendix;
use App\Models\Simrs\Usg\HasilPemeriksaanUsgAppendixGambar;
use App\Models\Simrs\Usg\HasilPemeriksaanUsgGambar;
use App\Models\Simrs\Usg\HasilPemeriksaanUsgGynecologi;
use App\Models\Simrs\Usg\HasilPemeriksaanUsgGynecologiGambar;
use App\Models\Simrs\Usg\HasilPemeriksaanUsgHepar;
use App\Models\Simrs\Usg\HasilPemeriksaanUsgHeparGambar;
use App\Models\Simrs\Usg\HasilPemeriksaanUsgMamma;
use App\Models\Simrs\Usg\HasilPemeriksaanUsgMammaGambar;
use App\Models\Simrs\Usg\HasilPemeriksaanUsgNeonatus;
use App\Models\Simrs\Usg\HasilPemeriksaanUsgNeonatusGambar;
use App\Models\Simrs\Usg\HasilPemeriksaanUsgScrotum;
use App\Models\Simrs\Usg\HasilPemeriksaanUsgScrotumGambar;
use App\Models\Simrs\Usg\HasilPemeriksaanUsgThyroid;
use App\Models\Simrs\Usg\HasilPemeriksaanUsgThyroidGambar;
use App\Models\Simrs\Usg\HasilPemeriksaanUsgUrologi;
use App\Models\Simrs\Usg\HasilPemeriksaanUsgUrologiGambar;

class UsgService
{
    public static function getUsgTypeConfigs(): array
    {
        $simple = fn($model, $gambarModel, $label, $fields, $conclusion = 'kesimpulan') => [
            'model' => $model,
            'gambar_model' => $gambarModel,
            'label' => $label,
            'fields' => $fields,
            'conclusion' => $conclusion,
        ];

        return [
            'obstetri' => [
                'model' => HasilPemeriksaanUsg::class,
                'gambar_model' => HasilPemeriksaanUsgGambar::class,
                'label' => 'USG Obstetri',
                'conclusion' => 'kesimpulan',
                'fields' => [
                    'hta' => 'HTA',
                    'kantong_gestasi' => 'Kantong Gestasi',
                    'ukuran_bokongkepala' => 'Ukuran Bokong-Kepala',
                    'jenis_prestasi' => 'Jenis Prestasi',
                    'diameter_biparietal' => 'Diameter Biparietal',
                    'panjang_femur' => 'Panjang Femur',
                    'lingkar_abdomen' => 'Lingkar Abdomen',
                    'tafsiran_berat_janin' => 'Tafsiran Berat Janin',
                    'usia_kehamilan' => 'Usia Kehamilan',
                    'plasenta_berimplatansi' => 'Plasenta Berimplatansi',
                    'derajat_maturitas' => 'Derajat Maturitas',
                    'jumlah_air_ketuban' => 'Jumlah Air Ketuban',
                    'indek_cairan_ketuban' => 'Indeks Cairan Ketuban',
                    'kelainan_kongenital' => 'Kelainan Kongenital',
                    'peluang_sex' => 'Peluang Sex',
                    'kesimpulan' => 'Kesimpulan',
                ],
            ],
            'abdomen' => $simple(
                HasilPemeriksaanUsgAbdomen::class,
                HasilPemeriksaanUsgAbdomenGambar::class,
                'USG Abdomen',
                ['hasil' => 'Hasil', 'kesimpulan' => 'Kesimpulan']
            ),
            'appendix' => $simple(
                HasilPemeriksaanUsgAppendix::class,
                HasilPemeriksaanUsgAppendixGambar::class,
                'USG Appendix',
                ['hasil' => 'Hasil', 'kesimpulan' => 'Kesimpulan']
            ),
            'gynecologi' => $simple(
                HasilPemeriksaanUsgGynecologi::class,
                HasilPemeriksaanUsgGynecologiGambar::class,
                'USG Gynecologi',
                ['hasil' => 'Hasil', 'kesimpulan' => 'Kesimpulan']
            ),
            'hepar' => $simple(
                HasilPemeriksaanUsgHepar::class,
                HasilPemeriksaanUsgHeparGambar::class,
                'USG Hepar',
                ['hasil' => 'Hasil', 'kesimpulan' => 'Kesimpulan']
            ),
            'mamma' => $simple(
                HasilPemeriksaanUsgMamma::class,
                HasilPemeriksaanUsgMammaGambar::class,
                'USG Mamma',
                ['hasil' => 'Hasil', 'kesimpulan' => 'Kesimpulan']
            ),
            'neonatus' => [
                'model' => HasilPemeriksaanUsgNeonatus::class,
                'gambar_model' => HasilPemeriksaanUsgNeonatusGambar::class,
                'label' => 'USG Neonatus',
                'conclusion' => 'kesimpulan',
                'fields' => ['ventrikal_sinistra' => 'Ventrikal Sinistra', 'ventrikal_dextra' => 'Ventrikal Dextra', 'kesan' => 'Kesan', 'kesimpulan' => 'Kesimpulan', 'saran' => 'Saran'],
            ],
            'scrotum' => $simple(
                HasilPemeriksaanUsgScrotum::class,
                HasilPemeriksaanUsgScrotumGambar::class,
                'USG Scrotum',
                ['hasil' => 'Hasil', 'kesimpulan' => 'Kesimpulan']
            ),
            'thyroid' => $simple(
                HasilPemeriksaanUsgThyroid::class,
                HasilPemeriksaanUsgThyroidGambar::class,
                'USG Thyroid',
                ['hasil' => 'Hasil', 'kesimpulan' => 'Kesimpulan']
            ),
            'urologi' => [
                'model' => HasilPemeriksaanUsgUrologi::class,
                'gambar_model' => HasilPemeriksaanUsgUrologiGambar::class,
                'label' => 'USG Urologi',
                'conclusion' => 'tambahan',
                'fields' => ['ginjal_kanan' => 'Ginjal Kanan', 'ginjal_kiri' => 'Ginjal Kiri', 'vesica_urinaria' => 'Vesica Urinaria', 'tambahan' => 'Tambahan'],
            ],
        ];
    }
}
