<?php

namespace App\Models\Simrs;

/** Data batch/lot barang farmasi dari SIMRS (tabel `data_batch`) */
class DataBatch extends SimrsModel
{
    use \Awobaz\Compoships\Compoships;

    protected $table = 'data_batch';

    public $primaryKey = null;

    public $incrementing = false;

    protected $fillable = [
        'no_batch',
        'kode_brng',
        'tgl_beli',
        'tgl_kadaluarsa',
        'asal',
        'no_faktur',
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
        'jumlahbeli',
        'sisa',
    ];

    protected function casts(): array
    {
        return [
            'tgl_beli' => 'date',
            'tgl_kadaluarsa' => 'date',
        ];
    }

    public function dataBarang()
    {
        return $this->belongsTo(DataBarang::class, 'kode_brng', 'kode_brng');
    }
}
