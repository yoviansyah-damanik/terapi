<?php

namespace App\Models\Simrs;

class Departemen extends SimrsModel
{
    protected $table = 'departemen';

    protected $primaryKey = 'dep_id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'dep_id',
        'nama',
    ];
}
