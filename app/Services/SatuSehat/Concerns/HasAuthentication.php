<?php

namespace App\Services\SatuSehat\Concerns;

use App\Exceptions\SatuSehat\AuthenticationException;
use App\Helpers\ConfigurationHelper;
use App\Models\SatuSehat\SatuSehatLog;
use App\Services\SatuSehat\DTO\TokenData;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Trait untuk menangani autentikasi OAuth2 SatuSehat
 */
trait HasAuthentication
{
    protected ?TokenData $token = null;

    protected function getAccessToken(): string
    {
        $token = $this->getCachedToken();

        if ($token && !$token->isExpired($this->getBufferSeconds())) {
            return $token->accessToken;
        }

        return $this->requestNewToken()->accessToken;
    }

    protected function getCachedToken(): ?TokenData
    {
        if ($this->token && !$this->token->isExpired($this->getBufferSeconds())) {
            return $this->token;
        }

        $cached = Cache::get($this->getCacheKey());
        if ($cached) {
            $this->token = TokenData::fromArray($cached);

            if (!$this->token->isExpired($this->getBufferSeconds())) {
                return $this->token;
            }
        }

        return null;
    }

    protected function requestNewToken(): TokenData
    {
        $clientId = ConfigurationHelper::get('satusehat.client_id') ?? config('satusehat.client_id');
        $clientSecret = ConfigurationHelper::get('satusehat.client_secret') ?? config('satusehat.client_secret');

        if (empty($clientId) || empty($clientSecret)) {
            throw AuthenticationException::missingCredentials();
        }

        $startTime = microtime(true);
        $endpoint = $this->getAuthUrl() . '/accesstoken?grant_type=client_credentials';

        try {
            $response = Http::asForm()
                ->timeout($this->getTimeout())
                ->post($endpoint, [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                ]);

            $responseTime = (microtime(true) - $startTime) * 1000;

            if (!$response->successful()) {
                $body = $response->json() ?? [];

                $this->logAuthRequest($endpoint, $response->status(), $body, $responseTime, false, $body['error_description'] ?? 'Gagal mendapatkan token');

                throw new AuthenticationException(
                    message: $body['error_description'] ?? 'Gagal mendapatkan token',
                    code: $response->status(),
                    responseBody: $body,
                );
            }

            $this->token = TokenData::fromResponse($response->json());
            $this->cacheToken($this->token);

            $this->logAuthRequest($endpoint, $response->status(), ['expires_in' => $this->token->expiresIn], $responseTime, true);

            if (config('satusehat.debug')) {
                Log::debug('SatuSehat: Token baru berhasil didapatkan', [
                    'expires_in' => $this->token->expiresIn,
                ]);
            }

            return $this->token;
        } catch (AuthenticationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $responseTime = (microtime(true) - $startTime) * 1000;
            $this->logAuthRequest($endpoint, null, null, $responseTime, false, $e->getMessage());

            Log::error('SatuSehat OAuth Error: ' . $e->getMessage());
            throw new AuthenticationException(
                message: 'Gagal terhubung ke server autentikasi SatuSehat',
                previous: $e,
            );
        }
    }

    protected function logAuthRequest(
        string $endpoint,
        ?int $status,
        ?array $responseBody,
        float $responseTime,
        bool $isSuccess,
        ?string $errorMessage = null,
    ): void {
        try {
            SatuSehatLog::log(
                resourceType: 'OAuth',
                action: 'auth',
                method: 'POST',
                endpoint: $endpoint,
                requestParams: null,
                requestBody: ['grant_type' => 'client_credentials'],
                responseStatus: $status,
                responseBody: $responseBody,
                ihsNumber: null,
                patientNik: null,
                responseTime: $responseTime,
                isSuccess: $isSuccess,
                errorMessage: $errorMessage,
            );
        } catch (\Exception $e) {
            Log::warning('Failed to save SatuSehat auth log: ' . $e->getMessage());
        }
    }

    protected function cacheToken(TokenData $token): void
    {
        $ttl = $token->expiresIn - $this->getBufferSeconds();

        Cache::put($this->getCacheKey(), $token->toArray(), $ttl);
    }

    public function invalidateToken(): void
    {
        $this->token = null;
        Cache::forget($this->getCacheKey());

        if (config('satusehat.debug')) {
            Log::debug('SatuSehat: Token berhasil di-invalidate');
        }
    }

    protected function getCacheKey(): string
    {
        return config('satusehat.cache.key', 'satusehat_access_token');
    }

    protected function getBufferSeconds(): int
    {
        return config('satusehat.cache.buffer_seconds', 60);
    }

    protected function getAuthUrl(): string
    {
        return rtrim(ConfigurationHelper::get('satusehat.auth_url') ?? config('satusehat.auth_url'), '/');
    }

    protected function getBaseUrl(): string
    {
        return rtrim(ConfigurationHelper::get('satusehat.base_url') ?? config('satusehat.base_url'), '/');
    }

    protected function getFhirBaseUrl(): string
    {
        return rtrim(ConfigurationHelper::get('satusehat.fhir_url') ?? config('satusehat.fhir_url'), '/');
    }

    protected function getTimeout(): int
    {
        return (int) (ConfigurationHelper::get('satusehat.timeout') ?? config('satusehat.timeout', 30));
    }
}
