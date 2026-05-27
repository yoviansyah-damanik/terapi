<?php

namespace App\Models\SatuSehat;

use App\Models\BaseModel;

class SatuSehatDocumentReference extends BaseModel
{
    const TYPE_PRESCRIPTION = 'prescription';

    protected $table = 'satu_sehat_document_references';

    protected $fillable = [
        'ihs_number',
        'local_id',
        'doc_type',
        'patient_ihs',
        'encounter_ihs',
        'author_ihs',
        'status',
        'doc_status',
        'raw_response',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_response' => 'array',
            'synced_at'    => 'datetime',
        ];
    }
}
