<?php

namespace App\Models\SatuSehat;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SatuSehatBundleItem extends BaseModel
{
    protected $table = 'satu_sehat_bundle_items';

    protected $fillable = [
        'bundle_log_id',
        'resource_type',
        'local_id',
        'ihs_id',
        'status',
        'payload',
        'response',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'response' => 'array',
        ];
    }

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(SatuSehatBundle::class, 'bundle_log_id');
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'success' => 'green',
            'failed'  => 'red',
            'skipped' => 'zinc',
            'sending' => 'blue',
            'warning' => 'amber',
            default   => 'zinc',
        };
    }
}
