<?php

namespace App\Models\Simrs;

class EmergencyIndex extends SimrsModel
{
    protected $table = 'emergency_index';

    protected $primaryKey = 'kode_emergency';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'kode_emergency',
        'nama_emergency',
        'indek',
    ];
}
