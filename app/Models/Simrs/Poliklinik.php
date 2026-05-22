<?php

namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Poliklinik extends SimrsModel
{
    protected $table = 'poliklinik';

    protected $primaryKey = 'kd_poli';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'kd_poli',
        'nm_poli',
        'registrasi',
        'registrasilama',
        'status',
    ];

    public function regPeriksa(): HasMany
    {
        return $this->hasMany(RegPeriksa::class, 'kd_poli', 'kd_poli');
    }

    public function scopeActive($query)
    {
        return $query->where('status', '1');
    }

    public function scopeSearch($query, ?string $search)
    {
        if (!$search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('kd_poli', 'like', "%{$search}%")
                ->orWhere('nm_poli', 'like', "%{$search}%");
        });
    }
}
