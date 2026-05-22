<?php

namespace App\Models\Simrs;

class SukuBangsa extends SimrsModel
{
    protected $table = 'suku_bangsa';

    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'id',
        'nama_suku_bangsa',
    ];

    public function patients()
    {
        return $this->hasMany(Patient::class, 'suku_bangsa', 'id');
    }
}
