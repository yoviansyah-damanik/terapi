<?php

namespace App\Models\Mapping;

use App\Models\BaseModel;

/** Mapping departemen SIMRS ke tipe organisasi HL7 CodeSystem (organization-type) */
class OrganizationMap extends BaseModel
{
    protected $table = 'map_organization';

    protected $fillable = [
        'dep_id',
        'org_type_code',
        'org_type_term',
        'org_type_display',
    ];
}
