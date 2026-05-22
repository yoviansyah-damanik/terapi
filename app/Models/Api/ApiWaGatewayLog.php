<?php

namespace App\Models\Api;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiWaGatewayLog extends BaseModel
{
    protected $table = 'api_wa_gateway_logs';

    protected $fillable = [
        'api_user_id',
        'api_user_name',
        'ip_address',
        'gateway',
        'action',
        'phone_number',
        'message_preview',
        'message_id',
        'response_status',
        'response_time_ms',
        'success',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
        ];
    }

    const ACTIONS = [
        'send_text' => 'Kirim Teks',
        'send_image' => 'Kirim Gambar',
        'send_file' => 'Kirim File',
        'send_video' => 'Kirim Video',
        'send_audio' => 'Kirim Audio',
        'send_location' => 'Kirim Lokasi',
        'send_contact' => 'Kirim Kontak',
        'send_link' => 'Kirim Link',
        'send_poll' => 'Kirim Polling',
        'check_user' => 'Cek User',
        'get_status' => 'Status Gateway',
        'webhook' => 'Webhook Masuk',
        'broadcast' => 'Broadcast',
    ];

    public function apiUser(): BelongsTo
    {
        return $this->belongsTo(ApiUser::class);
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('phone_number', 'like', "%{$search}%")
                ->orWhere('message_preview', 'like', "%{$search}%")
                ->orWhere('api_user_name', 'like', "%{$search}%")
                ->orWhere('message_id', 'like', "%{$search}%");
        });
    }

    public function scopeForGateway(Builder $query, string $gateway): Builder
    {
        return $query->where('gateway', $gateway);
    }

    public function scopeForAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    public function scopeForSuccess(Builder $query, string $val): Builder
    {
        return $val === 'yes' ? $query->where('success', true) : $query->where('success', false);
    }

    public function getActionLabelAttribute(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }

    public function getStatusColorAttribute(): string
    {
        if (is_null($this->success))
            return 'zinc';
        return $this->success ? 'green' : 'red';
    }

    public function getGatewayLabelAttribute(): string
    {
        return strtoupper($this->gateway);
    }

    public function getGatewayColorAttribute(): string
    {
        return $this->gateway === 'waha' ? 'lime' : 'emerald';
    }
}
