<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class OAuthService
{
    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    /** @var string[] */
    private array $scopes;

    public function __construct()
    {
        $this->baseUrl      = rtrim(config('services.oauth_rs.base_url', ''), '/');
        $this->clientId     = config('services.oauth_rs.client_id', '');
        $this->clientSecret = config('services.oauth_rs.client_secret', '');
        $this->redirectUri  = config('services.oauth_rs.redirect_uri', '');
        $this->scopes       = config('services.oauth_rs.scopes', ['openid', 'profile', 'email', 'role']);
    }

    /** Cek apakah OAuth RS sudah dikonfigurasi. */
    public function isEnabled(): bool
    {
        return $this->baseUrl !== '' && $this->clientId !== '';
    }

    /**
     * Buat URL otorisasi beserta state CSRF, lalu simpan state ke session.
     * @return array{url: string, state: string}
     */
    public function buildAuthorizationUrl(): array
    {
        $state = Str::random(40);

        $url = $this->baseUrl . '/oauth/authorize?' . http_build_query([
            'client_id'     => $this->clientId,
            'redirect_uri'  => $this->redirectUri,
            'response_type' => 'code',
            'scope'         => implode(' ', $this->scopes),
            'state'         => $state,
        ]);

        return ['url' => $url, 'state' => $state];
    }

    /**
     * Tukar authorization code dengan access token.
     * @throws RuntimeException jika request gagal atau response tidak valid
     */
    public function exchangeCodeForToken(string $code): string
    {
        try {
            $response = Http::asForm()->post($this->baseUrl . '/oauth/token', [
                'grant_type'    => 'authorization_code',
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'redirect_uri'  => $this->redirectUri,
                'code'          => $code,
            ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Tidak dapat terhubung ke server OAuth.', 0, $e);
        }

        if (!$response->successful()) {
            throw new RuntimeException('Gagal mendapatkan access token dari server OAuth.');
        }

        $token = $response->json('access_token');
        if (!$token) {
            throw new RuntimeException('Response token tidak valid dari server OAuth.');
        }

        return $token;
    }

    /**
     * Ambil data user dari endpoint userinfo.
     * @return array{sub: string, name?: string, preferred_username?: string, email?: string, role?: string}
     * @throws RuntimeException jika request gagal
     */
    public function getUserInfo(string $accessToken): array
    {
        try {
            $response = Http::withToken($accessToken)
                ->get($this->baseUrl . '/oauth/userinfo');
        } catch (ConnectionException $e) {
            throw new RuntimeException('Tidak dapat terhubung ke server OAuth.', 0, $e);
        }

        if (!$response->successful()) {
            throw new RuntimeException('Gagal mengambil data pengguna dari server OAuth.');
        }

        return $response->json();
    }
}
