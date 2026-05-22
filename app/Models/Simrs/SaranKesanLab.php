<?php

namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaranKesanLab extends SimrsModel
{
    use \Awobaz\Compoships\Compoships;

    protected $table = 'saran_kesan_lab';

    public $incrementing = false;

    protected $fillable = [
        'no_rawat',
        'tgl_periksa',
        'jam',
        'saran',
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
}
