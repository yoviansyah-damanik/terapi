<?php

namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AturanPakai extends SimrsModel
{
    use \Awobaz\Compoships\Compoships;

    protected $table = 'aturan_pakai';

    public $incrementing = false;

    protected $fillable = [
        'tgl_perawatan',
        'jam',
        'no_rawat',
        'kode_brng',
        'aturan',
    ];

    protected function casts(): array
    {
        return [
            'tgl_perawatan' => 'date',
        ];
    }

    public function detailPemberianObat(): BelongsTo
    {
        return $this->belongsTo(DetailPemberianObat::class, 'no_rawat', 'no_rawat');
    }
}
