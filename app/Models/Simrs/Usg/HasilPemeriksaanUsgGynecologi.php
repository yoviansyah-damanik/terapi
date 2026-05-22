<?php

namespace App\Models\Simrs\Usg;

class HasilPemeriksaanUsgGynecologi extends HasilPemeriksaanUsgBase
{
    protected $table = 'hasil_pemeriksaan_usg_gynecologi_new';

    protected $fillable = [
        'noorder',
        'no_rawat',
        'tanggal',
        'kd_dokter',
        'diagnosa_klinis',
        'kiriman_dari',
        'uterus',
        'parametrium',
        'ovarium',
        'doppler',
        'kesimpulan',
    ];

    protected static function gambarModel(): string
    {
        return HasilPemeriksaanUsgGynecologiGambar::class;
    }
}
