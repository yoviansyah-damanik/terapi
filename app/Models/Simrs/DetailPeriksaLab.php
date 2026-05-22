<?php

namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetailPeriksaLab extends SimrsModel
{
    use \Awobaz\Compoships\Compoships;
    protected $table = 'detail_periksa_lab';

    public $incrementing = false;

    protected $fillable = [
        'no_rawat',
        'kd_jenis_prw',
        'tgl_periksa',
        'jam',
        'id_template',
        'nilai',
        'nilai_rujukan',
        'keterangan',
        'bagian_rs',
        'bhp',
        'bagian_perujuk',
        'bagian_dokter',
        'bagian_laborat',
        'kso',
        'menejemen',
        'biaya_item',
    ];

    protected function casts(): array
    {
        return [
            'tgl_periksa' => 'date',
            'biaya_item' => 'decimal:2',
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

    /**
     * Template item lab (nama, satuan, nilai rujukan) berdasarkan kd_jenis_prw + id_template
     */
    public function template(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(TemplateLaboratorium::class, 'id_template', 'id_template')
            ->where('template_laboratorium.kd_jenis_prw', $this->kd_jenis_prw);
    }
}
