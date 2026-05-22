<?php

namespace App\Models\Simrs\Usg;

use App\Models\Simrs\Dokter;

class HasilPemeriksaanUsg extends HasilPemeriksaanUsgBase
{
    protected $table = 'hasil_pemeriksaan_usg_new';

    protected $fillable = [
        'noorder',
        'no_rawat',
        'tanggal',
        'kd_dokter',
        'diagnosa_klinis',
        'kiriman_dari',
        'hta',
        'kantong_gestasi',
        'ukuran_bokongkepala',
        'jenis_prestasi',
        'diameter_biparietal',
        'panjang_femur',
        'lingkar_abdomen',
        'tafsiran_berat_janin',
        'usia_kehamilan',
        'plasenta_berimplatansi',
        'derajat_maturitas',
        'jumlah_air_ketuban',
        'indek_cairan_ketuban',
        'kelainan_kongenital',
        'peluang_sex',
        'kesimpulan',
    ];

    protected static function gambarModel(): string
    {
        return HasilPemeriksaanUsgGambar::class;
    }

    public function dokter()
    {
        return $this->belongsTo(Dokter::class, 'kd_dokter', 'kd_dokter');
    }
}
