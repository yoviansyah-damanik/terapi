<?php

namespace App\Models\Simrs;

class KodeSatuan extends SimrsModel
{
    protected $table = 'kodesatuan';
    protected $primaryKey = 'kode_sat';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['kode_sat', 'satuan'];
}
