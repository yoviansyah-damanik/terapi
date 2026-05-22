<?php

namespace App\Models\Simrs;

/** Model untuk tabel inventaris_jenis (SIMRS). */
class InventarisJenis extends SimrsModel
{
    protected $table = 'inventaris_jenis';
    protected $primaryKey = 'id_jenis';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id_jenis',
        'nama_jenis',
    ];
}
