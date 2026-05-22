<?php

namespace App\Models\Simrs;

class Bidang extends SimrsModel
{
    protected $table = 'bidang';

    protected $primaryKey = 'nama';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'nama',
    ];
}
