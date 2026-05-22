<?php

namespace App\Models\Simrs;

class Jabatan extends SimrsModel
{
    protected $table = 'jabatan';

    protected $primaryKey = 'kd_jbtn';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'kd_jbtn',
        'nm_jbtn',
    ];
}
