<?php

namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetailPeriksaLabPa extends SimrsModel
{
    use \Awobaz\Compoships\Compoships;

    protected $table = 'detail_periksa_labpa';

    public $incrementing = false;

    protected $fillable = [
        'no_rawat',
        'kd_jenis_prw',
        'tgl_periksa',
        'jam',
        'diagnosa_klinik',
        'makroskopik',
        'mikroskopik',
        'kesimpulan',
        'kesan',
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

    public function jenisPerawatan(): BelongsTo
    {
        return $this->belongsTo(JnsPerawatanLab::class, 'kd_jenis_prw', 'kd_jenis_prw');
    }
}
