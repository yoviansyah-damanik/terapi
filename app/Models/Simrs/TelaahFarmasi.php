<?php

namespace App\Models\Simrs;

class TelaahFarmasi extends SimrsModel
{
    protected $table = 'telaah_farmasi';

    protected $primaryKey = 'no_resep';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'no_resep',
        'resep_identifikasi_pasien',
        'resep_ket_identifikasi_pasien',
        'resep_tepat_obat',
        'resep_ket_tepat_obat',
        'resep_tepat_dosis',
        'resep_ket_tepat_dosis',
        'resep_tepat_cara_pemberian',
        'resep_ket_tepat_cara_pemberian',
        'resep_tepat_waktu_pemberian',
        'resep_ket_tepat_waktu_pemberian',
        'resep_ada_tidak_duplikasi_obat',
        'resep_ket_ada_tidak_duplikasi_obat',
        'resep_interaksi_obat',
        'resep_ket_interaksi_obat',
        'resep_kontra_indikasi_obat',
        'resep_ket_kontra_indikasi_obat',
        'obat_tepat_pasien',
        'obat_tepat_obat',
        'obat_tepat_dosis',
        'obat_tepat_cara_pemberian',
        'obat_tepat_waktu_pemberian',
        'nip',
    ];
}
