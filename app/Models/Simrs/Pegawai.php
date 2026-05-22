<?php

namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pegawai extends SimrsModel
{
    protected $table = 'pegawai';

    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'nik',
        'nama',
        'jk',
        'jbtn',
        'jnj_jabatan',
        'kode_kelompok',
        'kode_resiko',
        'kode_emergency',
        'departemen',
        'bidang',
        'stts_wp',
        'stts_kerja',
        'npwp',
        'pendidikan',
        'gapok',
        'tmp_lahir',
        'tgl_lahir',
        'alamat',
        'kota',
        'mulai_kerja',
        'ms_kerja',
        'indexins',
        'bpd',
        'rekening',
        'stts_aktif',
        'wajibmasuk',
        'pengurang',
        'indek',
        'mulai_kontrak',
        'cuti_diambil',
        'dankes',
        'photo',
        'no_ktp',
    ];

    protected function casts(): array
    {
        return [
            'tgl_lahir' => 'date',
            'mulai_kerja' => 'date',
            'mulai_kontrak' => 'date',
            'gapok' => 'decimal:2',
        ];
    }

    // ========== ACCESSORS ==========

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

    public function getStatusAktifLabelAttribute(): string
    {
        return match ($this->stts_aktif) {
            'AKTIF' => 'Aktif',
            'CUTI' => 'Cuti',
            'KELUAR' => 'Keluar',
            'PENSIUN' => 'Pensiun',
            default => $this->stts_aktif ?? '-',
        };
    }

    public function getMasaKerjaAttribute(): ?string
    {
        if (!$this->mulai_kerja) {
            return null;
        }
        $years = $this->mulai_kerja->diffInYears(now());
        $months = $this->mulai_kerja->diffInMonths(now()) % 12;

        if ($years > 0) {
            return $years . ' tahun ' . $months . ' bulan';
        }
        return $months . ' bulan';
    }

    // ========== RELATIONSHIPS ==========

    public function jabatanRelation(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class, 'jbtn', 'kd_jbtn');
    }

    public function kelompokJabatanRelation(): BelongsTo
    {
        return $this->belongsTo(KelompokJabatan::class, 'kode_kelompok', 'kode_kelompok');
    }

    public function resikoKerjaRelation(): BelongsTo
    {
        return $this->belongsTo(ResikoKerja::class, 'kode_resiko', 'kode_resiko');
    }

    public function emergencyIndexRelation(): BelongsTo
    {
        return $this->belongsTo(EmergencyIndex::class, 'kode_emergency', 'kode_emergency');
    }

    public function departemenRelation(): BelongsTo
    {
        return $this->belongsTo(Departemen::class, 'departemen', 'dep_id');
    }

    public function bidangRelation(): BelongsTo
    {
        return $this->belongsTo(Bidang::class, 'bidang', 'nama');
    }

    public function statusWpRelation(): BelongsTo
    {
        return $this->belongsTo(StatusWp::class, 'stts_wp', 'stts');
    }

    public function statusKerjaRelation(): BelongsTo
    {
        return $this->belongsTo(StatusKerja::class, 'stts_kerja', 'stts');
    }

    public function pendidikanRelation(): BelongsTo
    {
        return $this->belongsTo(Pendidikan::class, 'pendidikan', 'tingkat');
    }

    public function indexInsRelation(): BelongsTo
    {
        return $this->belongsTo(IndexIns::class, 'indexins', 'dep_id');
    }

    public function bankRelation(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'bpd', 'namabank');
    }

    // ========== SCOPES ==========

    public function scopeSearch($query, ?string $search)
    {
        if (!$search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('id', 'like', "%{$search}%")
                ->orWhere('nik', 'like', "%{$search}%")
                ->orWhere('nama', 'like', "%{$search}%")
                ->orWhere('no_ktp', 'like', "%{$search}%")
                ->orWhere('jbtn', 'like', "%{$search}%");
        });
    }

    public function scopeGender($query, ?string $gender)
    {
        if (!$gender) {
            return $query;
        }
        return $query->where('jk', $gender);
    }

    public function scopeStatusAktif($query, ?string $status)
    {
        if (!$status) {
            return $query;
        }
        return $query->where('stts_aktif', $status);
    }

    public function scopeStatusKerja($query, ?string $status)
    {
        if (!$status) {
            return $query;
        }
        return $query->where('stts_kerja', $status);
    }

    public function scopeDepartemen($query, ?string $departemen)
    {
        if (!$departemen) {
            return $query;
        }
        return $query->where('departemen', $departemen);
    }
}
