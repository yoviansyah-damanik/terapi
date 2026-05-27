<?php

namespace App\Models\Mapping;

use App\Models\BaseModel;

class EmployeeSpecialtyMap extends BaseModel
{
    protected $table = 'map_employee_specialty';

    protected $fillable = ['employee_id', 'specialty_code', 'specialty_term', 'specialty_display'];
}
