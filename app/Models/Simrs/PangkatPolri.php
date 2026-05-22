<?php

namespace App\Models\Simrs;

class PangkatPolri extends SimrsModel
{
    protected $table = 'pangkat_polri';

    protected $fillable = ['id', 'nama_pangkat'];

    public $timestamps = false;
}
