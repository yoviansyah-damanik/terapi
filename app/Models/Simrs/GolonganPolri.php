<?php

namespace App\Models\Simrs;

class GolonganPolri extends SimrsModel
{
    protected $table = 'golongan_polri';

    protected $fillable = ['id', 'nama_golongan'];

    public $timestamps = false;
}
