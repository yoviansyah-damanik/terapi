<?php

namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Pasien extends SimrsModel
{
    protected $table = 'pasien';

    protected $primaryKey = 'no_rkm_medis';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'no_rkm_medis',
        'nm_pasien',
        'no_ktp',
        'jk',
        'tmp_lahir',
        'tgl_lahir',
        'nm_ibu',
        'alamat',
        'gol_darah',
        'pekerjaan',
        'stts_nikah',
        'agama',
        'tgl_daftar',
        'no_tlp',
        'umur',
        'pnd',
        'keluarga',
        'namakeluarga',
        'kd_pj',
        'no_peserta',
        'kd_kel',
        'kd_kec',
        'kd_kab',
        'pekerjaanpj',
        'alamatpj',
        'kelurahanpj',
        'kecamatanpj',
        'kabupatenpj',
        'perusahaan_pasien',
        'suku_bangsa',
        'bahasa_pasien',
        'cacat_fisik',
        'email',
        'nip',
        'kd_prop',
        'propinsipj',
    ];

    protected function casts(): array
    {
        return [
            'tgl_lahir' => 'date',
            'tgl_daftar' => 'date',
        ];
    }

    // ========== ACCESSORS ==========

    public function getFullNameAttribute(): string
    {
        return $this->nm_pasien;
    }

    public function getGenderAttribute(): string
    {
        return $this->jk === 'L' ? 'Laki-laki' : 'Perempuan';
    }

    public function getGenderLabelAttribute(): string
    {
        return match ($this->jk) {
            'L' => 'Laki-laki',
            'P' => 'Perempuan',
            default => '-',
        };
    }

    public function getAgeAttribute(): ?string
    {
        if (!$this->tgl_lahir) {
            return null;
        }
        return $this->tgl_lahir->age . ' tahun';
    }

    public function getMaritalStatusLabelAttribute(): string
    {
        return match ($this->stts_nikah) {
            'MENIKAH' => 'Menikah',
            'BELUM MENIKAH' => 'Belum Menikah',
            'JANDA' => 'Janda',
            'DUDA' => 'Duda',
            default => $this->stts_nikah ?? '-',
        };
    }

    public function getBloodTypeLabelAttribute(): string
    {
        return $this->gol_darah ?: '-';
    }

    // ========== RELATIONSHIPS ==========

    public function penjab(): BelongsTo
    {
        return $this->belongsTo(Penjab::class, 'kd_pj', 'kd_pj');
    }

    public function sukuBangsaRelation(): BelongsTo
    {
        return $this->belongsTo(SukuBangsa::class, 'suku_bangsa', 'id');
    }

    public function bahasaPasienRelation(): BelongsTo
    {
        return $this->belongsTo(BahasaPasien::class, 'bahasa_pasien', 'id');
    }

    public function cacatFisikRelation(): BelongsTo
    {
        return $this->belongsTo(CacatFisik::class, 'cacat_fisik', 'id');
    }

    public function satuSehatPatient(): HasOne
    {
        return $this->hasOne(\App\Models\SatuSehat\SatuSehatPatient::class, 'nik', 'no_ktp');
    }

    public function tni(): HasOne
    {
        return $this->hasOne(PasienTni::class, 'no_rkm_medis', 'no_rkm_medis');
    }

    public function polri(): HasOne
    {
        return $this->hasOne(PasienPolri::class, 'no_rkm_medis', 'no_rkm_medis');
    }

    // ========== SCOPES ==========

    public function scopeSearch($query, ?string $search)
    {
        if (!$search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('no_rkm_medis', 'like', "%{$search}%")
                ->orWhere('nm_pasien', 'like', "%{$search}%")
                ->orWhere('no_ktp', 'like', "%{$search}%")
                ->orWhere('no_tlp', 'like', "%{$search}%")
                ->orWhere('alamat', 'like', "%{$search}%");
        });
    }

    public function scopeGender($query, ?string $gender)
    {
        if (!$gender) {
            return $query;
        }
        return $query->where('jk', $gender);
    }
}
