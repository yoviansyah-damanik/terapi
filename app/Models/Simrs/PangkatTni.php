<?php

namespace App\Models\Simrs;

class PangkatTni extends SimrsModel
{
    protected $table = 'pangkat_tni';

    protected $fillable = ['id', 'nama_pangkat'];

    public $timestamps = false;
}
