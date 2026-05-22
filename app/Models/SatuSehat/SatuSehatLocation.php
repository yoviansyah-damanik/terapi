<?php

namespace App\Models\SatuSehat;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SatuSehatLocation extends BaseModel
{
    protected $table = 'satu_sehat_locations';

    protected $fillable = [
        'ihs_number',
        'identifier',
        'name',
        'type',
        'status',
        'managing_organization',
        'raw_response',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_response' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    /** Ambil nilai telecom dari raw_response berdasarkan system (phone/email/url) */
    public function getTelecom(string $system): ?string
    {
        $telecom = collect($this->raw_response['telecom'] ?? []);
        return $telecom->firstWhere('system', $system)['value'] ?? null;
    }

    /** Ambil data posisi dari raw_response */
    public function getPosition(): array
    {
        return $this->raw_response['position'] ?? [];
    }

    /** Ambil kode physical type dari raw_response */
    public function getPhysicalTypeCode(): ?string
    {
        return $this->raw_response['physicalType']['coding'][0]['code'] ?? null;
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(SatuSehatOrganization::class, 'managing_organization', 'ihs_number');
    }

    public function encounters(): HasMany
    {
        return $this->hasMany(SatuSehatEncounter::class, 'location_ihs', 'ihs_number');
    }

    public static function findByIhs(string $ihsNumber): ?self
    {
        return static::where('ihs_number', $ihsNumber)->first();
    }
}
