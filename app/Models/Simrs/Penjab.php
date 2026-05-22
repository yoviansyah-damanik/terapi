<?php

namespace App\Models\Simrs;

class Penjab extends SimrsModel
{
    protected $table = 'penjab';

    protected $primaryKey = 'kd_pj';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'kd_pj',
        'png_jawab',
        'nama_perusahaan',
        'alamat_asuransi',
        'no_telp',
        'attn',
        'status',
    ];

    public function patients()
    {
        return $this->hasMany(Patient::class, 'kd_pj', 'kd_pj');
    }
}
