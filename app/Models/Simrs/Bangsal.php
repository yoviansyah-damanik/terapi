<?php

namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Bangsal extends SimrsModel
{
    protected $table = 'bangsal';

    protected $primaryKey = 'kd_bangsal';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'kd_bangsal',
        'nm_bangsal',
        'status',
    ];

    public function kategoriBangsalPelayanan(): BelongsToMany
    {
        return $this->belongsToMany(
            KategoriBangsalPelayanan::class,
            'bangsal_per_pelayanan',
            'kd_bangsal',
            'kd_kategori',
            'kd_bangsal',
            'kd_kategori'
        );
    }

    public function kategoriBangsalUsia(): BelongsToMany
    {
        return $this->belongsToMany(
            KategoriBangsalUsia::class,
            'bangsal_per_usia',
            'kd_bangsal',
            'kd_kategori',
            'kd_bangsal',
            'kd_kategori'
        );
    }

    public function bangsalGroups(): BelongsToMany
    {
        return $this->belongsToMany(
            BangsalGroup::class,
            'detail_bangsal_group',
            'kd_bangsal',
            'id_group',
            'kd_bangsal',
            'id_group'
        );
    }

    public function jenisKelamin(): HasOne
    {
        return $this->hasOne(BangsalJenisKelamin::class, 'kd_bangsal', 'kd_bangsal');
    }

    public function kamars(): HasMany
    {
        return $this->hasMany(Kamar::class, 'kd_bangsal', 'kd_bangsal');
    }
}
