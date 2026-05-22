<?php

namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiagnosaPasien extends SimrsModel
{
    protected $table = 'diagnosa_pasien';

    public $incrementing = false;

    protected $fillable = [
        'no_rawat',
        'kd_penyakit',
        'status',
        'prioritas',
        'status_penyakit',
    ];

    public function regPeriksa(): BelongsTo
    {
        return $this->belongsTo(RegPeriksa::class, 'no_rawat', 'no_rawat');
    }

    public function penyakit(): BelongsTo
    {
        return $this->belongsTo(Penyakit::class, 'kd_penyakit', 'kd_penyakit');
    }
}
