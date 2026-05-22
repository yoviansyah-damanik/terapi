<?php

namespace App\Models\SatuSehat;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SatuSehatOrganization extends BaseModel
{
    protected $table = 'satu_sehat_organizations';

    protected $fillable = [
        'ihs_number',
        'identifier',
        'name',
        'status',
        'part_of',
        'raw_response',
        'synced_at',
    ];

    /** Ambil nilai telecom dari raw_response berdasarkan system (phone/email/url) */
    public function getTelecom(string $system): ?string
    {
        $telecom = collect($this->raw_response['telecom'] ?? []);
        return $telecom->firstWhere('system', $system)['value'] ?? null;
    }

    /** Ambil objek address pertama dari raw_response */
    public function getAddress(): array
    {
        return $this->raw_response['address'][0] ?? [];
    }

    protected function casts(): array
    {
        return [
            'raw_response' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    /** Accessor agar kode blade dapat memakai $org->active sebagai boolean */
    public function getActiveAttribute(): bool
    {
        return $this->status === 'active';
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'part_of', 'ihs_number');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'part_of', 'ihs_number');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(SatuSehatLocation::class, 'managing_organization', 'ihs_number');
    }

    public static function findByIhs(string $ihsNumber): ?self
    {
        return static::where('ihs_number', $ihsNumber)->first();
    }
}
