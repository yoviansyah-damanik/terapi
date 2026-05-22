<?php

namespace App\Models\Simrs\Usg;

class HasilPemeriksaanUsgNeonatus extends HasilPemeriksaanUsgBase
{
    protected $table = 'hasil_pemeriksaan_usg_neonatus';

    protected $fillable = [
        'noorder', 'no_rawat', 'tanggal', 'kd_dokter', 'diagnosa_klinis', 'kiriman_dari',
        'ventrikal_sinistra', 'ventrikal_dextra', 'kesan', 'kesimpulan', 'saran',
    ];

    protected static function gambarModel(): string { return HasilPemeriksaanUsgNeonatusGambar::class; }
}
