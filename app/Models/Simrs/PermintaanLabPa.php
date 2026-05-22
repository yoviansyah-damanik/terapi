<?php

namespace App\Models\Simrs;

use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PermintaanLabPa extends SimrsModel
{
    use Compoships;
    protected $table = 'permintaan_labpa';

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
        'pengambilan_bahan',
        'diperoleh_dengan',
        'lokasi_jaringan',
        'diawetkan_dengan',
        'pernah_dilakukan_di',
        'tanggal_pa_sebelumnya',
        'nomor_pa_sebelumnya',
        'diagnosa_pa_sebelumnya',
    ];

    protected function casts(): array
    {
        return [
            'tgl_permintaan' => 'date',
            'tgl_sampel' => 'date',
            'tgl_hasil' => 'date',
            'tanggal_pa_sebelumnya' => 'date',
        ];
    }

    /**
     * Join data detail periksa PA berdasarkan no_rawat + tgl_hasil + jam_hasil.
     * Kolom tambahan: kd_jenis_prw, tgl_periksa, jam_periksa, kesimpulan, kesan
     * (dari detail_periksa_labpa)
     */
    public function scopeWithDetails(Builder $query): Builder
    {
        return $query
            ->leftJoin('detail_periksa_labpa', function ($join) {
                $join->on('permintaan_labpa.no_rawat', '=', 'detail_periksa_labpa.no_rawat')
                    ->on('permintaan_labpa.tgl_hasil', '=', 'detail_periksa_labpa.tgl_periksa')
                    ->on('permintaan_labpa.jam_hasil', '=', 'detail_periksa_labpa.jam');
            })
            ->select([
                'permintaan_labpa.*',
                'detail_periksa_labpa.kd_jenis_prw',
                'detail_periksa_labpa.tgl_periksa',
                'detail_periksa_labpa.jam as jam_periksa',
                'detail_periksa_labpa.kesimpulan',
                'detail_periksa_labpa.kesan',
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

    public function detailPeriksaLabPa(): HasMany
    {
        return $this->hasMany(
            DetailPeriksaLabPa::class,
            ['no_rawat', 'tgl_periksa', 'jam'],
            ['no_rawat', 'tgl_hasil', 'jam_hasil'],
        );
    }
}
