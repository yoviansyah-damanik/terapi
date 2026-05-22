<?php

namespace App\Models\Simrs;

class SatuanPolri extends SimrsModel
{
    protected $table = 'satuan_polri';

    protected $fillable = ['id', 'nama_satuan'];

    public $timestamps = false;
}
