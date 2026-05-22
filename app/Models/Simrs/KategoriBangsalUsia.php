<?php

namespace App\Models\Simrs;

class KategoriBangsalUsia extends SimrsModel
{
    protected $table = 'kategori_bangsal_usia';

    protected $primaryKey = 'kd_kategori';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'kd_kategori',
        'nm_kategori',
        'keterangan',
    ];
}
