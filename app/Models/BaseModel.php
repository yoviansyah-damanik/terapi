<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Base model untuk aplikasi
 *
 * Semua model utama harus extend class ini.
 * Menggunakan UUID sebagai primary key.
 */
abstract class BaseModel extends Model
{
    use HasUuids;

    protected $connection = 'mariadb';
}
