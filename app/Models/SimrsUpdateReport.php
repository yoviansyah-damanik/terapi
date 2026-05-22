<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;

class SimrsUpdateReport extends BaseModel
{
    protected $fillable = [
        'api_user_id',
        'api_user_name',
        'ip_address',
        'host_name',
        'app_name',
        'from_version',
        'to_version',
        'status',
        'error_message',
        'duration_seconds',
    ];

    public function scopeSuccess(Builder $query): Builder
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->whereIn('status', ['failed', 'rollback']);
    }
}
