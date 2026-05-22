<?php

namespace App\Models\SatuSehat;

use App\Models\BaseModel;

class SatuSehatImagingStudy extends BaseModel
{
    protected $table = 'satu_sehat_imaging_studies';

    protected $fillable = [
        'ihs_number',
        'local_id',
        'identifier',
        'patient_ihs',
        'encounter_ihs',
        'status',
        'modality_code',
        'modality_display',
        'body_site_code',
        'body_site_display',
        'description',
        'started_at',
        'raw_response',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'raw_response' => 'array',
            'synced_at' => 'datetime',
        ];
    }
}
