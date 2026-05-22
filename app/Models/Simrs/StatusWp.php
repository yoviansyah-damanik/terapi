<?php

namespace App\Models\Simrs;

class StatusWp extends SimrsModel
{
    protected $table = 'stts_wp';

    protected $primaryKey = 'stts';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'stts',
        'ktg',
    ];
}
