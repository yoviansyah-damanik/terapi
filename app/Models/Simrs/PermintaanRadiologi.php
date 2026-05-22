<?php

namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermintaanRadiologi extends SimrsModel
{
    protected $table = 'permintaan_radiologi';

    protected $primaryKey = 'noorder';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'noorder',
        'no_rawat',
        'tgl_permintaan',
        'jam_permintaan',
        'tgl_sampel',
        'jam_sampel',
        'tgl_hasil',
        'jam_hasil',
        'dokter_perujuk',
        'status',
        'informasi_tambahan',
        'diagnosa_klinis',
    ];

    protected function casts(): array
    {
        return [
            'tgl_permintaan' => 'date',
            'tgl_sampel' => 'date',
            'tgl_hasil' => 'date',
        ];
    }

    /**
     * Join data periksa, gambar, dan hasil radiologi berdasarkan no_rawat + tgl_hasil + jam_hasil.
     * Kolom tambahan yang tersedia setelah scope ini:
     * - kd_jenis_prw, tgl_periksa, jam_periksa (dari periksa_radiologi)
     * - lokasi_gambar (dari gambar_radiologi)
     * - hasil (dari hasil_radiologi)
     */
    public function scopeWithDetails(Builder $query): Builder
    {
        return $query
            ->leftJoin('periksa_radiologi', function ($join) {
                $join->on('permintaan_radiologi.no_rawat', '=', 'periksa_radiologi.no_rawat')
                    ->on('permintaan_radiologi.tgl_hasil', '=', 'periksa_radiologi.tgl_periksa')
                    ->on('permintaan_radiologi.jam_hasil', '=', 'periksa_radiologi.jam');
            })
            ->leftJoin('gambar_radiologi', function ($join) {
                $join->on('permintaan_radiologi.no_rawat', '=', 'gambar_radiologi.no_rawat')
                    ->on('permintaan_radiologi.tgl_hasil', '=', 'gambar_radiologi.tgl_periksa')
                    ->on('permintaan_radiologi.jam_hasil', '=', 'gambar_radiologi.jam');
            })
            ->leftJoin('hasil_radiologi', function ($join) {
                $join->on('permintaan_radiologi.no_rawat', '=', 'hasil_radiologi.no_rawat')
                    ->on('permintaan_radiologi.tgl_hasil', '=', 'hasil_radiologi.tgl_periksa')
                    ->on('permintaan_radiologi.jam_hasil', '=', 'hasil_radiologi.jam');
            })
            ->join('jns_perawatan_radiologi', function ($join) {
                $join->on('periksa_radiologi.kd_jenis_prw', '=', 'jns_perawatan_radiologi.kd_jenis_prw');
            })
            ->join('dokter', function ($join) {
                $join->on('periksa_radiologi.kd_dokter', '=', 'dokter.kd_dokter');
            })
            ->select([
                'permintaan_radiologi.*',
                'jns_perawatan_radiologi.nm_perawatan as nama_jenis_prw',
                'periksa_radiologi.kd_jenis_prw',
                'periksa_radiologi.tgl_periksa',
                'periksa_radiologi.jam as jam_periksa',
                'gambar_radiologi.lokasi_gambar',
                'dokter.nm_dokter',
                'hasil_radiologi.hasil',
            ]);
    }

    public function regPeriksa(): BelongsTo
    {
        return $this->belongsTo(RegPeriksa::class, 'no_rawat', 'no_rawat');
    }

    public function dokterPerujuk(): BelongsTo
    {
        return $this->belongsTo(Dokter::class, 'dokter_perujuk', 'kd_dokter');
    }

    public function allPeriksaRad()
    {
        return $this->hasMany(PeriksaRadiologi::class, 'no_rawat', 'no_rawat');
    }

    public function getPeriksaRadAttribute()
    {
        return $this->allPeriksaRad->filter(fn($item) => 
            $item->tgl_periksa?->format('Y-m-d') === $this->tgl_hasil?->format('Y-m-d') && 
            $item->jam === $this->jam_hasil
        )->values();
    }

    public function allHasilRadiologi()
    {
        return $this->hasMany(HasilRadiologi::class, 'no_rawat', 'no_rawat');
    }

    public function getHasilRadiologiAttribute()
    {
        return $this->allHasilRadiologi->first(fn($item) => 
            $item->tgl_periksa?->format('Y-m-d') === $this->tgl_hasil?->format('Y-m-d') && 
            $item->jam === $this->jam_hasil
        );
    }

    public function allGambarRadiologi()
    {
        return $this->hasMany(GambarRadiologi::class, 'no_rawat', 'no_rawat');
    }

    public function getGambarRadiologiAttribute()
    {
        return $this->allGambarRadiologi->filter(fn($item) => 
            $item->tgl_periksa?->format('Y-m-d') === $this->tgl_hasil?->format('Y-m-d') && 
            $item->jam === $this->jam_hasil
        )->values();
    }

    public function allBhpRadiologi()
    {
        return $this->hasMany(BeriBhpRadiologi::class, 'no_rawat', 'no_rawat');
    }

    public function getBhpRadiologiAttribute()
    {
        return $this->allBhpRadiologi->filter(fn($item) => 
            $item->tgl_periksa?->format('Y-m-d') === $this->tgl_hasil?->format('Y-m-d') && 
            $item->jam === $this->jam_hasil
        )->values();
    }
}
