<?php

namespace App\Models\SatuSehat;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SatuSehatBundleLog extends BaseModel
{
    protected $table = 'satu_sehat_bundle_logs';

    protected $fillable = [
        'bundle_id',
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
            'payload'  => 'array',
            'response' => 'array',
        ];
    }

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(SatuSehatBundle::class, 'bundle_id');
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'success' => 'green',
            'failed'  => 'red',
            'skipped' => 'zinc',
            'warning' => 'amber',
            default   => 'zinc',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'success' => 'Terkirim',
            'failed'  => 'Gagal',
            'skipped' => 'Dilewati',
            'warning' => 'Peringatan',
            default   => $this->status,
        };
    }
}
