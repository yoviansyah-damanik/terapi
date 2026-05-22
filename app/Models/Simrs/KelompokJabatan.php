<?php

namespace App\Models\Simrs;

class KelompokJabatan extends SimrsModel
{
    protected $table = 'kelompok_jabatan';

    protected $primaryKey = 'kode_kelompok';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'kode_kelompok',
        'nama_kelompok',
        'indek',
    ];
}
