<?php

namespace App\Services;

use App\Helpers\ConfigurationHelper;
use App\Models\WaGateway\Waha\WahaLog;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WahaService
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $session;

    public function __construct()
    {
        $this->baseUrl = rtrim(ConfigurationHelper::get('waha.api_url', 'http://localhost:3000'), '/');
        $this->apiKey = ConfigurationHelper::get('waha.api_key', '');
        $this->session = ConfigurationHelper::get('waha.session', 'default');
    }

    /**
     * Buat HTTP client ke WAHA API
     */
    protected function client(): \Illuminate\Http\Client\PendingRequest
    {
        $client = Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->timeout(30);

        if (!empty($this->apiKey)) {
            $client->withHeaders(['X-Api-Key' => $this->apiKey]);
        }

        return $client;
    }

    /**
     * Kirim request ke WAHA API dan format response-nya
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
            Log::error("WAHA API Error [{$endpoint}]: {$e->getMessage()}");

            return [
                'success' => false,
                'message' => 'Gagal terhubung ke server WAHA',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Format response dari WAHA API menjadi array standar
     */
    protected function formatResponse(Response $response): array
    {
        $body = $response->json() ?? [];

        return [
            'success' => $response->successful(),
            'status_code' => $response->status(),
            'data' => $body,
        ];
    }

    /**
     * Konversi nomor telepon ke format WAHA (628xxx@c.us)
     */
    public function formatPhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($phone, '08')) {
            $phone = '62' . substr($phone, 1);
        } elseif (str_starts_with($phone, '8')) {
            $phone = '62' . $phone;
        }

        return $phone . '@c.us';
    }

    // ===== Session Management =====

    /**
     * Daftar semua session WAHA
     */
    public function listSessions(): array
    {
        return $this->sendRequest('GET', '/api/sessions/');
    }

    /**
     * Cek status session WAHA
     */
    public function getSessionStatus(): array
    {
        return $this->sendRequest('GET', "/api/sessions/{$this->session}");
    }

    /**
     * Mulai session WAHA.
     * Coba buat session baru, jika sudah ada coba start yang existing.
     */
    public function startSession(): array
    {
        $webhookUrl = $this->getWebhookUrl();

        $webhooks = $webhookUrl ? [
            [
                'url' => $webhookUrl,
                'events' => ['message', 'message.ack', 'session.status'],
            ],
        ] : [];

        // 1. Coba buat session baru (POST /api/sessions)
        $result = $this->sendRequest('POST', '/api/sessions', [
            'name' => $this->session,
            'start' => true,
            'config' => [
                'webhooks' => $webhooks,
            ],
        ]);

        // Berhasil dibuat, atau error bukan 422 → kembalikan langsung
        if ($result['success'] || ($result['status_code'] ?? 0) !== 422) {
            return $result;
        }

        // 2. Session sudah ada → coba start (POST /api/sessions/{name}/start)
        $startResult = $this->sendRequest('POST', "/api/sessions/{$this->session}/start");

        // Jika sudah started (422), anggap berhasil
        if (!$startResult['success'] && ($startResult['status_code'] ?? 0) === 422) {
            return [
                'success' => true,
                'status_code' => 200,
                'data' => $startResult['data'] ?? [],
                'message' => 'Session sudah berjalan',
            ];
        }

        return $startResult;
    }

    /**
     * Hentikan session WAHA
     */
    public function stopSession(): array
    {
        return $this->sendRequest('POST', "/api/sessions/{$this->session}/stop");
    }

    /**
     * Restart session WAHA
     */
    public function restartSession(): array
    {
        return $this->sendRequest('POST', "/api/sessions/{$this->session}/restart");
    }

    /**
     * Logout session WAHA (disconnect WhatsApp)
     */
    public function logoutSession(): array
    {
        return $this->sendRequest('POST', "/api/sessions/{$this->session}/logout");
    }

    /**
     * Hapus session WAHA
     */
    public function deleteSession(): array
    {
        return $this->sendRequest('DELETE', "/api/sessions/{$this->session}");
    }

    /**
     * Ambil QR code untuk scan (mengembalikan data URI base64)
     */
    public function getQrCode(): array
    {
        try {
            // Coba ambil sebagai image langsung
            $response = $this->client()->get("/api/{$this->session}/auth/qr", [
                'format' => 'image',
            ]);

            if ($response->successful()) {
                $contentType = $response->header('Content-Type');

                // Response berupa image binary
                if (str_contains($contentType, 'image')) {
                    return [
                        'success' => true,
                        'data' => 'data:' . $contentType . ';base64,' . base64_encode($response->body()),
                    ];
                }

                // Response berupa JSON dengan field mimetype + data (base64)
                $json = $response->json();
                if (isset($json['data']) && isset($json['mimetype'])) {
                    return [
                        'success' => true,
                        'data' => 'data:' . $json['mimetype'] . ';base64,' . $json['data'],
                    ];
                }

                return $this->formatResponse($response);
            }

            // Status 404 biasa berarti session belum siap untuk QR
            return [
                'success' => false,
                'message' => 'QR code belum tersedia. Pastikan session sudah di-start.',
                'status_code' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error("WAHA QR Code Error: {$e->getMessage()}");

            return [
                'success' => false,
                'message' => 'Gagal terhubung ke server WAHA',
                'error' => $e->getMessage(),
            ];
        }
    }

    // ===== Pengiriman Pesan =====

    /**
     * Kirim pesan teks
     */
    public function sendText(string $phone, string $message): array
    {
        $payload = [
            'chatId' => $this->formatPhone($phone),
            'text' => $message,
            'session' => $this->session,
        ];

        $result = $this->sendRequest('POST', '/api/sendText', $payload);

        WahaLog::logOutgoing($phone, 'text', $payload, $result['data'] ?? $result, $result['success']);

        return $result;
    }

    /**
     * Kirim gambar dengan caption
     */
    public function sendImage(string $phone, string $imagePath, ?string $caption = null): array
    {
        $imageData = base64_encode(file_get_contents($imagePath));
        $mimeType = mime_content_type($imagePath);

        $payload = [
            'chatId' => $this->formatPhone($phone),
            'file' => [
                'mimetype' => $mimeType,
                'filename' => basename($imagePath),
                'data' => $imageData,
            ],
            'caption' => $caption ?? '',
            'session' => $this->session,
        ];

        $result = $this->sendRequest('POST', '/api/sendImage', $payload);

        // Log tanpa data file base64 agar tidak membengkak
        $logPayload = $payload;
        $logPayload['file']['data'] = '[base64 truncated]';
        WahaLog::logOutgoing($phone, 'image', $logPayload, $result['data'] ?? $result, $result['success']);

        return $result;
    }

    /**
     * Kirim file/dokumen
     */
    public function sendFile(string $phone, string $filePath, ?string $fileName = null): array
    {
        $fileData = base64_encode(file_get_contents($filePath));
        $mimeType = mime_content_type($filePath);

        $payload = [
            'chatId' => $this->formatPhone($phone),
            'file' => [
                'mimetype' => $mimeType,
                'filename' => $fileName ?? basename($filePath),
                'data' => $fileData,
            ],
            'session' => $this->session,
        ];

        $result = $this->sendRequest('POST', '/api/sendFile', $payload);

        // Log tanpa data file base64
        $logPayload = $payload;
        $logPayload['file']['data'] = '[base64 truncated]';
        WahaLog::logOutgoing($phone, 'file', $logPayload, $result['data'] ?? $result, $result['success']);

        return $result;
    }

    // ===== Helper =====

    /**
     * Ambil URL webhook aplikasi ini untuk dikonfigurasi di WAHA
     */
    public function getWebhookUrl(): ?string
    {
        $webhookUrl = ConfigurationHelper::get('waha.webhook_url');

        if ($webhookUrl) {
            return $webhookUrl;
        }

        $appUrl = config('app.url');
        if ($appUrl) {
            return rtrim($appUrl, '/') . '/api/whatsapp/webhook';
        }

        return null;
    }
}
