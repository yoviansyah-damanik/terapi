<?php

namespace App\Models\Simrs;

/** Data master reaksi alergi dari SIMRS (tabel `reaksi_alergi`) */
class AlergiReaksi extends SimrsModel
{
    protected $table = 'alergi_reaksi';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'nama_reaksi',
        'keterangan',
        'kategori'
    ];
}
