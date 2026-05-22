<?php

namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HasilRadiologi extends SimrsModel
{
    protected $table = 'hasil_radiologi';

    public $incrementing = false;

    protected $fillable = [
        'no_rawat',
        'tgl_periksa',
        'jam',
        'hasil',
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
            ->whereRaw('permintaan_radiologi.tgl_hasil = hasil_radiologi.tgl_periksa')
            ->whereRaw('permintaan_radiologi.jam_hasil = hasil_radiologi.jam');
    }
}
