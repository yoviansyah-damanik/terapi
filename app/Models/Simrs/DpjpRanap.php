<?php

namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DpjpRanap extends SimrsModel
{
    protected $table = 'dpjp_ranap';

    protected $primaryKey = null; // Composite key or no primary key
    public $incrementing = false;

    protected $fillable = [
        'no_rawat',
        'kd_dokter',
    ];

    public function dokter(): BelongsTo
    {
        return $this->belongsTo(Dokter::class, 'kd_dokter', 'kd_dokter');
    }

    public function regPeriksa(): BelongsTo
    {
        return $this->belongsTo(RegPeriksa::class, 'no_rawat', 'no_rawat');
    }
}
