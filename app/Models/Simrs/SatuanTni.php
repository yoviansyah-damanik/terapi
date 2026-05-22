<?php

namespace App\Models\Simrs;

class SatuanTni extends SimrsModel
{
    protected $table = 'satuan_tni';

    protected $fillable = ['id', 'nama_satuan'];

    public $timestamps = false;
}
