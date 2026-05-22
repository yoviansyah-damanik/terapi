<?php
namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BeriObatOperasi extends SimrsModel
{
    protected $table = 'beri_obat_operasi';
    public $incrementing = false;

    protected $fillable = [
        'no_rawat',
        'tanggal',
        'kd_obat',
        'hargasatuan',
        'jumlah',
    ];

    public function regPeriksa(): BelongsTo
    {
        return $this->belongsTo(RegPeriksa::class, 'no_rawat', 'no_rawat');
    }

    public function obat(): BelongsTo
    {
        return $this->belongsTo(DataBarang::class, 'kd_obat', 'kode_brng');
    }
}
