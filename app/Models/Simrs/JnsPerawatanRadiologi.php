<?php

namespace App\Models\Simrs;

class JnsPerawatanRadiologi extends SimrsModel
{
    protected $table = 'jns_perawatan_radiologi';

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
    ];

    public function scopeActive($query)
    {
        return $query->where('status', '1');
    }

    public function snomedMap()
    {
        return $this->hasOne(\App\Models\Mapping\ProcedureMap::class, 'procedure_code', 'kd_jenis_prw')
            ->where('source_table', 'radiologi');
    }

    public function modalityMap()
    {
        return $this->hasOne(MappingRadiologiModality::class, 'kd_jenis_prw', 'kd_jenis_prw');
    }
}
