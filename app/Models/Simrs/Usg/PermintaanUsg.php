<?php

namespace App\Models\Simrs\Usg;

use App\Models\Simrs\RegPeriksa;
use App\Models\Simrs\SimrsModel;

class PermintaanUsg extends SimrsModel
{
    protected $table = 'permintaan_usg';

    protected $primaryKey = 'noorder';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'noorder',
        'no_rawat',
        'jenis_permintaan',
        'waktu_permintaan',
        'waktu_hasil',
    ];

    protected function casts(): array
    {
        return [
            'waktu_permintaan' => 'datetime',
            'waktu_hasil'      => 'datetime',
        ];
    }

    public function hasil()
    {
        return $this->hasOne(HasilPemeriksaanUsg::class, 'noorder', 'noorder');
    }

    public function regPeriksa()
    {
        return $this->hasOne(RegPeriksa::class, 'no_rawat', 'no_rawat');
    }

    public function scopeHasResult($query)
    {
        return $query->whereNotNull('waktu_hasil')
            ->where('waktu_hasil', '!=', '0000-00-00 00:00:00');
    }
}
