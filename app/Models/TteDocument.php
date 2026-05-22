<?php

namespace App\Models;

use App\Models\Api\ApiUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TteDocument extends BaseModel
{
    protected $table = 'tte_documents';

    protected $fillable = [
        'source',
        'action',
        'nik',
        'mode',
        'file_count',
        'signed_files',
        'api_user_id',
        'api_user_name',
        'ip_address',
        'response_time_ms',
    ];

    protected function casts(): array
    {
        return [
            'signed_files' => 'array',
            'file_count' => 'integer',
            'response_time_ms' => 'integer',
        ];
    }

    public function apiUser(): BelongsTo
    {
        return $this->belongsTo(ApiUser::class);
    }

    public function scopeForSource(Builder $query, string $source): Builder
    {
        return $query->where('source', $source);
    }

    public function scopeForAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function ($q) use ($term) {
            $q->where('nik', 'like', "%{$term}%")
              ->orWhere('api_user_name', 'like', "%{$term}%")
              ->orWhere('ip_address', 'like', "%{$term}%");
        });
    }

    /** Simpan file-file bertanda tangan dan buat record. */
    public static function createFromApiResult(
        string $action,
        array $result,
        array $payload,
        Request $request,
        ?string $mode,
        int $startTime,
    ): self {
        $apiUser = $request->attributes->get('api_user');
        $signedFiles = self::storeSignedFiles($result['data']['file'] ?? [], $action);

        return self::create([
            'source'           => 'api',
            'action'           => $action,
            'nik'              => $payload['nik'] ?? null,
            'mode'             => $mode,
            'file_count'       => count($signedFiles),
            'signed_files'     => $signedFiles,
            'api_user_id'      => $apiUser?->id,
            'api_user_name'    => $apiUser?->name,
            'ip_address'       => $request->ip(),
            'response_time_ms' => intval((microtime(true) - $startTime) * 1000),
        ]);
    }

    /** Simpan file-file bertanda tangan dari simulasi dan buat record. */
    public static function createFromSimulation(
        string $action,
        ?string $nik,
        ?string $mode,
        array $result,
    ): self {
        $signedFiles = self::storeSignedFiles($result['data']['file'] ?? [], $action);

        return self::create([
            'source'       => 'simulation',
            'action'       => $action,
            'nik'          => $nik,
            'mode'         => $mode,
            'file_count'   => count($signedFiles),
            'signed_files' => $signedFiles,
        ]);
    }

    /**
     * Simpan array base64 PDF ke disk tte_signed, kembalikan array path.
     */
    private static function storeSignedFiles(array $files, string $action): array
    {
        if (empty($files)) {
            return [];
        }

        $paths = [];
        $year  = now()->format('Y');
        $month = now()->format('m');
        $id    = (string) \Illuminate\Support\Str::orderedUuid();

        foreach ($files as $index => $base64) {
            $binary = base64_decode($base64, strict: true);
            if ($binary === false) {
                continue;
            }
            $path = "{$year}/{$month}/{$id}_{$index}.pdf";
            Storage::disk('tte_signed')->put($path, $binary);
            $paths[] = $path;
        }

        return $paths;
    }

    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'sign_pdf' => 'Sign PDF',
            'seal_pdf' => 'Seal PDF',
            default    => $this->action,
        };
    }

    public function getModeLabel(): string
    {
        return match ($this->mode) {
            'tag'        => 'Tag',
            'coordinate' => 'Koordinat',
            'invisible'  => 'Invisible',
            default      => $this->mode ?? '—',
        };
    }
}
