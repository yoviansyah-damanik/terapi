<?php

namespace App\Models\Simrs;

/** Data master alergi dari SIMRS (tabel `alergi`) */
class Alergi extends SimrsModel
{
    protected $table = 'alergi';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'nama_alergi',
        'keterangan',
        'tipe',
    ];
}
