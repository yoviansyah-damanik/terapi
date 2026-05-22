<?php

namespace App\Models\Dicom;

use Illuminate\Database\Eloquent\Model;

class Worklist extends Model
{
    protected $table = 'worklists';
    protected $primaryKey = 'accession_number';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'accession_number',
        'dicom_acsn',
        'noorder',
        'procedure_code',
        'no_rawat',
        'patient_id',
        'patient_name',
        'birth_date',
        'gender',
        'modality',
        'ae_title',
        'procedure_desc',
        'scheduled_date',
        'study_instance_uid',
        'orthanc_study_id',
        'imaging_study_ihs',
        'series_count',
        'instance_count',
        'sent_to_router_at',
        'router_job_id',
        'status',
        'error_message',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'scheduled_date' => 'datetime',
        'sent_to_router_at' => 'datetime',
    ];
}
