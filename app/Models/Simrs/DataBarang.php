<?php

namespace App\Models\Simrs;

class DataBarang extends SimrsModel
{
    protected $table = 'databarang';

    protected $primaryKey = 'kode_brng';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'kode_brng',
        'nama_brng',
        'kode_satbesar',
        'kode_sat',
        'letak_barang',
        'dasar',
        'h_beli',
        'ralan',
        'kelas1',
        'kelas2',
        'kelas3',
        'utama',
        'vip',
        'vvip',
        'beliluar',
        'jualbebas',
        'karyawan',
        'stokminimal',
        'kdjns',
        'isi',
        'kapasitas',
        'expire',
        'status',
        'kode_industri',
        'kode_kategori',
        'kode_golongan',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', '1');
    }

    public function jenis()
    {
        return $this->belongsTo(Jenis::class, 'kdjns', 'kdjns');
    }

    public function golonganBarang()
    {
        return $this->belongsTo(GolonganBarang::class, 'kode_golongan', 'kode');
    }

    public function kategoriBarang()
    {
        return $this->belongsTo(KategoriBarang::class, 'kode_kategori', 'kode');
    }

    public function industriFarmasi()
    {
        return $this->belongsTo(IndustriFarmasi::class, 'kode_industri', 'kode_industri');
    }

    public function satuanKecil()
    {
        return $this->belongsTo(KodeSatuan::class, 'kode_sat', 'kode_sat');
    }

    public function satuanBesar()
    {
        return $this->belongsTo(KodeSatuan::class, 'kode_satbesar', 'kode_sat');
    }
}
