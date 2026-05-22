<?php

namespace App\Models\Simrs;

use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PermintaanLab extends SimrsModel
{
    use Compoships;
    protected $table = 'permintaan_lab';

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
     * Join saran/kesan (1:1) via no_rawat + tgl_hasil + jam_hasil.
     * Detail periksa (1:N) di-load via relasi detailPeriksaLab.
     * Kolom tambahan: saran, kesan (dari saran_kesan_lab)
     */
    public function scopeWithDetails(Builder $query): Builder
    {
        return $query
            ->leftJoin('saran_kesan_lab', function ($join) {
                $join->on('permintaan_lab.no_rawat', '=', 'saran_kesan_lab.no_rawat')
                    ->on('permintaan_lab.tgl_hasil', '=', 'saran_kesan_lab.tgl_periksa')
                    ->on('permintaan_lab.jam_hasil', '=', 'saran_kesan_lab.jam');
            })
            ->with('detailPeriksaLab.jenisPerawatan')
            ->select([
                'permintaan_lab.*',
                'saran_kesan_lab.saran',
                'saran_kesan_lab.kesan',
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

    public function periksaLab(): HasMany
    {
        return $this->hasMany(
            PeriksaLab::class,
            ['no_rawat', 'tgl_periksa', 'jam'],
            ['no_rawat', 'tgl_hasil', 'jam_hasil'],
        )->where('kategori', 'PK');
    }

    public function detailPeriksaLab(): HasMany
    {
        return $this->hasMany(
            DetailPeriksaLab::class,
            ['no_rawat', 'tgl_periksa', 'jam'],
            ['no_rawat', 'tgl_hasil', 'jam_hasil'],
        );
    }

    public function kesanSaran(): HasOne
    {
        return $this->hasOne(
            SaranKesanLab::class,
            ['no_rawat', 'tgl_periksa', 'jam'],
            ['no_rawat', 'tgl_hasil', 'jam_hasil'],
        );
    }
}
