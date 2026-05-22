<?php

namespace App\Models\Simrs\Usg;

use App\Models\Simrs\SimrsModel;

abstract class HasilPemeriksaanUsgBase extends SimrsModel
{
    protected $primaryKey = 'noorder';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'noorder',
        'no_rawat',
        'tanggal',
        'kd_dokter',
        'diagnosa_klinis',
        'kiriman_dari',
    ];

    protected function casts(): array
    {
        return ['tanggal' => 'datetime'];
    }

    abstract protected static function gambarModel(): string;

    public function gambar()
    {
        return $this->hasMany(static::gambarModel(), 'noorder', 'noorder');
    }

    public function permintaan()
    {
        return $this->belongsTo(PermintaanUsg::class, 'noorder', 'noorder');
    }
}
