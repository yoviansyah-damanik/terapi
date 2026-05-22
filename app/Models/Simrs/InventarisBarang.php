<?php

namespace App\Models\Simrs;

/** Model untuk tabel inventaris_barang (SIMRS). */
class InventarisBarang extends SimrsModel
{
    protected $table = 'inventaris_barang';
    protected $primaryKey = 'kode_barang';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'kode_barang',
        'nama_barang',
        'jml_barang',
        'kode_produsen',
        'id_merk',
        'thn_produksi',
        'isbn',
        'id_kategori',
        'id_jenis',
    ];

    public function jenis()
    {
        return $this->belongsTo(InventarisJenis::class, 'id_jenis', 'id_jenis');
    }
}
