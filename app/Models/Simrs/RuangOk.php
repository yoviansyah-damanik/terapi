<?php
namespace App\Models\Simrs;

class RuangOk extends SimrsModel
{
    protected $table = 'ruang_ok';
    protected $primaryKey = 'kd_ruang_ok';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'kd_ruang_ok',
        'nm_ruang_ok',
    ];
}
