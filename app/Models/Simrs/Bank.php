<?php

namespace App\Models\Simrs;

class Bank extends SimrsModel
{
    protected $table = 'bank';

    protected $primaryKey = 'namabank';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'namabank',
    ];
}
