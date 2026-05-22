<?php

namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatatanAdimeGizi extends SimrsModel
{
    protected $table = 'catatan_adime_gizi';

    // Composite primary key: no_rawat + tanggal
    protected $primaryKey = ['no_rawat', 'tanggal'];
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'no_rawat',
        'tanggal',
        'asesmen',
        'diagnosis',
        'intervensi',
        'monitoring',
        'evaluasi',
        'instruksi',
        'nip',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    public function regPeriksa(): BelongsTo
    {
        return $this->belongsTo(RegPeriksa::class, 'no_rawat', 'no_rawat');
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(Petugas::class, 'nip', 'nip');
    }
}
