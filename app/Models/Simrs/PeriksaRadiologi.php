<?php

namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeriksaRadiologi extends SimrsModel
{
    protected $table = 'periksa_radiologi';

    public $incrementing = false;

    protected $fillable = [
        'no_rawat',
        'nip',
        'kd_jenis_prw',
        'tgl_periksa',
        'jam',
        'dokter_perujuk',
        'bagian_rs',
        'bhp',
        'tarif_perujuk',
        'tarif_tindakan_dokter',
        'tarif_tindakan_petugas',
        'kso',
        'menejemen',
        'biaya',
        'kd_dokter',
        'status',
        'proyeksi',
        'kV',
        'mAS',
        'FFD',
        'BSF',
        'inak',
        'jml_penyinaran',
        'dosis',
    ];

    protected function casts(): array
    {
        return [
            'tgl_periksa' => 'date',
            'biaya' => 'decimal:2',
        ];
    }

    public function regPeriksa(): BelongsTo
    {
        return $this->belongsTo(RegPeriksa::class, 'no_rawat', 'no_rawat');
    }

    public function jenisPerawatan(): BelongsTo
    {
        return $this->belongsTo(JnsPerawatanRadiologi::class, 'kd_jenis_prw', 'kd_jenis_prw');
    }

    public function dokter(): BelongsTo
    {
        return $this->belongsTo(Dokter::class, 'kd_dokter', 'kd_dokter');
    }

    public function dokterPerujuk(): BelongsTo
    {
        return $this->belongsTo(Dokter::class, 'dokter_perujuk', 'kd_dokter');
    }

    public function permintaanRadiologi(): BelongsTo
    {
        return $this->belongsTo(PermintaanRadiologi::class, 'no_rawat', 'no_rawat')
            ->whereRaw('permintaan_radiologi.tgl_hasil = periksa_radiologi.tgl_periksa')
            ->whereRaw('permintaan_radiologi.jam_hasil = periksa_radiologi.jam');
    }
}
