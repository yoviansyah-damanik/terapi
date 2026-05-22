<?php

namespace App\Models\Simrs;

use App\Helpers\ConfigurationHelper;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RegPeriksa extends SimrsModel
{
    protected $table = 'reg_periksa';

    protected $primaryKey = 'no_rawat';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'no_reg',
        'no_rawat',
        'tgl_registrasi',
        'jam_reg',
        'kd_dokter',
        'no_rkm_medis',
        'kd_poli',
        'p_jawab',
        'almt_pj',
        'hubunganpj',
        'biaya_reg',
        'stts',
        'stts_daftar',
        'status_lanjut',
        'kd_pj',
        'umurdaftar',
        'sttsumur',
        'status_bayar',
        'status_poli',
    ];

    protected function casts(): array
    {
        return [
            'tgl_registrasi' => 'date',
            'biaya_reg' => 'decimal:2',
        ];
    }

    // ========== RELATIONSHIPS ==========

    public function pasien(): BelongsTo
    {
        return $this->belongsTo(Pasien::class, 'no_rkm_medis', 'no_rkm_medis');
    }

    public function dokter(): BelongsTo
    {
        return $this->belongsTo(Dokter::class, 'kd_dokter', 'kd_dokter');
    }

    public function poliklinik(): BelongsTo
    {
        return $this->belongsTo(Poliklinik::class, 'kd_poli', 'kd_poli');
    }

    public function penjab(): BelongsTo
    {
        return $this->belongsTo(Penjab::class, 'kd_pj', 'kd_pj');
    }

    public function bridgingSep(): HasOne
    {
        return $this->hasOne(BridgingSep::class, 'no_rawat', 'no_rawat');
    }

    public function diagnosaPasien(): HasMany
    {
        return $this->hasMany(DiagnosaPasien::class, 'no_rawat', 'no_rawat');
    }

    public function detailPemberianObat(): HasMany
    {
        return $this->hasMany(DetailPemberianObat::class, 'no_rawat', 'no_rawat');
    }

    public function periksaLab(): HasMany
    {
        return $this->hasMany(PeriksaLab::class, 'no_rawat', 'no_rawat');
    }

    public function detailPeriksaLab(): HasMany
    {
        return $this->hasMany(DetailPeriksaLab::class, 'no_rawat', 'no_rawat');
    }

    public function saranKesanLab(): HasMany
    {
        return $this->hasMany(SaranKesanLab::class, 'no_rawat', 'no_rawat');
    }

    public function kamarInap(): HasMany
    {
        return $this->hasMany(KamarInap::class, 'no_rawat', 'no_rawat');
    }

    public function dpjpRanap(): HasMany
    {
        return $this->hasMany(DpjpRanap::class, 'no_rawat', 'no_rawat');
    }

    public function resepPulang(): HasMany
    {
        return $this->hasMany(ResepPulang::class, 'no_rawat', 'no_rawat');
    }

    public function permintaanLab(): HasMany
    {
        return $this->hasMany(PermintaanLab::class, 'no_rawat', 'no_rawat');
    }

    public function permintaanLabMb(): HasMany
    {
        return $this->hasMany(PermintaanLabMb::class, 'no_rawat', 'no_rawat');
    }

    public function permintaanLabPa(): HasMany
    {
        return $this->hasMany(PermintaanLabPa::class, 'no_rawat', 'no_rawat');
    }

    public function detailPeriksaLabPa(): HasMany
    {
        return $this->hasMany(DetailPeriksaLabPa::class, 'no_rawat', 'no_rawat');
    }

    public function permintaanRadiologi(): HasMany
    {
        return $this->hasMany(PermintaanRadiologi::class, 'no_rawat', 'no_rawat');
    }

    public function alergiPasien(): HasMany
    {
        return $this->hasMany(AlergiPasien::class, 'no_rawat', 'no_rawat');
    }

    public function prosedurPasien(): HasMany
    {
        return $this->hasMany(ProsedurPasien::class, 'no_rawat', 'no_rawat');
    }

    public function pemeriksaanRalan(): HasMany
    {
        return $this->hasMany(PemeriksaanRalan::class, 'no_rawat', 'no_rawat');
    }

    public function pemeriksaanRanap(): HasMany
    {
        return $this->hasMany(PemeriksaanRanap::class, 'no_rawat', 'no_rawat');
    }

    public function mutasiBerkas(): HasOne
    {
        return $this->hasOne(MutasiBerkas::class, 'no_rawat', 'no_rawat');
    }

    public function resumePasien(): HasOne
    {
        return $this->hasOne(ResumePasien::class, 'no_rawat', 'no_rawat');
    }

    public function resumePasienRanap(): HasOne
    {
        return $this->hasOne(ResumePasienRanap::class, 'no_rawat', 'no_rawat');
    }

    public function rawatJlDr(): HasMany
    {
        return $this->hasMany(RawatJlDr::class, 'no_rawat', 'no_rawat');
    }

    public function rawatJlPr(): HasMany
    {
        return $this->hasMany(RawatJlPr::class, 'no_rawat', 'no_rawat');
    }

    public function rawatJlDrPr(): HasMany
    {
        return $this->hasMany(RawatJlDrPr::class, 'no_rawat', 'no_rawat');
    }

    public function rawatInapDr(): HasMany
    {
        return $this->hasMany(RawatInapDr::class, 'no_rawat', 'no_rawat');
    }

    public function rawatInapPr(): HasMany
    {
        return $this->hasMany(RawatInapPr::class, 'no_rawat', 'no_rawat');
    }

    public function rawatInapDrPr(): HasMany
    {
        return $this->hasMany(RawatInapDrPr::class, 'no_rawat', 'no_rawat');
    }

    // ========== SATU SEHAT RELATIONSHIPS ==========

    public function satuSehatEncounter(): HasOne
    {
        return $this->hasOne(\App\Models\SatuSehat\SatuSehatEncounter::class, 'local_id', 'no_rawat');
    }

    public function satuSehatBundle(): HasOne
    {
        return $this->hasOne(\App\Models\SatuSehat\SatuSehatBundle::class, 'no_rawat', 'no_rawat')->latestOfMany();
    }

    // ========== SCOPES ==========

    public function scopeSearch($query, ?string $search)
    {
        if (!$search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('no_rawat', 'like', "%{$search}%")
                ->orWhere('no_rkm_medis', 'like', "%{$search}%")
                ->orWhereHas('pasien', fn($p) => $p->where('nm_pasien', 'like', "%{$search}%"))
                ->orWhereHas('bridgingSep', fn($s) => $s->where('no_sep', 'like', "%{$search}%"));
        });
    }

    public function scopeFilterPoli($query, ?string $kdPoli)
    {
        if (!$kdPoli) {
            return $query;
        }

        return $query->where('kd_poli', $kdPoli);
    }

    public function scopeFilterStatus($query, ?string $status)
    {
        if (!$status) {
            return $query;
        }

        return $query->where('stts', $status);
    }

    public function scopeFilterStatusLanjut($query, ?string $statusLanjut)
    {
        if (!$statusLanjut) {
            return $query;
        }

        return $query->where('status_lanjut', $statusLanjut);
    }

    public function scopeFilterPenjab($query, ?string $kdPj)
    {
        if (!$kdPj) {
            return $query;
        }

        return $query->where('kd_pj', $kdPj);
    }

    public function scopeFilterTanggal($query, ?string $tanggal)
    {
        if (!$tanggal) {
            return $query;
        }

        return $query->whereDate('tgl_registrasi', $tanggal);
    }

    public function scopeFilterTanggalRange($query, ?string $tglMulai, ?string $tglSelesai)
    {
        if ($tglMulai && $tglSelesai) {
            return $query->whereBetween('tgl_registrasi', [$tglMulai, $tglSelesai]);
        }

        if ($tglMulai) {
            return $query->whereDate('tgl_registrasi', '>=', $tglMulai);
        }

        if ($tglSelesai) {
            return $query->whereDate('tgl_registrasi', '<=', $tglSelesai);
        }

        return $query;
    }

    public function scopeBpjsOnly($query)
    {
        $raw = ConfigurationHelper::get('bpjs.default_codes');
        $codes = $raw
            ? array_filter(array_map('trim', explode(',', $raw)))
            : config('bpjs.default_codes', []);

        return $query->whereIn('kd_pj', $codes);
    }

    // ========== ACCESSORS ==========

    public function getStatusLabelAttribute(): string
    {
        return match ($this->stts) {
            'Belum' => 'Belum',
            'Sudah' => 'Sudah',
            'Batal' => 'Batal',
            'Berubah' => 'Berubah',
            'Dirujuk' => 'Dirujuk',
            'Meninggal' => 'Meninggal',
            'Dirawat' => 'Dirawat',
            'Pulang Paksa' => 'Pulang Paksa',
            default => $this->stts ?? '-',
        };
    }

    public function getStatusLanjutLabelAttribute(): string
    {
        return match ($this->status_lanjut) {
            'Ralan' => 'Rawat Jalan',
            'Ranap' => 'Rawat Inap',
            default => $this->status_lanjut ?? '-',
        };
    }
}
