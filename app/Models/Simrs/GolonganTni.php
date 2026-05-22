<?php

namespace App\Models\Simrs;

class GolonganTni extends SimrsModel
{
    protected $table = 'golongan_tni';

    protected $fillable = ['id', 'nama_golongan'];

    public $timestamps = false;
}
