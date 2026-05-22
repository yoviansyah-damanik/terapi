<?php
namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaporanOperasi extends SimrsModel
{
    protected $table = 'laporan_operasi';
    public $incrementing = false;
    protected $primaryKey = 'no_rawat';

    protected $fillable = [
        'no_rawat',
        'tanggal',
        'diagnosa_preop',
        'diagnosa_postop',
        'jaringan_dieksekusi',
        'selesaioperasi',
        'permintaan_pa',
        'laporan_operasi',
    ];

    public function regPeriksa(): BelongsTo
    {
        return $this->belongsTo(RegPeriksa::class, 'no_rawat', 'no_rawat');
    }
}
