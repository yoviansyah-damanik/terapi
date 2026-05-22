<?php

namespace App\Models\Simrs;

class JabatanTni extends SimrsModel
{
    protected $table = 'jabatan_tni';

    protected $fillable = ['id', 'nama_jabatan'];

    public $timestamps = false;
}
