<?php

namespace App\Models\Simrs;

/** Master kelompok umur pasien (tabel `kelompok_umur` di SIMRS) */
class KelompokUmur extends SimrsModel
{
    protected $table = 'kelompok_umur';

    protected $primaryKey = 'kode';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'kode',
        'nama',
        'umur_min',
        'umur_max',
        'urut',
    ];

    protected $casts = [
        'umur_min' => 'integer',
        'umur_max' => 'integer',
        'urut'     => 'integer',
    ];

    /** Label rentang umur dalam format yang mudah dibaca */
    public function getRentangLabelAttribute(): string
    {
        return $this->getRawOriginal('umur_max') === null
            ? "≥ {$this->umur_min} hari"
            : "{$this->umur_min} – {$this->umur_max} hari";
    }

    /** Temukan kelompok umur dari DB berdasarkan umur dalam hari (satu pasien) */
    public static function findByDays(int $days): ?self
    {
        return static::where('umur_min', '<=', $days)
            ->where(fn($q) => $q->whereNull('umur_max')->orWhere('umur_max', '>=', $days))
            ->orderBy('urut')
            ->first();
    }

    /** Cocokkan kelompok umur dari collection yang sudah di-load (bulk, menghindari N+1) */
    public static function matchFromCollection(\Illuminate\Support\Collection $kelompoks, int $days): ?self
    {
        return $kelompoks->first(
            fn($k) => $days >= $k->umur_min && ($k->umur_max === null || $days <= $k->umur_max)
        );
    }
}
