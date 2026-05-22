<?php

namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AplicareKetersediaanKamar extends SimrsModel
{
    protected $table = 'aplicare_ketersediaan_kamar';

    // Tidak menggunakan auto-increment; composite key (kd_bangsal + kode_kelas_aplicare)
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'kode_kelas_aplicare',
        'kd_bangsal',
        'kelas',
        'kapasitas',
        'tersedia',
        'tersediapria',
        'tersediawanita',
        'tersediapriawanita',
    ];

    public function bangsal(): BelongsTo
    {
        return $this->belongsTo(Bangsal::class, 'kd_bangsal', 'kd_bangsal');
    }
}
