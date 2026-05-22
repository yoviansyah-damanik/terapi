<?php

namespace App\Models\Simrs\Usg;

class HasilPemeriksaanUsgHepar extends HasilPemeriksaanUsgBase
{
    protected $table = 'hasil_pemeriksaan_usg_hepar';

    protected $fillable = [
        'noorder', 'no_rawat', 'tanggal', 'kd_dokter', 'diagnosa_klinis', 'kiriman_dari',
        'hasil', 'kesimpulan',
    ];

    protected static function gambarModel(): string { return HasilPemeriksaanUsgHeparGambar::class; }
}
