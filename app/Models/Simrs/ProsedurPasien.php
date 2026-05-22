<?php

namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProsedurPasien extends SimrsModel
{
    protected $table = 'prosedur_pasien';

    public $incrementing = false;

    protected $fillable = [
        'no_rawat',
        'kode',
        'status',
        'prioritas',
    ];

    public function regPeriksa(): BelongsTo
    {
        return $this->belongsTo(RegPeriksa::class, 'no_rawat', 'no_rawat');
    }

    public function icd9(): BelongsTo
    {
        return $this->belongsTo(Icd9::class, 'kode', 'kode');
    }
}
