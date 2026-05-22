<?php

namespace App\Models\Simrs;

class StatusKerja extends SimrsModel
{
    protected $table = 'stts_kerja';

    protected $primaryKey = 'stts';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'stts',
        'ktg',
        'indek',
    ];
}
