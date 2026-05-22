<?php

namespace App\Models\SatuSehat;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SatuSehatPatient extends BaseModel
{
    protected $table = 'satu_sehat_patients';

    protected $fillable = [
        'ihs_number',
        'nik',
        'name',
        'gender',
        'birth_date',
        'phone',
        'email',
        'address',
        'city',
        'province',
        'postal_code',
        'raw_response',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'raw_response' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function encounters(): HasMany
    {
        return $this->hasMany(SatuSehatEncounter::class, 'patient_ihs', 'ihs_number');
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(SatuSehatCondition::class, 'patient_ihs', 'ihs_number');
    }

    public function observations(): HasMany
    {
        return $this->hasMany(SatuSehatObservation::class, 'patient_ihs', 'ihs_number');
    }

    public function medicationRequests(): HasMany
    {
        return $this->hasMany(SatuSehatMedicationRequest::class, 'patient_ihs', 'ihs_number');
    }

    public function allergyIntolerances(): HasMany
    {
        return $this->hasMany(SatuSehatAllergyIntolerance::class, 'patient_ihs', 'ihs_number');
    }

    public function immunizations(): HasMany
    {
        return $this->hasMany(SatuSehatImmunization::class, 'patient_ihs', 'ihs_number');
    }

    public static function findByIhs(string $ihsNumber): ?self
    {
        return static::where('ihs_number', $ihsNumber)->first();
    }

    public static function findByNik(string $nik): ?self
    {
        return static::where('nik', $nik)->first();
    }
}
