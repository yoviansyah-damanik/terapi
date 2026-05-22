<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WaGateway\Waha\WahaLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WahaWebhookController extends Controller
{
    /**
     * Handle incoming webhook dari WAHA
     */
    public function handle(Request $request): JsonResponse
    {
        // GET request = verifikasi webhook aktif
        if ($request->isMethod('get')) {
            return response()->json(['status' => 'ok', 'webhook' => 'active']);
        }

        $event = $request->input('event');
        $payload = $request->all();

        Log::info("WAHA Webhook: {$event}", ['payload' => $payload]);

        return match ($event) {
            'message' => $this->handleMessage($payload),
            'message.ack' => $this->handleMessageAck($payload),
            'session.status' => $this->handleSessionStatus($payload),
            default => response()->json(['status' => 'ignored', 'event' => $event]),
        };
    }

    /**
     * Handle pesan masuk
     */
    protected function handleMessage(array $payload): JsonResponse
    {
        $messageData = $payload['payload'] ?? [];

        $from = $messageData['from'] ?? 'unknown';
        // Bersihkan format @c.us
        $phone = str_replace('@c.us', '', $from);

        $hasMedia = $messageData['hasMedia'] ?? false;
        $type = $hasMedia ? ($messageData['type'] ?? 'text') : 'text';

        WahaLog::logIncoming($phone, $type, $payload);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Handle status delivery pesan (ack)
     */
    protected function handleMessageAck(array $payload): JsonResponse
    {
        Log::info('WAHA Message ACK', [
            'id' => $payload['payload']['id'] ?? null,
            'ack' => $payload['payload']['ack'] ?? null,
        ]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Handle perubahan status session
     */
    protected function handleSessionStatus(array $payload): JsonResponse
    {
        $status = $payload['payload']['status'] ?? 'unknown';
        $session = $payload['payload']['name'] ?? 'unknown';

        Log::info("WAHA Session Status: {$session} -> {$status}");

        return response()->json(['status' => 'ok']);
    }
}
