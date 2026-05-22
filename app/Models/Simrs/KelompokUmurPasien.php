<?php

namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kelompok umur pasien ter-assign (tabel `kelompok_umur_pasien` di SIMRS) */
class KelompokUmurPasien extends SimrsModel
{
    protected $table = 'kelompok_umur_pasien';

    protected $primaryKey = 'no_rkm_medis';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'no_rkm_medis',
        'kode_kelompok_umur',
        'umur_hari',
        'tanggal_hitung',
    ];

    protected $casts = [
        'umur_hari'      => 'integer',
        'tanggal_hitung' => 'date',
    ];

    public function pasien(): BelongsTo
    {
        return $this->belongsTo(Pasien::class, 'no_rkm_medis', 'no_rkm_medis');
    }

    public function kelompokUmur(): BelongsTo
    {
        return $this->belongsTo(KelompokUmur::class, 'kode_kelompok_umur', 'kode');
    }
}
