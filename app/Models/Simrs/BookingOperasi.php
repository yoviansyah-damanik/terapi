<?php
namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingOperasi extends SimrsModel
{
    protected $table = 'booking_operasi';
    public $incrementing = false;

    protected $fillable = [
        'no_rawat',
        'kode_paket',
        'tanggal',
        'jam_mulai',
        'jam_selesai',
        'status',
        'kd_dokter',
        'kd_ruang_ok',
    ];

    public function regPeriksa(): BelongsTo
    {
        return $this->belongsTo(RegPeriksa::class, 'no_rawat', 'no_rawat');
    }

    public function paket(): BelongsTo
    {
        return $this->belongsTo(PaketOperasi::class, 'kode_paket', 'kode_paket');
    }

    public function dokter(): BelongsTo
    {
        return $this->belongsTo(Dokter::class, 'kd_dokter', 'kd_dokter');
    }

    public function ruangOk(): BelongsTo
    {
        return $this->belongsTo(RuangOk::class, 'kd_ruang_ok', 'kd_ruang_ok');
    }
}
