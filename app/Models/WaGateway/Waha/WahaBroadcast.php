<?php

namespace App\Models\WaGateway\Waha;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WahaBroadcast extends BaseModel
{
    protected $fillable = [
        'name',
        'message',
        'type',
        'file_path',
        'file_name',
        'total_recipients',
        'sent_count',
        'failed_count',
        'status',
        'created_by',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at'   => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(WahaBroadcastRecipient::class, 'broadcast_id');
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', 'processing');
    }

    /**
     * Hitung ulang counter sent/failed dari recipients
     */
    public function recalculateCounts(): void
    {
        $this->update([
            'sent_count' => $this->recipients()->where('status', 'sent')->count(),
            'failed_count' => $this->recipients()->where('status', 'failed')->count(),
        ]);
    }
}
