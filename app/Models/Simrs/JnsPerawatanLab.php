<?php

namespace App\Models\Simrs;

class JnsPerawatanLab extends SimrsModel
{
    protected $table = 'jns_perawatan_lab';

    protected $primaryKey = 'kd_jenis_prw';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'kd_jenis_prw',
        'nm_perawatan',
        'bagian_rs',
        'bhp',
        'tarif_perujuk',
        'tarif_tindakan_dokter',
        'tarif_tindakan_petugas',
        'kso',
        'menejemen',
        'total_byr',
        'kd_pj',
        'status',
        'kelas',
        'kategori',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', '1');
    }
    public function snomedMap()
    {
        return $this->hasOne(\App\Models\Mapping\ProcedureMap::class, 'procedure_code', 'kd_jenis_prw')
            ->where('source_table', 'lab');
    }
}
