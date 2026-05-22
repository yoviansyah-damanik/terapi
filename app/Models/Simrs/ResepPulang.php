<?php

namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResepPulang extends SimrsModel
{
    use \Awobaz\Compoships\Compoships;

    protected $table = 'resep_pulang';

    public $incrementing = false;

    protected $fillable = [
        'no_rawat',
        'kode_brng',
        'jml_barang',
        'harga',
        'total',
        'dosis',
        'tanggal',
        'jam',
        'kd_bangsal',
        'no_batch',
        'no_faktur',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'harga' => 'decimal:2',
            'total' => 'decimal:2',
            'jml_barang' => 'decimal:2',
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

    public function bangsal(): BelongsTo
    {
        return $this->belongsTo(Bangsal::class, 'kd_bangsal', 'kd_bangsal');
    }

    public function dataBatch(): BelongsTo
    {
        return $this->belongsTo(DataBatch::class, ['kode_brng', 'no_batch'], ['kode_brng', 'no_batch']);
    }
}
