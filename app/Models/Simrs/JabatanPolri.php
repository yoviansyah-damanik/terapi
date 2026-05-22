<?php

namespace App\Models\Simrs;

class JabatanPolri extends SimrsModel
{
    protected $table = 'jabatan_polri';

    protected $fillable = ['id', 'nama_jabatan'];

    public $timestamps = false;
}
