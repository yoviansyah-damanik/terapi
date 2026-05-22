<?php

namespace App\Models\Simrs;

/** Riwayat alergi pasien dari SIMRS (tabel `alergi_pasien`) */
class AlergiPasien extends SimrsModel
{
    protected $table = 'alergi_pasien';

    public $primaryKey = null;

    public $incrementing = false;

    protected $casts = [
        'tanggal' => 'date',
    ];

    protected $fillable = [
        'no_rkm_medis',
        'id_alergi',
        'id_reaksi',
        'id_tingkat_keparahan',
        'id_kritisitas',
        'catatan',
        'no_rawat_ref',
        'tanggal',
        'jam',
        'nip',
    ];

    public function alergi()
    {
        return $this->belongsTo(Alergi::class, 'id_alergi', 'id');
    }

    public function reaksi()
    {
        return $this->belongsTo(AlergiReaksi::class, 'id_reaksi', 'id');
    }

    public function tingkatKeparahan()
    {
        return $this->belongsTo(AlergiTingkatKeparahan::class, 'id_tingkat_keparahan', 'id');
    }

    public function kritisitas()
    {
        return $this->belongsTo(AlergiKritisitas::class, 'id_kritisitas', 'id');
    }

    /** Referensi kunjungan (no_rawat) — opsional, beda koneksi tidak bisa join langsung */
    public function regPeriksa()
    {
        return $this->belongsTo(RegPeriksa::class, 'no_rawat_ref', 'no_rawat');
    }

    public function pasien()
    {
        return $this->belongsTo(Pasien::class, 'no_rkm_medis', 'no_rkm_medis');
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'nip', 'id');
    }
}
