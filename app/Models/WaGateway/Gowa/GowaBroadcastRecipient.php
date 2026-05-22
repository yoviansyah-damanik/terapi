<?php

namespace App\Models\WaGateway\Gowa;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GowaBroadcastRecipient extends BaseModel
{
    protected $fillable = [
        'broadcast_id',
        'phone',
        'status',
        'error_message',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(GowaBroadcast::class, 'broadcast_id');
    }
}
