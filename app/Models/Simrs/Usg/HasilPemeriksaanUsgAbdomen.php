<?php

namespace App\Models\Simrs\Usg;

class HasilPemeriksaanUsgAbdomen extends HasilPemeriksaanUsgBase
{
    protected $table = 'hasil_pemeriksaan_usg_abdomen';

    protected $fillable = [
        'noorder', 'no_rawat', 'tanggal', 'kd_dokter', 'diagnosa_klinis', 'kiriman_dari',
        'hasil', 'kesimpulan',
    ];

    protected static function gambarModel(): string { return HasilPemeriksaanUsgAbdomenGambar::class; }
}
