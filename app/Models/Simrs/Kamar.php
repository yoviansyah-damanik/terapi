<?php

namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Kamar extends SimrsModel
{
    protected $table = 'kamar';

    protected $primaryKey = 'kd_kamar';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'kd_kamar',
        'kd_bangsal',
        'trf_kamar',
        'status',
        'kelas',
        'statusdata',
    ];

    protected function casts(): array
    {
        return [
            'trf_kamar' => 'decimal:2',
        ];
    }

    public function bangsal(): BelongsTo
    {
        return $this->belongsTo(Bangsal::class, 'kd_bangsal', 'kd_bangsal');
    }
}
