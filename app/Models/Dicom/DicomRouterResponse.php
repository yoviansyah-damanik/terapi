<?php

namespace App\Models\Dicom;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DicomRouterResponse extends BaseModel
{
    protected $table = 'dicom_router_responses';

    protected $fillable = [
        'dicom_study_id',
        'accession_number',
        'imaging_study_ihs',
        'study_instance_uid',
        'stage',
        'status',
        'message',
        'errors',
        'raw_payload',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'status'      => 'boolean',
            'errors'      => 'array',
            'raw_payload' => 'array',
        ];
    }

    public function dicomStudy(): BelongsTo
    {
        return $this->belongsTo(DicomStudy::class);
    }
}
