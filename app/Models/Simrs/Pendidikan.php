<?php

namespace App\Models\Simrs;

class Pendidikan extends SimrsModel
{
    protected $table = 'pendidikan';

    protected $primaryKey = 'tingkat';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tingkat',
        'indek',
        'gapok1',
        'kenaikan',
        'maksimal',
    ];

    protected function casts(): array
    {
        return [
            'gapok1' => 'decimal:2',
            'kenaikan' => 'decimal:2',
            'maksimal' => 'decimal:2',
        ];
    }
}
