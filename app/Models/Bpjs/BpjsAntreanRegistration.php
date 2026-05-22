<?php

namespace App\Models\Bpjs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BpjsAntreanRegistration extends Model
{
    protected $table = 'bpjs_antrean_registrations';
    protected $primaryKey = 'no_rawat';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'no_rawat',
        'tanggal',
        'kd_poli',
        'nm_poli',
        'kd_dokter',
        'nm_dokter',
        'status_lanjut',
    ];

    public function antrean(): HasOne
    {
        return $this->hasOne(BpjsAntreanBooking::class, 'kode_booking', 'no_rawat');
    }
}
