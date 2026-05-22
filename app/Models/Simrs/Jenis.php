<?php

namespace App\Models\Simrs;

class Jenis extends SimrsModel
{
    protected $table = 'jenis';
    protected $primaryKey = 'kdjns';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['kdjns', 'nama', 'keterangan'];
}
