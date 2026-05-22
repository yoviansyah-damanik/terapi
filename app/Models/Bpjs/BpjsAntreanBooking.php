<?php

namespace App\Models\Bpjs;

use Illuminate\Database\Eloquent\Model;

class BpjsAntreanBooking extends Model
{
    protected $table = 'bpjs_antrean_bookings';
    protected $primaryKey = 'kode_booking';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'kode_booking',
        'tanggal',
        'kd_poli',
        'kd_dokter',
        'jam_praktek',
        'nik',
        'no_kartu',
        'no_hp',
        'no_rm',
        'jenis_kunjungan',
        'no_referensi',
        'sumber_data',
        'is_peserta',
        'no_antrean',
        'estimasi_timestamp',
        'status',
        'created_time_timestamp',
    ];

    protected function casts(): array
    {
        return [
            'is_peserta' => 'boolean',
            'estimasi_timestamp' => 'integer',
            'created_time_timestamp' => 'integer',
        ];
    }
}
