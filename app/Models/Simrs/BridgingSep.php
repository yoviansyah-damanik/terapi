<?php

namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BridgingSep extends SimrsModel
{
    protected $table = 'bridging_sep';

    protected $primaryKey = 'no_sep';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'no_sep',
        'no_rawat',
        'tglsep',
        'tglrujukan',
        'no_rujukan',
        'kdppkrujukan',
        'nmppkrujukan',
        'kdppkpelayanan',
        'nmppkpelayanan',
        'jnspelayanan',
        'catatan',
        'diagawal',
        'nmdiagnosaawal',
        'kdpolitujuan',
        'nmpolitujuan',
        'klsrawat',
        'klsnaik',
        'pembiayaan',
        'pjnaikkelas',
        'lakalantas',
        'user',
        'nomr',
        'nama_pasien',
        'tanggal_lahir',
        'peserta',
        'jkel',
        'no_kartu',
        'tglpulang',
        'asal_rujukan',
        'eksekutif',
        'cob',
        'notelep',
        'katarak',
        'tglkkl',
        'keterangankkl',
        'suplesi',
        'no_sep_suplesi',
        'kdprop',
        'nmprop',
        'kdkab',
        'nmkab',
        'kdkec',
        'nmkec',
        'noskdp',
        'kddpjp',
        'nmdpdjp',
        'tujuankunjungan',
        'flagprosedur',
        'penunjang',
        'asesmenpelayanan',
        'kddpjplayanan',
        'nmdpjplayanan',
        'backdate',
        'antrean',
    ];

    protected function casts(): array
    {
        return [
            'tglsep' => 'date',
            'tglrujukan' => 'date',
            'tglpulang' => 'date',
        ];
    }

    public function regPeriksa(): BelongsTo
    {
        return $this->belongsTo(RegPeriksa::class, 'no_rawat', 'no_rawat');
    }

    /**
     * IGD: jnspelayanan='2' ATAU nmpolitujuan/kdpolitujuan mengandung kata darurat/igd/ugd/gawat.
     */
    public function scopeIgd($query)
    {
        return $query->where('jnspelayanan', '2')
            ->where('nmpolitujuan', 'like', '%darurat%');
    }

    /** Rawat Jalan murni (bukan IGD). */
    public function scopeRalanOnly($query)
    {
        return $query->where('jnspelayanan', '2')
            ->where('nmpolitujuan', 'not like', '%darurat%');
    }

    /** Cek apakah record ini adalah kunjungan IGD. */
    public function isIgd(): bool
    {
        if ((string) $this->jnspelayanan === '1')
            return true;

        $poli = strtolower((string) $this->nmpolitujuan);

        return str_contains($poli, 'darurat');
    }

    public function scopeSearch($query, ?string $search)
    {
        if (!$search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('no_sep', 'like', "%{$search}%")
                ->orWhere('no_rawat', 'like', "%{$search}%")
                ->orWhere('nomr', 'like', "%{$search}%")
                ->orWhere('nama_pasien', 'like', "%{$search}%");
        });
    }
}
