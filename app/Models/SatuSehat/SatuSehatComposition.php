<?php

namespace App\Models\SatuSehat;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SatuSehatComposition extends BaseModel
{
    const TYPE_RESUME_RALAN = 'resume_ralan';
    const TYPE_RESUME_RANAP = 'resume_ranap';
    const TYPE_CATATAN_GIZI = 'catatan_gizi';
    const TYPE_RESUME_KEPERAWATAN_RALAN = 'resume_keperawatan_ralan';
    const TYPE_RESUME_KEPERAWATAN_RANAP = 'resume_keperawatan_ranap';
    const TYPE_RESUME_FARMASI = 'resume_farmasi';

    protected $table = 'satu_sehat_compositions';

    protected $fillable = [
        'ihs_number',
        'identifier',
        'local_id',
        'composition_type',
        'patient_ihs',
        'encounter_ihs',
        'author_ihs',
        'status',
        'type_code',
        'type_display',
        'title',
        'date',
        'custodian_ihs',
        'raw_response',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'datetime',
            'raw_response' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(SatuSehatPatient::class, 'patient_ihs', 'ihs_number');
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(SatuSehatEncounter::class, 'encounter_ihs', 'ihs_number');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(SatuSehatPractitioner::class, 'author_ihs', 'ihs_number');
    }

    public static function findByIhs(string $ihsNumber): ?self
    {
        return static::where('ihs_number', $ihsNumber)->first();
    }
}
