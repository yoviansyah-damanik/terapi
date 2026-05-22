<?php

namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MutasiBerkas extends SimrsModel
{
    protected $table = 'mutasi_berkas';

    public $incrementing = false;
    public $timestamps = false; // Asumsi tabel legacy tidak pakai created_at/updated_at

    protected $fillable = [
        'no_rawat',
        'status',
        'dikirim',
        'diterima',
        'kembali',
        'tidakada',
        'ranap',
    ];

    protected function casts(): array
    {
        return [
            'dikirim' => 'datetime',
            'diterima' => 'datetime',
            'kembali' => 'datetime',
            'tidakada' => 'datetime',
            'ranap' => 'datetime',
        ];
    }
    
    public function regPeriksa(): BelongsTo
    {
        return $this->belongsTo(RegPeriksa::class, 'no_rawat', 'no_rawat');
    }
}
