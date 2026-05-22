<?php

namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Dokter extends SimrsModel
{
    protected $table = 'dokter';

    protected $primaryKey = 'kd_dokter';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'kd_dokter',
        'nm_dokter',
        'jk',
        'tmp_lahir',
        'tgl_lahir',
        'gol_drh',
        'agama',
        'almt_tgl',
        'no_telp',
        'stts_nikah',
        'kd_sps',
        'alumni',
        'no_ijn_praktek',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'tgl_lahir' => 'date',
        ];
    }

    public function regPeriksa(): HasMany
    {
        return $this->hasMany(RegPeriksa::class, 'kd_dokter', 'kd_dokter');
    }

    public function pegawai(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'kd_dokter', 'nik');
    }

    public function scopeSearch($query, ?string $search)
    {
        if (!$search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('kd_dokter', 'like', "%{$search}%")
                ->orWhere('nm_dokter', 'like', "%{$search}%");
        });
    }

    public function scopeActive($query)
    {
        return $query->where('status', '1');
    }
}
