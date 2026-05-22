<?php

namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RawatJlPr extends SimrsModel
{
    protected $table = 'rawat_jl_pr';

    protected $primaryKey = 'no_rawat';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'no_rawat',
        'kd_jenis_prw',
        'nip',
        'tgl_perawatan',
        'jam_rawat',
        'material',
        'bhp',
        'tarif_tindakanpr',
        'kso',
        'menejemen',
        'biaya_rawat',
        'stts_bayar',
    ];

    protected function casts(): array
    {
        return [
            'tgl_perawatan' => 'date',
            'tarif_tindakanpr' => 'decimal:2',
            'biaya_rawat' => 'decimal:2',
        ];
    }

    public function jenisPerawatan(): BelongsTo
    {
        return $this->belongsTo(JnsPerawatan::class, 'kd_jenis_prw', 'kd_jenis_prw');
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(Petugas::class, 'nip', 'nip');
    }
}
