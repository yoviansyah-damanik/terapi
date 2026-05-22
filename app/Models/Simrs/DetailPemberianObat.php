<?php

namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DetailPemberianObat extends SimrsModel
{
    use \Awobaz\Compoships\Compoships;

    protected $table = 'detail_pemberian_obat';

    public $incrementing = false;

    protected $fillable = [
        'tgl_perawatan',
        'jam',
        'no_rawat',
        'kode_brng',
        'h_beli',
        'biaya_obat',
        'jml',
        'embalase',
        'tuslah',
        'total',
        'status',
        'kd_bangsal',
        'no_batch',
        'no_faktur',
    ];

    protected function casts(): array
    {
        return [
            'tgl_perawatan' => 'date',
            'h_beli' => 'decimal:2',
            'biaya_obat' => 'decimal:2',
            'jml' => 'decimal:2',
            'embalase' => 'decimal:2',
            'tuslah' => 'decimal:2',
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

    public function bangsal(): BelongsTo
    {
        return $this->belongsTo(Bangsal::class, 'kd_bangsal', 'kd_bangsal');
    }

    /**
     * Aturan pakai obat untuk baris pemberian ini.
     * Karena Eloquent tidak mendukung composite foreign key secara native,
     * filter tgl_perawatan, jam, dan kode_brng ditambahkan sebagai where clause.
     */
    public function aturanPakai(): HasOne
    {
        return $this->hasOne(AturanPakai::class, ['no_rawat', 'tgl_perawatan', 'jam', 'kode_brng'], ['no_rawat', 'tgl_perawatan', 'jam', 'kode_brng']);
    }

    public function dataBatch(): BelongsTo
    {
        return $this->belongsTo(DataBatch::class, ['kode_brng', 'no_batch'], ['kode_brng', 'no_batch']);
    }
}
