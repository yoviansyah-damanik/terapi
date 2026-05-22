<?php

namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Icd9 extends SimrsModel
{
    protected $table = 'icd9';

    protected $primaryKey = 'kode';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['kode', 'deskripsi_panjang', 'deskripsi_pendek'];

    public function snomedMap(): HasOne
    {
        return $this->hasOne(\App\Models\Mapping\Icd9Map::class, 'icd9_code', 'kode');
    }
}
