<?php

namespace App\Models\Mapping;

use App\Models\BaseModel;

class HsServiceItem extends BaseModel
{
    protected $table = 'map_healthcare_service_items';

    protected $fillable = ['type', 'local_code', 'item_type', 'system_code', 'system_term', 'system_display'];

    public function serviceMap()
    {
        return $this->belongsTo(HealthcareServiceMap::class, 'local_code', 'local_code');
    }
}
