<?php

namespace App\Services\SatuSehat\DTO;

use Carbon\Carbon;

/**
 * DTO untuk menyimpan data OAuth token dari SatuSehat
 */
class TokenData
{
    public function __construct(
        public readonly string $accessToken,
        public readonly string $tokenType,
        public readonly int $expiresIn,
        public readonly Carbon $issuedAt,
    ) {}

    public static function fromResponse(array $response): self
    {
        return new self(
            accessToken: $response['access_token'],
            tokenType: $response['token_type'] ?? 'Bearer',
            expiresIn: (int) ($response['expires_in'] ?? 3600),
            issuedAt: Carbon::now(),
        );
    }

    public function isExpired(int $bufferSeconds = 60): bool
    {
        $expiresAt = $this->issuedAt->copy()->addSeconds($this->expiresIn - $bufferSeconds);

        return Carbon::now()->gte($expiresAt);
    }

    public function getExpiresAt(): Carbon
    {
        return $this->issuedAt->copy()->addSeconds($this->expiresIn);
    }

    public function getRemainingSeconds(): int
    {
        return max(0, $this->getExpiresAt()->diffInSeconds(Carbon::now(), false) * -1);
    }

    public function toArray(): array
    {
        return [
            'access_token' => $this->accessToken,
            'token_type' => $this->tokenType,
            'expires_in' => $this->expiresIn,
            'issued_at' => $this->issuedAt->toIso8601String(),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            accessToken: $data['access_token'],
            tokenType: $data['token_type'],
            expiresIn: $data['expires_in'],
            issuedAt: Carbon::parse($data['issued_at']),
        );
    }
}
