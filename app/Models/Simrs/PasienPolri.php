<?php

namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PasienPolri extends SimrsModel
{
    protected $table = 'pasien_polri';

    protected $primaryKey = 'no_rkm_medis';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'no_rkm_medis',
        'id_golongan',
        'id_pangkat',
        'id_satuan',
        'id_jabatan',
        'nrp',
    ];

    public function pasien(): BelongsTo
    {
        return $this->belongsTo(Pasien::class, 'no_rkm_medis', 'no_rkm_medis');
    }

    public function golongan(): BelongsTo
    {
        return $this->belongsTo(GolonganPolri::class, 'id_golongan', 'id');
    }

    public function pangkat(): BelongsTo
    {
        return $this->belongsTo(PangkatPolri::class, 'id_pangkat', 'id');
    }

    public function satuan(): BelongsTo
    {
        return $this->belongsTo(SatuanPolri::class, 'id_satuan', 'id');
    }

    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(JabatanPolri::class, 'id_jabatan', 'id');
    }
}
