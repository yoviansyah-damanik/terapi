<?php

namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermintaanPemeriksaanRadiologi extends SimrsModel
{
    protected $table = 'permintaan_pemeriksaan_radiologi';

    public $incrementing = false;
    public $timestamps   = false;

    protected $fillable = [
        'noorder',
        'kd_jenis_prw',
        'stts_bayar',
    ];

    public function permintaan(): BelongsTo
    {
        return $this->belongsTo(PermintaanRadiologi::class, 'noorder', 'noorder');
    }

    public function jenisPemeriksaan(): BelongsTo
    {
        return $this->belongsTo(JnsPerawatanRadiologi::class, 'kd_jenis_prw', 'kd_jenis_prw');
    }
}
