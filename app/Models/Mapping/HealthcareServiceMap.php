<?php

namespace App\Models\Mapping;

use App\Models\BaseModel;

class HealthcareServiceMap extends BaseModel
{
    protected $table = 'map_healthcare_service';

    protected $fillable = [
        'type',
        'local_code',
        'physical_type_code',
        'physical_type_term',
        'physical_type_display',
    ];

    public function serviceItems()
    {
        return $this->hasMany(HsServiceItem::class, 'local_code', 'local_code')
            ->where('type', $this->type);
    }
}
