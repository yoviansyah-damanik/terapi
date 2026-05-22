<?php

namespace App\Models\Mapping;

use App\Models\BaseModel;

/** Mapping alat kesehatan lokal → KFA Alkes (map_devices). */
class DeviceMap extends BaseModel
{
    protected $table = 'map_devices';

    protected $fillable = [
        'local_code',
        'kfa_code',
        'kfa_name',
        'system_url',
    ];
}
