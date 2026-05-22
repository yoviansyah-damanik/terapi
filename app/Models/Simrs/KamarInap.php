<?php

namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KamarInap extends SimrsModel
{
    protected $table = 'kamar_inap';

    public $incrementing = false;

    protected $fillable = [
        'no_rawat',
        'kd_kamar',
        'trf_kamar',
        'diagnosa_awal',
        'diagnosa_akhir',
        'tgl_masuk',
        'jam_masuk',
        'tgl_keluar',
        'jam_keluar',
        'lama',
        'ttl_biaya',
        'stts_pulang',
    ];

    protected function casts(): array
    {
        return [
            'tgl_masuk' => 'date',
            'tgl_keluar' => 'date',
            'trf_kamar' => 'decimal:2',
            'ttl_biaya' => 'decimal:2',
            'lama' => 'integer',
        ];
    }

    public function regPeriksa(): BelongsTo
    {
        return $this->belongsTo(RegPeriksa::class, 'no_rawat', 'no_rawat');
    }

    public function kamar(): BelongsTo
    {
        return $this->belongsTo(Kamar::class, 'kd_kamar', 'kd_kamar');
    }

    public function bangsal(): BelongsTo
    {
        return $this->belongsTo(Bangsal::class, 'kd_kamar', 'kd_bangsal');
    }
}
