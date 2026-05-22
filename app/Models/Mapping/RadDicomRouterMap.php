<?php

namespace App\Models\Mapping;

use App\Models\BaseModel;
use App\Models\Dicom\DicomRouter;
use App\Models\Dicom\DicomModality;

class RadDicomRouterMap extends BaseModel
{
    protected $table = 'map_rad_dicom_router';

    protected $fillable = [
        'local_code',
        'router_id',
    ];

    public function router()
    {
        return $this->belongsTo(DicomRouter::class, 'router_id');
    }

    public function modality()
    {
        return $this->hasOne(DicomModality::class, 'router_id', 'router_id');
    }
}
