<?php

namespace App\Models\Simrs;

class ResikoKerja extends SimrsModel
{
    protected $table = 'resiko_kerja';

    protected $primaryKey = 'kode_resiko';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'kode_resiko',
        'nama_resiko',
        'indek',
    ];
}
