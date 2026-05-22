<?php
namespace App\Models\Simrs;

class ObatBhpOk extends SimrsModel
{
    protected $table = 'obatbhp_ok';
    public $incrementing = false;

    protected $fillable = [
        'kd_obat',
        'nm_obat',
        'kode_sat',
        'hargasatuan',
    ];

    public function dataBarang(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(DataBarang::class, 'kd_obat', 'kode_brng');
    }
}
