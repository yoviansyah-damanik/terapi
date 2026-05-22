<?php

namespace App\Models\Mapping;

use App\Models\BaseModel;

class DoctorMap extends BaseModel
{
    protected $table = 'map_doctor';

    protected $fillable = [
        'doctor_code',
        'system_code',
        'system_term',
        'system_display',
    ];
}
