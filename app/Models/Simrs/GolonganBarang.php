<?php

namespace App\Models\Simrs;

class GolonganBarang extends SimrsModel
{
    protected $table = 'golongan_barang';
    protected $primaryKey = 'kode';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['kode', 'nama'];
}
