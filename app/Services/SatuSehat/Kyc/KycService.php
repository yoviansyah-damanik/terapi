<?php

namespace App\Services\SatuSehat\Kyc;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Layanan KYC SatuSehat — Generate URL & Challenge Code.
 * Reuse token OAuth2 dari cache SatuSehat yang sudah ada.
 */
class KycService
{
    public function __construct(private KycEncryptionService $encryption)
    {
    }

    /**
     * Generate URL verifikasi mandiri pasien via SatuSehat KYC.
     * Return array berisi 'data' (url, token) dari response terdekrip.
     *
     * @throws \RuntimeException jika token tidak tersedia atau request gagal
     */
    public function generateUrl(string $agentName, string $agentNik): array
    {
        $token = $this->getAccessToken();
        $keyPair = $this->encryption->generateRsaKeyPair();

        $payload = json_encode([
            'agent_name' => $agentName,
            'agent_nik' => $agentNik,
            'public_key' => $keyPair['public'],
        ]);

        $environment = $this->getEnvironment();
        $pubKey = $environment === 'production'
            ? KycEncryptionService::SATUSEHAT_PUBLIC_KEY_PROD
            : KycEncryptionService::SATUSEHAT_PUBLIC_KEY_DEV;

        $encrypted = $this->encryption->encryptPayload($payload, $pubKey);

        $response = Http::withToken($token)
            ->withHeaders(['Content-Type' => 'text/plain'])
            ->timeout(config('satusehat.timeout', 30))
            ->post($this->getKycBaseUrl() . '/kyc/v1/generate-url', $encrypted);

        if (!$response->successful()) {
            $body = $response->body();
            Log::error('KYC generate-url failed', ['status' => $response->status(), 'body' => $body]);
            throw new \RuntimeException('KYC generate-url gagal: HTTP ' . $response->status());
        }

        $decrypted = $this->encryption->decryptResponse($response->body(), $keyPair['private']);

        return json_decode($decrypted, true);
    }

    /**
     * Generate challenge code 6-digit untuk pasien dengan SATUSEHAT Mobile.
     * Return array berisi 'challenge_code', 'ihs_number', 'expired_at'.
     *
     * @throws \RuntimeException jika token tidak tersedia atau request gagal
     */
    public function challengeCode(string $agentNik, string $patientNik, string $patientName): array
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->timeout(config('satusehat.timeout', 30))
            ->post($this->getKycBaseUrl() . '/kyc/v1/challenge-code', [
                'metadata' => [
                    'nik' => $patientNik,
                    'name' => $patientName,
                ],
            ]);

        if (!$response->successful()) {
            Log::error('KYC challenge-code failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('KYC challenge-code gagal: HTTP ' . $response->status());
        }

        return $response->json();
    }

    private function getAccessToken(): string
    {
        $cacheKey = config('satusehat.cache.key', 'satusehat_access_token');
        $cached = Cache::get($cacheKey);

        if (!$cached || empty($cached['access_token'])) {
            throw new \RuntimeException('Token SatuSehat belum tersedia. Pastikan konfigurasi SatuSehat sudah benar dan token telah di-refresh.');
        }

        return $cached['access_token'];
    }

    /** Tentukan environment berdasarkan base_url (staging vs production) */
    private function getEnvironment(): string
    {
        $baseUrl = config('satusehat.base_url', '');
        return str_contains($baseUrl, 'stg') ? 'development' : 'production';
    }

    /** Base URL untuk endpoint KYC (sama dengan base_url SatuSehat) */
    private function getKycBaseUrl(): string
    {
        return rtrim(config('satusehat.base_url'), '/');
    }
}
