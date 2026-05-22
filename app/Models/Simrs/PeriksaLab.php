<?php

namespace App\Models\Simrs;

use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeriksaLab extends SimrsModel
{
    use Compoships;
    protected $table = 'periksa_lab';

    public $incrementing = false;

    protected $fillable = [
        'no_rawat',
        'nip',
        'kd_jenis_prw',
        'tgl_periksa',
        'jam',
        'dokter_perujuk',
        'bagian_rs',
        'bhp',
        'tarif_perujuk',
        'tarif_tindakan_dokter',
        'tarif_tindakan_petugas',
        'kso',
        'menejemen',
        'biaya',
        'kd_dokter',
        'status',
        'kategori',
    ];

    protected function casts(): array
    {
        return [
            'tgl_periksa' => 'date',
            'biaya' => 'decimal:2',
        ];
    }

    public function regPeriksa(): BelongsTo
    {
        return $this->belongsTo(RegPeriksa::class, 'no_rawat', 'no_rawat');
    }

    public function jenisPerawatan(): BelongsTo
    {
        return $this->belongsTo(JnsPerawatanLab::class, 'kd_jenis_prw', 'kd_jenis_prw');
    }

    public function dokter(): BelongsTo
    {
        return $this->belongsTo(Dokter::class, 'kd_dokter', 'kd_dokter');
    }

    public function dokterPerujuk(): BelongsTo
    {
        return $this->belongsTo(Dokter::class, 'dokter_perujuk', 'kd_dokter');
    }

    public function detailPeriksaLab(): HasMany
    {
        return $this->hasMany(
            DetailPeriksaLab::class,
            ['no_rawat', 'kd_jenis_prw', 'tgl_periksa', 'jam'],
            ['no_rawat', 'kd_jenis_prw', 'tgl_periksa', 'jam'],
        );
    }

    public function templateLaboratoriums(): HasMany
    {
        return $this->hasMany(TemplateLaboratorium::class, 'kd_jenis_prw', 'kd_jenis_prw')
            ->orderBy('urut');
    }
}
