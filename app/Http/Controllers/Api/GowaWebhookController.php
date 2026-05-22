<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WaGateway\Gowa\GowaLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GowaWebhookController extends Controller
{
    /**
     * Handle incoming webhook dari GOWA server
     */
    public function handle(Request $request): JsonResponse
    {
        if ($request->isMethod('get')) {
            return response()->json(['status' => 'ok', 'webhook' => 'active']);
        }

        $payload = $request->all();

        Log::info('GOWA Webhook received', ['payload' => $payload]);

        $phone = $payload['from'] ?? $payload['phone'] ?? 'unknown';
        $type = $payload['type'] ?? $payload['message_type'] ?? 'text';

        GowaLog::logIncoming($phone, $type, $payload);

        return response()->json(['status' => 'ok']);
    }
}
