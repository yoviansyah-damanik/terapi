<?php
namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Operasi extends SimrsModel
{
    protected $table = 'operasi';
    public $incrementing = false;

    protected $fillable = [
        'no_rawat',
        'tgl_operasi',
        'jenis_anasthesi',
        'kategori',
        'operator1',
        'operator2',
        'operator3',
        'asisten_operator1',
        'asisten_operator2',
        'asisten_operator3',
        'instrumen',
        'dokter_anak',
        'perawaat_resusitas',
        'dokter_anestesi',
        'asisten_anestesi',
        'asisten_anestesi2',
        'bidan',
        'bidan2',
        'bidan3',
        'perawat_luar',
        'omloop',
        'omloop2',
        'omloop3',
        'omloop4',
        'omloop5',
        'dokter_pjanak',
        'dokter_umum',
        'kode_paket',
        'biayaoperator1',
        'biayaoperator2',
        'biayaoperator3',
        'biayaasisten_operator1',
        'biayaasisten_operator2',
        'biayaasisten_operator3',
        'biayainstrumen',
        'biayadokter_anak',
        'biayaperawaat_resusitas',
        'biayadokter_anestesi',
        'biayaasisten_anestesi',
        'biayaasisten_anestesi2',
        'biayabidan',
        'biayabidan2',
        'biayabidan3',
        'biayaperawat_luar',
        'biayaalat',
        'biayasewaok',
        'akomodasi',
        'bagian_rs',
        'biaya_omloop',
        'biaya_omloop2',
        'biaya_omloop3',
        'biaya_omloop4',
        'biaya_omloop5',
        'biayasarpras',
        'biaya_dokter_pjanak',
        'biaya_dokter_umum',
        'status',
    ];

    public function regPeriksa(): BelongsTo
    {
        return $this->belongsTo(RegPeriksa::class, 'no_rawat', 'no_rawat');
    }

    public function paket(): BelongsTo
    {
        return $this->belongsTo(PaketOperasi::class, 'kode_paket', 'kode_paket');
    }

    public function operator1Dr(): BelongsTo
    {
        return $this->belongsTo(Dokter::class, 'operator1', 'kd_dokter');
    }

    public function operator2Dr(): BelongsTo
    {
        return $this->belongsTo(Dokter::class, 'operator2', 'kd_dokter');
    }

    public function operator3Dr(): BelongsTo
    {
        return $this->belongsTo(Dokter::class, 'operator3', 'kd_dokter');
    }

    public function asistenOperator1Dr(): BelongsTo
    {
        return $this->belongsTo(Dokter::class, 'asisten_operator1', 'kd_dokter');
    }

    public function dokterAnestesiDr(): BelongsTo
    {
        return $this->belongsTo(Dokter::class, 'dokter_anestesi', 'kd_dokter');
    }
}
