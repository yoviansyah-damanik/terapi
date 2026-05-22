<?php

namespace App\Models\Simrs;

class CacatFisik extends SimrsModel
{
    protected $table = 'cacat_fisik';

    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'id',
        'nama_cacat',
    ];

    public function patients()
    {
        return $this->hasMany(Patient::class, 'cacat_fisik', 'id');
    }
}
