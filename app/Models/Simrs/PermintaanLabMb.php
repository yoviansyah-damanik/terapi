<?php

namespace App\Models\Simrs;

use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PermintaanLabMb extends SimrsModel
{
    use Compoships;
    protected $table = 'permintaan_labmb';

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
     * Join data periksa lab MB (Mikrobiologi) berdasarkan no_rawat + tgl_hasil + jam_hasil.
     * Kolom tambahan: kd_jenis_prw, tgl_periksa, jam_periksa (dari periksa_lab kategori MB)
     */
    public function scopeWithDetails(Builder $query): Builder
    {
        return $query
            ->leftJoin('periksa_lab', function ($join) {
                $join->on('permintaan_labmb.no_rawat', '=', 'periksa_lab.no_rawat')
                    ->on('permintaan_labmb.tgl_hasil', '=', 'periksa_lab.tgl_periksa')
                    ->on('permintaan_labmb.jam_hasil', '=', 'periksa_lab.jam')
                    ->where('periksa_lab.kategori', '=', 'MB');
            })
            ->select([
                'permintaan_labmb.*',
                'periksa_lab.kd_jenis_prw',
                'periksa_lab.tgl_periksa',
                'periksa_lab.jam as jam_periksa',
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
        )->where('kategori', 'MB');
    }
}
