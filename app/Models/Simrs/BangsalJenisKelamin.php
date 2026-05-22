<?php

namespace App\Models\Simrs;

class BangsalJenisKelamin extends SimrsModel
{
    protected $table = 'bangsal_per_jenis_kelamin';

    public $timestamps = false;

    protected $fillable = ['kd_bangsal', 'jenis_kelamin'];

    public $incrementing = false;

    protected $primaryKey = 'kd_bangsal';
    protected $keyType = 'string';
}
