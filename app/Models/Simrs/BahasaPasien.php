<?php

namespace App\Models\Simrs;

class BahasaPasien extends SimrsModel
{
    protected $table = 'bahasa_pasien';

    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'id',
        'nama_bahasa',
    ];

    public function patients()
    {
        return $this->hasMany(Patient::class, 'bahasa_pasien', 'id');
    }
}
