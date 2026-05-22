<?php

namespace App\Services;

use App\Helpers\ConfigurationHelper;
use App\Models\WaGateway\Gowa\GowaLog;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GowaService
{
    protected string $baseUrl;
    protected string $username;
    protected string $password;
    protected string $deviceId;

    public function __construct()
    {
        $this->baseUrl = rtrim(ConfigurationHelper::get('gowa.api_url', 'http://localhost:3000'), '/');
        $this->username = ConfigurationHelper::get('gowa.username', '');
        $this->password = ConfigurationHelper::get('gowa.password', '');
        $this->deviceId = ConfigurationHelper::get('gowa.device_id', '');
    }

    /**
     * Buat HTTP client ke GOWA API dengan Basic Auth
     */
    protected function client(): \Illuminate\Http\Client\PendingRequest
    {
        $client = Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->timeout(30);

        if (!empty($this->username) && !empty($this->password)) {
            $client->withBasicAuth($this->username, $this->password);
        }

        if (!empty($this->deviceId)) {
            $client->withHeaders(['X-Device-Id' => $this->deviceId]);
        }

        return $client;
    }

    /**
     * Kirim request ke GOWA API dan format response-nya
     */
    protected function sendRequest(string $method, string $endpoint, array $payload = []): array
    {
        try {
            $response = match (strtoupper($method)) {
                'GET' => $this->client()->get($endpoint, $payload),
                'POST' => $this->client()->post($endpoint, $payload),
                'PUT' => $this->client()->put($endpoint, $payload),
                'DELETE' => $this->client()->delete($endpoint, $payload),
                default => $this->client()->post($endpoint, $payload),
            };

            return $this->formatResponse($response);
        } catch (\Exception $e) {
            Log::error("GOWA API Error [{$endpoint}]: {$e->getMessage()}");

            return [
                'success' => false,
                'message' => 'Gagal terhubung ke server GOWA',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Kirim request multipart/form-data ke GOWA API (untuk upload media)
     */
    protected function sendMultipartRequest(string $endpoint, array $multipart): array
    {
        try {
            $client = $this->client();

            $response = $client->asMultipart();

            foreach ($multipart as $item) {
                if (isset($item['contents']) && is_resource($item['contents'])) {
                    $response = $response->attach(
                        $item['name'],
                        $item['contents'],
                        $item['filename'] ?? null
                    );
                } elseif (isset($item['file_path'])) {
                    $response = $response->attach(
                        $item['name'],
                        file_get_contents($item['file_path']),
                        $item['filename'] ?? basename($item['file_path'])
                    );
                } else {
                    $response = $response->attach(
                        $item['name'],
                        $item['contents'] ?? '',
                        $item['filename'] ?? null
                    );
                }
            }

            $httpResponse = $response->post($endpoint);

            return $this->formatResponse($httpResponse);
        } catch (\Exception $e) {
            Log::error("GOWA Multipart Error [{$endpoint}]: {$e->getMessage()}");

            return [
                'success' => false,
                'message' => 'Gagal mengirim file ke server GOWA',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Format response dari GOWA API
     */
    protected function formatResponse(Response $response): array
    {
        $body = $response->json() ?? [];

        return [
            'success' => $response->successful() && ($body['code'] ?? '') === 'SUCCESS',
            'status_code' => $response->status(),
            'data' => $body['results'] ?? $body,
            'message' => $body['message'] ?? null,
        ];
    }

    /**
     * Konversi nomor telepon ke format GOWA (62xxx tanpa @c.us)
     */
    public function formatPhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($phone, '08')) {
            $phone = '62' . substr($phone, 1);
        } elseif (str_starts_with($phone, '8')) {
            $phone = '62' . $phone;
        }

        return $phone;
    }

    // ===== Koneksi & Login =====

    /**
     * Login via QR Code — mengembalikan data QR code image
     */
    public function login(): array
    {
        try {
            $response = $this->client()->get('/app/login');

            if ($response->successful()) {
                $contentType = $response->header('Content-Type');

                if (str_contains($contentType, 'image')) {
                    return [
                        'success' => true,
                        'data' => 'data:' . $contentType . ';base64,' . base64_encode($response->body()),
                    ];
                }

                $json = $response->json();
                if (isset($json['results']['qr_link'])) {
                    return [
                        'success' => true,
                        'data' => $json['results']['qr_link'],
                    ];
                }

                return $this->formatResponse($response);
            }

            return [
                'success' => false,
                'message' => 'QR code belum tersedia',
                'status_code' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error("GOWA Login Error: {$e->getMessage()}");

            return [
                'success' => false,
                'message' => 'Gagal terhubung ke server GOWA',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Login via pairing code
     */
    public function loginWithCode(string $phone): array
    {
        $phone = $this->formatPhone($phone);

        return $this->sendRequest('GET', '/app/login-with-code', ['phone' => $phone]);
    }

    /**
     * Logout dari WhatsApp
     */
    public function logout(): array
    {
        return $this->sendRequest('GET', '/app/logout');
    }

    /**
     * Reconnect ke WhatsApp
     */
    public function reconnect(): array
    {
        return $this->sendRequest('GET', '/app/reconnect');
    }

    /**
     * Ambil daftar device yang terhubung
     */
    public function getDevices(): array
    {
        return $this->sendRequest('GET', '/app/devices');
    }

    // ===== Pengiriman Pesan =====

    /**
     * Kirim pesan teks
     */
    public function sendMessage(string $phone, string $message, array $options = []): array
    {
        $phone = $this->formatPhone($phone);

        $payload = array_merge([
            'phone' => $phone,
            'message' => $message,
        ], $options);

        $result = $this->sendRequest('POST', '/send/message', $payload);

        GowaLog::logOutgoing($phone, 'text', $payload, $result['data'] ?? $result, $result['success']);

        return $result;
    }

    /**
     * Kirim gambar dengan caption
     */
    public function sendImage(string $phone, string $filePath, ?string $caption = null, array $options = []): array
    {
        $phone = $this->formatPhone($phone);

        $multipart = [
            ['name' => 'phone', 'contents' => $phone],
            ['name' => 'caption', 'contents' => $caption ?? ''],
            ['name' => 'image', 'file_path' => $filePath, 'filename' => basename($filePath)],
        ];

        if (isset($options['view_once'])) {
            $multipart[] = ['name' => 'view_once', 'contents' => $options['view_once'] ? 'true' : 'false'];
        }

        $result = $this->sendMultipartRequest('/send/image', $multipart);

        $logPayload = ['phone' => $phone, 'caption' => $caption, 'file' => basename($filePath)];
        GowaLog::logOutgoing($phone, 'image', $logPayload, $result['data'] ?? $result, $result['success']);

        return $result;
    }

    /**
     * Kirim file/dokumen
     */
    public function sendFile(string $phone, string $filePath, ?string $caption = null, array $options = []): array
    {
        $phone = $this->formatPhone($phone);

        $multipart = [
            ['name' => 'phone', 'contents' => $phone],
            ['name' => 'caption', 'contents' => $caption ?? ''],
            ['name' => 'file', 'file_path' => $filePath, 'filename' => basename($filePath)],
        ];

        $result = $this->sendMultipartRequest('/send/file', $multipart);

        $logPayload = ['phone' => $phone, 'caption' => $caption, 'file' => basename($filePath)];
        GowaLog::logOutgoing($phone, 'file', $logPayload, $result['data'] ?? $result, $result['success']);

        return $result;
    }

    /**
     * Kirim video dengan caption
     */
    public function sendVideo(string $phone, string $filePath, ?string $caption = null, array $options = []): array
    {
        $phone = $this->formatPhone($phone);

        $multipart = [
            ['name' => 'phone', 'contents' => $phone],
            ['name' => 'caption', 'contents' => $caption ?? ''],
            ['name' => 'video', 'file_path' => $filePath, 'filename' => basename($filePath)],
        ];

        if (isset($options['view_once'])) {
            $multipart[] = ['name' => 'view_once', 'contents' => $options['view_once'] ? 'true' : 'false'];
        }

        $result = $this->sendMultipartRequest('/send/video', $multipart);

        $logPayload = ['phone' => $phone, 'caption' => $caption, 'file' => basename($filePath)];
        GowaLog::logOutgoing($phone, 'video', $logPayload, $result['data'] ?? $result, $result['success']);

        return $result;
    }

    /**
     * Kirim audio
     */
    public function sendAudio(string $phone, string $filePath, array $options = []): array
    {
        $phone = $this->formatPhone($phone);

        $multipart = [
            ['name' => 'phone', 'contents' => $phone],
            ['name' => 'audio', 'file_path' => $filePath, 'filename' => basename($filePath)],
        ];

        $result = $this->sendMultipartRequest('/send/audio', $multipart);

        $logPayload = ['phone' => $phone, 'file' => basename($filePath)];
        GowaLog::logOutgoing($phone, 'audio', $logPayload, $result['data'] ?? $result, $result['success']);

        return $result;
    }

    /**
     * Kirim lokasi
     */
    public function sendLocation(string $phone, float $latitude, float $longitude, array $options = []): array
    {
        $phone = $this->formatPhone($phone);

        $payload = array_merge([
            'phone' => $phone,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ], $options);

        $result = $this->sendRequest('POST', '/send/location', $payload);

        GowaLog::logOutgoing($phone, 'location', $payload, $result['data'] ?? $result, $result['success']);

        return $result;
    }

    /**
     * Kirim kontak
     */
    public function sendContact(string $phone, string $contactName, string $contactPhone, array $options = []): array
    {
        $phone = $this->formatPhone($phone);

        $payload = array_merge([
            'phone' => $phone,
            'contact_name' => $contactName,
            'contact_phone' => $this->formatPhone($contactPhone),
        ], $options);

        $result = $this->sendRequest('POST', '/send/contact', $payload);

        GowaLog::logOutgoing($phone, 'contact', $payload, $result['data'] ?? $result, $result['success']);

        return $result;
    }

    /**
     * Kirim link/URL
     */
    public function sendLink(string $phone, string $link, ?string $caption = null, array $options = []): array
    {
        $phone = $this->formatPhone($phone);

        $payload = array_merge([
            'phone' => $phone,
            'link' => $link,
            'caption' => $caption ?? '',
        ], $options);

        $result = $this->sendRequest('POST', '/send/link', $payload);

        GowaLog::logOutgoing($phone, 'link', $payload, $result['data'] ?? $result, $result['success']);

        return $result;
    }

    /**
     * Kirim polling
     */
    public function sendPoll(string $phone, string $question, array $pollOptions, int $maxAnswer = 1): array
    {
        $phone = $this->formatPhone($phone);

        $payload = [
            'phone' => $phone,
            'question' => $question,
            'options' => $pollOptions,
            'max_answer' => $maxAnswer,
        ];

        $result = $this->sendRequest('POST', '/send/poll', $payload);

        GowaLog::logOutgoing($phone, 'poll', $payload, $result['data'] ?? $result, $result['success']);

        return $result;
    }

    // ===== Manajemen Pesan =====

    /**
     * Tarik pesan (revoke)
     */
    public function revokeMessage(string $phone, string $messageId): array
    {
        $phone = $this->formatPhone($phone);

        return $this->sendRequest('POST', "/message/{$messageId}/revoke", [
            'phone' => $phone,
        ]);
    }

    /**
     * Hapus pesan
     */
    public function deleteMessage(string $phone, string $messageId): array
    {
        $phone = $this->formatPhone($phone);

        return $this->sendRequest('POST', "/message/{$messageId}/delete", [
            'phone' => $phone,
        ]);
    }

    /**
     * Kirim reaksi emoji ke pesan
     */
    public function reactMessage(string $phone, string $messageId, string $emoji): array
    {
        $phone = $this->formatPhone($phone);

        return $this->sendRequest('POST', "/message/{$messageId}/reaction", [
            'phone' => $phone,
            'emoji' => $emoji,
        ]);
    }

    // ===== User =====

    /**
     * Cek apakah nomor terdaftar di WhatsApp
     */
    public function checkUser(string $phone): array
    {
        $phone = $this->formatPhone($phone);

        return $this->sendRequest('GET', '/user/check', ['phone' => $phone]);
    }

    /**
     * Ambil info user WhatsApp
     */
    public function getUserInfo(string $phone): array
    {
        $phone = $this->formatPhone($phone);

        return $this->sendRequest('GET', '/user/info', ['phone' => $phone]);
    }

    /**
     * Ambil avatar user WhatsApp
     */
    public function getUserAvatar(string $phone): array
    {
        $phone = $this->formatPhone($phone);

        return $this->sendRequest('GET', '/user/avatar', ['phone' => $phone]);
    }

    // ===== Helper =====

    /**
     * Ambil URL webhook aplikasi ini
     */
    public function getWebhookUrl(): ?string
    {
        $webhookUrl = ConfigurationHelper::get('gowa.webhook_url');

        if ($webhookUrl) {
            return $webhookUrl;
        }

        $appUrl = config('app.url');
        if ($appUrl) {
            return rtrim($appUrl, '/') . '/api/gowa/webhook';
        }

        return null;
    }
}
