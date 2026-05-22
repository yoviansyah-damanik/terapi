<?php

namespace App\Models\SatuSehat;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycLog extends BaseModel
{
    protected $table = 'kyc_logs';

    protected $fillable = [
        'user_id',
        'agent_name',
        'agent_nik',
        'kyc_type',
        'patient_nik',
        'patient_name',
        'kyc_url',
        'challenge_code',
        'ihs_number',
        'expires_at',
        'is_success',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_success' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('is_success', true);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('is_success', false);
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeForType(Builder $query, string $type): Builder
    {
        return $query->where('kyc_type', $type);
    }

    /** Simpan log KYC dari controller/service */
    public static function record(array $data): static
    {
        return static::create(array_merge(['user_id' => auth()->id()], $data));
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->kyc_type) {
            'generate_url'   => 'Generate URL',
            'challenge_code' => 'Challenge Code',
            default          => ucfirst($this->kyc_type),
        };
    }

    public function getTypeBadgeColorAttribute(): string
    {
        return match ($this->kyc_type) {
            'generate_url'   => 'blue',
            'challenge_code' => 'purple',
            default          => 'zinc',
        };
    }
}
