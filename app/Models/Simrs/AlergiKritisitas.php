<?php

namespace App\Models\Simrs;

/** Kritisitas alergi dari SIMRS (tabel `alergi_kritisitas`) */
class AlergiKritisitas extends SimrsModel
{
    protected $table = 'alergi_kritisitas';

    protected $fillable = ['kritisitas', 'deskripsi'];
}
