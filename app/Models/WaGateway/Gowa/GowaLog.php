<?php

namespace App\Models\WaGateway\Gowa;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;

class GowaLog extends BaseModel
{
    protected $fillable = [
        'direction',
        'phone',
        'type',
        'payload',
        'response',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'payload'  => 'array',
            'response' => 'array',
        ];
    }

    public function scopeIncoming(Builder $query): Builder
    {
        return $query->where('direction', 'incoming');
    }

    public function scopeOutgoing(Builder $query): Builder
    {
        return $query->where('direction', 'outgoing');
    }

    /**
     * Catat log pengiriman pesan ke GOWA API
     */
    public static function logOutgoing(string $phone, string $type, array $payload, array $response, bool $success): self
    {
        return static::create([
            'direction' => 'outgoing',
            'phone' => $phone,
            'type' => $type,
            'payload' => $payload,
            'response' => $response,
            'status' => $success ? 'success' : 'failed',
            'error_message' => $success ? null : ($response['message'] ?? $response['error'] ?? 'Unknown error'),
        ]);
    }

    /**
     * Catat log pesan masuk dari webhook GOWA
     */
    public static function logIncoming(string $phone, string $type, array $payload): self
    {
        return static::create([
            'direction' => 'incoming',
            'phone' => $phone,
            'type' => $type,
            'payload' => $payload,
            'status' => 'success',
        ]);
    }
}
