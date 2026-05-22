<?php

namespace App\Models\Simrs;

/** Tingkat keparahan alergi dari SIMRS (tabel `alergi_tingkat_keparahan`) */
class AlergiTingkatKeparahan extends SimrsModel
{
    protected $table = 'alergi_tingkat_keparahan';

    protected $fillable = ['keparahan', 'deskripsi'];
}
