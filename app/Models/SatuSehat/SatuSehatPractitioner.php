<?php

namespace App\Models\SatuSehat;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SatuSehatPractitioner extends BaseModel
{
    protected $table = 'satu_sehat_practitioners';

    protected $fillable = [
        'ihs_number',
        'nik',
        'name',
        'gender',
        'birth_date',
        'phone',
        'email',
        'qualification',
        'raw_response',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'qualification' => 'array',
            'raw_response' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function encountersAsAttender(): HasMany
    {
        return $this->hasMany(SatuSehatEncounter::class, 'practitioner_ihs', 'ihs_number');
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
