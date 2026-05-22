<?php

namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Model;

/**
 * Base model untuk tabel-tabel di database SIMRS
 *
 * Semua model yang mengakses database SIMRS harus extend class ini.
 * Koneksi 'simrs' didefinisikan di config/database.php
 */
abstract class SimrsModel extends Model
{
    protected $connection = 'simrs';

    public $timestamps = false;
}
