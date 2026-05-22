<?php

namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateLaboratorium extends SimrsModel
{
    protected $table = 'template_laboratorium';

    public $incrementing = false;

    protected $fillable = [
        'kd_jenis_prw',
        'id_template',
        'Pemeriksaan',
        'satuan',
        'nilai_rujukan_ld',
        'nilai_rujukan_la',
        'nilai_rujukan_pd',
        'nilai_rujukan_pa',
        'bagian_rs',
        'bhp',
        'bagian_perujuk',
        'bagian_dokter',
        'bagian_laborat',
        'kso',
        'menejemen',
        'biaya_item',
        'urut',
    ];

    public function jenisPerawatan(): BelongsTo
    {
        return $this->belongsTo(JnsPerawatanLab::class, 'kd_jenis_prw', 'kd_jenis_prw');
    }
}
