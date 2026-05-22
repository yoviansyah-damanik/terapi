<?php

namespace App\Models\Simrs;

class IndustriFarmasi extends SimrsModel
{
    protected $table = 'industrifarmasi';
    protected $primaryKey = 'kode_industri';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['kode_industri', 'nama_industri', 'alamat', 'kota', 'no_telp'];
}
