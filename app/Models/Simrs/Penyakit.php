<?php

namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Penyakit extends SimrsModel
{
    protected $table = 'penyakit';

    protected $primaryKey = 'kd_penyakit';
    protected $keyType = 'string';
    public $incrementing = false;

    public function snomedMap()
    {
        return $this->hasOne(\App\Models\Mapping\Icd10Map::class, 'icd10_code', 'kd_penyakit');
    }

    protected $fillable = [
        'kd_penyakit',
        'nm_penyakit',
        'ciri_ciri',
        'keterangan',
        'kd_ktg',
        'status',
    ];

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(KategoriPenyakit::class, 'kd_ktg', 'kd_ktg');
    }

    public function diagnosaPasien(): HasMany
    {
        return $this->hasMany(DiagnosaPasien::class, 'kd_penyakit', 'kd_penyakit');
    }
}
