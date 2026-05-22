<?php

namespace App\Models\Simrs\Usg;

class HasilPemeriksaanUsgUrologi extends HasilPemeriksaanUsgBase
{
    protected $table = 'hasil_pemeriksaan_usg_urologi';

    protected $fillable = [
        'noorder', 'no_rawat', 'tanggal', 'kd_dokter', 'diagnosa_klinis', 'kiriman_dari',
        'ginjal_kanan', 'ginjal_kiri', 'vesica_urinaria', 'tambahan',
    ];

    protected static function gambarModel(): string { return HasilPemeriksaanUsgUrologiGambar::class; }
}
