<?php

namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GambarRadiologi extends SimrsModel
{
    protected $table = 'gambar_radiologi';

    public $incrementing = false;

    protected $fillable = [
        'no_rawat',
        'tgl_periksa',
        'jam',
        'lokasi_gambar',
    ];

    protected function casts(): array
    {
        return [
            'tgl_periksa' => 'date',
        ];
    }

    public function regPeriksa(): BelongsTo
    {
        return $this->belongsTo(RegPeriksa::class, 'no_rawat', 'no_rawat');
    }

    public function permintaanRadiologi(): BelongsTo
    {
        return $this->belongsTo(PermintaanRadiologi::class, 'no_rawat', 'no_rawat')
            ->whereRaw('permintaan_radiologi.tgl_hasil = gambar_radiologi.tgl_periksa')
            ->whereRaw('permintaan_radiologi.jam_hasil = gambar_radiologi.jam');
    }
}
