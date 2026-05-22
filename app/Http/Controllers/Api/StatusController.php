<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Redis;

class StatusController extends Controller
{
    /**
     * Cek status Reverb WebSocket server via WebSocket handshake (101 Switching Protocols).
     * Tidak memerlukan library eksternal — menggunakan raw TCP socket.
     */
    public function reverb(): JsonResponse
    {
        $host   = env('REVERB_HOST', '127.0.0.1');
        $port   = (int) env('REVERB_PORT', 8080);
        $scheme = env('REVERB_SCHEME', 'http');
        $appKey = env('REVERB_APP_KEY', '');

        $transport = $scheme === 'https' ? 'tls' : 'tcp';
        $path      = "/app/{$appKey}?protocol=7&client=php-health-check&version=1.0";
        $wsKey     = base64_encode(random_bytes(16));

        $errno  = 0;
        $errstr = '';

        // Buka koneksi TCP/TLS ke Reverb
        $socket = @stream_socket_client(
            "{$transport}://{$host}:{$port}",
            $errno,
            $errstr,
            timeout: 3
        );

        if (!$socket) {
            return response()->json([
                'status'     => 'offline',
                'message'    => "[{$errno}] {$errstr}",
                'host'       => $host,
                'port'       => $port,
                'scheme'     => $scheme,
                'checked_at' => now()->toIso8601String(),
            ], 503);
        }

        // Kirim WebSocket Upgrade handshake (RFC 6455)
        $handshake = implode("\r\n", [
            "GET {$path} HTTP/1.1",
            "Host: {$host}:{$port}",
            "Upgrade: websocket",
            "Connection: Upgrade",
            "Sec-WebSocket-Key: {$wsKey}",
            "Sec-WebSocket-Version: 13",
            "Origin: " . config('app.url'),
            '', '',
        ]);

        fwrite($socket, $handshake);

        // Baca response headers saja (berhenti di baris kosong)
        $raw = '';
        stream_set_timeout($socket, 3);
        while (!feof($socket)) {
            $line  = fgets($socket, 512);
            $raw  .= $line;
            if (rtrim($line) === '') break;
        }
        fclose($socket);

        $upgraded = str_contains($raw, '101 Switching Protocols');

        // Ekstrak Sec-WebSocket-Accept dari response (opsional, untuk validasi)
        preg_match('/Sec-WebSocket-Accept:\s*(\S+)/i', $raw, $acceptMatch);

        return response()->json([
            'status'     => $upgraded ? 'online' : 'error',
            'protocol'   => 'websocket',
            'host'       => $host,
            'port'       => $port,
            'scheme'     => $scheme,
            'ws_accept'  => $acceptMatch[1] ?? null,
            'checked_at' => now()->toIso8601String(),
        ], $upgraded ? 200 : 502);
    }

    /**
     * Cek koneksi Redis via PING command.
     */
    public function redis(): JsonResponse
    {
        $host = env('REDIS_HOST', '127.0.0.1');
        $port = (int) env('REDIS_PORT', 6379);

        try {
            $start     = hrtime(true);
            $result    = Redis::ping();
            $latencyMs = round((hrtime(true) - $start) / 1e6, 2);

            // phpredis mengembalikan true, predis mengembalikan string 'PONG'
            $ok = $result === true || (is_string($result) && strtoupper($result) === 'PONG');

            return response()->json([
                'status'     => $ok ? 'online' : 'error',
                'response'   => is_string($result) ? $result : ($ok ? 'PONG' : (string) $result),
                'latency_ms' => $latencyMs,
                'host'       => $host,
                'port'       => $port,
                'client'     => env('REDIS_CLIENT', 'phpredis'),
                'checked_at' => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'     => 'offline',
                'message'    => $e->getMessage(),
                'host'       => $host,
                'port'       => $port,
                'client'     => env('REDIS_CLIENT', 'phpredis'),
                'checked_at' => now()->toIso8601String(),
            ], 503);
        }
    }
}
