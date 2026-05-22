<?php

namespace App\Models\Dicom;

use App\Models\BaseModel;

class DicomRouter extends BaseModel
{
    protected $table = 'dicom_routers';

    protected $fillable = [
        'name',
        'ae_title',
        'host',
        'port',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'port' => 'integer',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function modality()
    {
        return $this->belongsTo(DicomModality::class, 'id', 'router_id');
    }
}
