<?php

namespace App\Models\Dicom;

use App\Models\BaseModel;

class DicomModality extends BaseModel
{
    protected $table = 'dicom_modalities';

    protected $fillable = [
        'router_id',
        'ae_title',
        'description',
        'ip_address',
        'port',
        'modality_type',
        'manufacturer',
        'allow_worklist',
        'is_active',
        'notes',
    ];

    public function router()
    {
        return $this->belongsTo(DicomRouter::class, 'router_id');
    }

    protected function casts(): array
    {
        return [
            'allow_worklist' => 'boolean',
            'is_active' => 'boolean',
            'port' => 'integer',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeWorklist($query)
    {
        return $query->where('allow_worklist', true)->where('is_active', true);
    }
}
