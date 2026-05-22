<?php

namespace App\Models\Bpjs;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BpjsLog extends BaseModel
{
    protected $table = 'bpjs_logs';

    protected $fillable = [
        'user_id',
        'service',
        'method',
        'endpoint',
        'no_rawat',
        'no_sep',
        'status',
        'response_status',
        'response_time',
        'request_payload',
        'response_payload',
        'bundle',
        'error_message',
        'ip_address',
        'user_agent',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'response_time' => 'decimal:2',
            'sent_at' => 'datetime',
        ];
    }

    // ========== RELATIONSHIPS ==========

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ========== SCOPES ==========

    public function scopeForService(Builder $query, string $service): Builder
    {
        return $query->where('service', $service);
    }

    public function scopeForNoRawat(Builder $query, string $noRawat): Builder
    {
        return $query->where('no_rawat', $noRawat);
    }

    public function scopeSuccess(Builder $query): Builder
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    // ========== HELPERS ==========

    /**
     * Catat log pengiriman ke layanan BPJS.
     */
    public static function record(
        string $service,
        string $status,
        ?string $noRawat = null,
        ?string $noSep = null,
        ?string $method = null,
        ?string $endpoint = null,
        ?int $responseStatus = null,
        ?float $responseTime = null,
        ?array $requestPayload = null,
        ?array $responsePayload = null,
        mixed $bundle = null,
        ?string $errorMessage = null,
        bool $success = false,
    ): static {
        return static::create([
            'user_id' => auth()->id(),
            'service' => $service,
            'method' => $method,
            'endpoint' => $endpoint,
            'no_rawat' => $noRawat,
            'no_sep' => $noSep,
            'status' => $status,
            'response_status' => $responseStatus,
            'response_time' => $responseTime,
            'request_payload' => $requestPayload,
            'response_payload' => $responsePayload,
            'bundle' => $bundle,
            'error_message' => $errorMessage,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'sent_at' => $success ? now() : null,
        ]);
    }

    // ========== ACCESSORS ==========

    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            'success' => 'green',
            'failed' => 'red',
            default => 'amber',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'success' => 'Berhasil',
            'failed' => 'Gagal',
            default => 'Pending',
        };
    }
}
