<?php

namespace App\Models\Simrs;

class KategoriBarang extends SimrsModel
{
    protected $table = 'kategori_barang';
    protected $primaryKey = 'kode';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['kode', 'nama'];
}
