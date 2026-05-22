<?php

namespace App\Models\Simrs;

class MappingRadiologiModality extends SimrsModel
{
    protected $table = 'mapping_radiologi_modality';
    protected $primaryKey = 'kd_jenis_prw';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'kd_jenis_prw',
        'modality_code',
    ];

    public function perawatan()
    {
        return $this->belongsTo(JnsPerawatanRadiologi::class, 'kd_jenis_prw', 'kd_jenis_prw');
    }
}
