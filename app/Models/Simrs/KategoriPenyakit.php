<?php

namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Relations\HasMany;

class KategoriPenyakit extends SimrsModel
{
    protected $table = 'kategori_penyakit';

    protected $primaryKey = 'kd_ktg';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['kd_ktg', 'nm_kategori', 'ciri_umum'];

    public function penyakit(): HasMany
    {
        return $this->hasMany(Penyakit::class, 'kd_ktg', 'kd_ktg');
    }
}
