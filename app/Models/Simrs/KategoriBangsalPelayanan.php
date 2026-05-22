<?php

namespace App\Models\Simrs;

class KategoriBangsalPelayanan extends SimrsModel
{
    protected $table = 'kategori_bangsal_pelayanan';

    protected $primaryKey = 'kd_kategori';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'kd_kategori',
        'nm_kategori',
        'keterangan',
    ];
}
