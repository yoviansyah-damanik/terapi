<?php

namespace App\Models\Api;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApiUser extends BaseModel
{
    const SCOPES = [
        [
            'key'         => 'hospital',
            'label'       => 'Informasi RS',
            'description' => 'Akses informasi identitas dan layanan rumah sakit',
            'color'       => 'cyan',
            'icon'        => 'building-office-2',
            'sort_order'  => 1,
        ],
        [
            'key'         => 'whatsapp-gateway',
            'label'       => 'WhatsApp Gateway',
            'description' => 'Akses ke WhatsApp Gateway',
            'color'       => 'green',
            'icon'        => 'whatsapp',
            'sort_order'  => 2,
        ],
        [
            'key'         => 'tte',
            'label'       => 'TTE',
            'description' => 'Akses ke TTE',
            'color'       => 'blue',
            'icon'        => 'key',
            'sort_order'  => 3,
        ],
        [
            'key'         => 'simrs',
            'label'       => 'SIMRS',
            'description' => 'Akses ke SIMRS',
            'color'       => 'red',
            'icon'        => 'computer-desktop',
            'sort_order'  => 4,
        ],
        [
            'key'         => 'qrcode',
            'label'       => 'QR Code',
            'description' => 'Akses ke generator QR Code',
            'color'       => 'violet',
            'icon'        => 'qr-code',
            'sort_order'  => 5,
        ],
        [
            'key'         => 'ai',
            'label'       => 'AI Provider',
            'description' => 'Akses pengiriman prompt AI sentral',
            'color'       => 'emerald',
            'icon'        => 'cpu-chip',
            'sort_order'  => 6,
        ],
        [
            'key'         => 'dicom',
            'label'       => 'DICOM / PACS',
            'description' => 'Akses pengiriman worklist DICOM ke PACS',
            'color'       => 'violet',
            'icon'        => 'photo',
            'sort_order'  => 7,
        ],
    ];
    const SCOPE_HOSPITAL          = 'hospital';
    const SCOPE_WHATSAPP_GATEWAY  = 'whatsapp-gateway';
    const SCOPE_TTE               = 'tte';
    const SCOPE_SIMRS             = 'simrs';
    const SCOPE_QRCODE            = 'qrcode';
    const SCOPE_AI                = 'ai';
    const SCOPE_DICOM             = 'dicom';

    /** Ambil semua scope sebagai map key => [label, description, color, icon] dari konstanta SCOPES */
    public static function scopesMap(): array
    {
        return collect(static::SCOPES)->keyBy('key')->all();
    }

    protected $fillable = [
        'name',
        'username',
        'password',
        'scopes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'password'      => 'hashed',
            'is_active'     => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /** Selalu kembalikan array, meski nilai di DB bukan JSON valid. */
    public function getScopesAttribute(mixed $value): array
    {
        if (is_array($value)) return $value;
        $decoded = json_decode($value ?? '[]', true);
        return is_array($decoded) ? $decoded : [];
    }

    /** Serialisasi ke JSON saat menyimpan. */
    public function setScopesAttribute(mixed $value): void
    {
        $this->attributes['scopes'] = json_encode(is_array($value) ? $value : []);
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes);
    }
}
