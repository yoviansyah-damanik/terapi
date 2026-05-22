<?php

namespace App\Models\Dicom;

use App\Models\BaseModel;

class DicomStudy extends BaseModel
{
    protected $table = 'dicom_studies';

    protected $fillable = [
        'no_rawat',
        'noorder',
        'orthanc_study_id',
        'imaging_study_ihs',
        'study_instance_uid',
        'patient_id',
        'modality',
        'study_description',
        'study_date',
        'ae_title',
        'series_count',
        'instance_count',
        'status',
        'sent_to_router_at',
        'router_job_id',
    ];

    protected function casts(): array
    {
        return [
            'study_date' => 'date',
            'sent_to_router_at' => 'datetime',
            'series_count' => 'integer',
            'instance_count' => 'integer',
        ];
    }

    public function scopeForOrder($query, string $noorder)
    {
        return $query->where('noorder', $noorder);
    }

    public function scopeForVisit($query, string $noRawat)
    {
        return $query->where('no_rawat', $noRawat);
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }
}
