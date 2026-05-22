<?php

namespace App\Models\Api;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;

class ApiSimrsLog extends BaseModel
{
    protected $table = 'api_simrs_logs';

    protected $fillable = [
        'error_id',
        'app_version',
        'host_name',
        'ip_address',
        'level',
        'category',
        'module',
        'message',
        'exception_class',
        'stack_trace',
        'simrs_user',
        'simrs_user_role',
        'db_host',
        'db_name',
        'db_connected',
        'db_response_time_ms',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'context'      => 'array',
            'db_connected' => 'boolean',
        ];
    }

    // ─── Kategori error Java yang umum ───

    const CATEGORIES = [
        'NullPointerException',
        'SQLException',
        'ConnectionException',
        'TimeoutException',
        'IOException',
        'AuthenticationException',
        'ValidationException',
        'ClassNotFoundException',
        'IllegalArgumentException',
        'RuntimeException',
        'OutOfMemoryError',
        'StackOverflowError',
        'NumberFormatException',
        'ArrayIndexOutOfBoundsException',
        'Other',
    ];

    const LEVELS = ['error', 'warning', 'info', 'debug'];

    const MODULES = [
        'pendaftaran',
        'rawat_jalan',
        'rawat_inap',
        'igd',
        'farmasi',
        'laboratorium',
        'radiologi',
        'kasir',
        'master_data',
        'laporan',
        'konfigurasi',
        'autentikasi',
        'other',
    ];

    // ─── Scopes ───

    public function scopeErrors(Builder $query): Builder
    {
        return $query->where('level', 'error');
    }

    public function scopeWarnings(Builder $query): Builder
    {
        return $query->where('level', 'warning');
    }

    public function scopeForLevel(Builder $query, string $level): Builder
    {
        return $query->where('level', $level);
    }

    public function scopeForCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeForModule(Builder $query, string $module): Builder
    {
        return $query->where('module', $module);
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('error_id', 'like', "%{$search}%")
                ->orWhere('message', 'like', "%{$search}%")
                ->orWhere('exception_class', 'like', "%{$search}%")
                ->orWhere('simrs_user', 'like', "%{$search}%")
                ->orWhere('category', 'like', "%{$search}%");
        });
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('created_at', today());
    }

    // ─── Helper statis untuk merekam log ───

    /**
     * Rekam log dari request API.
     */
    public static function record(array $data): static
    {
        return static::create([
            'error_id' => $data['error_id'] ?? null,
            'app_version' => $data['app_version'] ?? null,
            'host_name' => $data['host_name'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'level' => $data['level'] ?? 'error',
            'category' => $data['category'] ?? 'Other',
            'module' => $data['module'] ?? null,
            'message' => $data['message'],
            'exception_class' => $data['exception_class'] ?? null,
            'stack_trace' => $data['stack_trace'] ?? null,
            'simrs_user' => $data['simrs_user'] ?? null,
            'simrs_user_role' => $data['simrs_user_role'] ?? null,
            'db_host' => $data['db_host'] ?? null,
            'db_name' => $data['db_name'] ?? null,
            'db_connected' => $data['db_connected'] ?? null,
            'db_response_time_ms' => $data['db_response_time_ms'] ?? null,
            'context' => $data['context'] ?? null,
        ]);
    }

    // ─── Accessor ───

    public function getLevelColorAttribute(): string
    {
        return match ($this->level) {
            'error' => 'red',
            'warning' => 'amber',
            'info' => 'blue',
            'debug' => 'zinc',
            default => 'zinc',
        };
    }

    public function getLevelLabelAttribute(): string
    {
        return match ($this->level) {
            'error' => 'Error',
            'warning' => 'Warning',
            'info' => 'Info',
            'debug' => 'Debug',
            default => strtoupper($this->level),
        };
    }
}
