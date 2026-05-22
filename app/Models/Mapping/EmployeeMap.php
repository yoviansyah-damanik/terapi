<?php

namespace App\Models\Mapping;

use App\Models\BaseModel;

class EmployeeMap extends BaseModel
{
    protected $table = 'map_employee';

    protected $fillable = [
        'employee_id',
        'system_code',
        'system_term',
        'system_display',
    ];
}
