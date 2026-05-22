<?php

namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BeriBhpRadiologi extends SimrsModel
{
    protected $table = 'beri_bhp_radiologi';

    public $incrementing = false;

    protected $fillable = [
        'no_rawat',
        'tgl_periksa',
        'jam',
        'kode_brng',
        'kode_sat',
        'jumlah',
        'harga',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'tgl_periksa' => 'date',
            'jumlah' => 'decimal:2',
            'harga' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function regPeriksa(): BelongsTo
    {
        return $this->belongsTo(RegPeriksa::class, 'no_rawat', 'no_rawat');
    }

    public function dataBarang(): BelongsTo
    {
        return $this->belongsTo(DataBarang::class, 'kode_brng', 'kode_brng');
    }
}
