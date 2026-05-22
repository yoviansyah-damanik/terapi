<?php

/**
 * Komponen tes koneksi mandiri.
 * Merender tombol "Tes Koneksi" sekaligus modal hasilnya.
 * Halaman pemanggil cukup menyediakan URL, headers, dan parameter — komponen ini
 * yang melakukan HTTP request dan menampilkan hasilnya.
 *
 * Penggunaan:
 *   <livewire:components.connection-result
 *       url="https://api.example.com/health"
 *       method="GET"
 *       :headers="['Authorization' => 'Bearer ' . $token]"
 *       :volatile-headers="['X-Timestamp' => 'time']"
 *       name="connection-example"
 *       title="Tes Koneksi — Example API" />
 *
 * Prop volatile-headers: header yang dikomputasi ulang tepat saat test dijalankan.
 *   'time'   → (string) time()           (Unix timestamp)
 *   'unixms' → (string) microtime * 1000 (milidetik)
 *
 * Prop hmac-bpjs: komputasi BPJS HMAC-SHA256 saat test dijalankan.
 *   ['cons_id' => ..., 'secret_key' => ..., 'user_key' => ...]
 *
 * Prop as-form: kirim body sebagai application/x-www-form-urlencoded (untuk OAuth2).
 * Prop reachable: jika true, respons HTTP apapun dianggap sukses (hanya exception = gagal).
 */

use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Prop;
use Livewire\Component;

new class extends Component {
    #[Prop]
    public string $url = '';
    #[Prop]
    public string $method = 'GET';
    #[Prop]
    public array $headers = [];
    #[Prop]
    public array $volatileHeaders = []; // ['Header-Key' => 'time'|'unixms'|static_value]
    #[Prop]
    public array $hmacBpjs = []; // ['cons_id' => ..., 'secret_key' => ..., 'user_key' => ...]
    #[Prop]
    public array $body = [];
    #[Prop]
    public int $timeout = 10;
    #[Prop]
    public bool $asForm = false;
    #[Prop]
    public bool $reachable = false;
    #[Prop]
    public string $name = 'connection-result';
    #[Prop]
    public string $title = 'Tes Koneksi';
    #[Prop]
    public string $label = 'Tes Koneksi';
    #[Prop]
    public string $size = 'md';
    #[Prop]
    public string $variant = 'ghost';
    #[Prop]
    public string $icon = 'signal';

    public ?array $status = null;

    /** Jalankan HTTP request dan simpan hasilnya ke $status */
    public function runTest(): void
    {
        $this->status = null;

        $headers = $this->headers;
        foreach ($this->volatileHeaders as $key => $formula) {
            $headers[$key] = match ($formula) {
                'time' => (string) time(),
                'unixms' => (string) (int) round(microtime(true) * 1000),
                default => (string) $formula,
            };
        }

        // Komputasi BPJS HMAC-SHA256 fresh saat test dijalankan
        if (!empty($this->hmacBpjs)) {
            $timestamp = time();
            $signature = base64_encode(hash_hmac('sha256', $this->hmacBpjs['cons_id'] . '&' . $timestamp, $this->hmacBpjs['secret_key'], true));
            $headers['X-cons-id'] = (string) $this->hmacBpjs['cons_id'];
            $headers['X-timestamp'] = (string) $timestamp;
            $headers['X-signature'] = $signature;
            $headers['user_key'] = (string) $this->hmacBpjs['user_key'];
            $headers['Content-Type'] = 'application/json';
        }

        $start = microtime(true);

        try {
            $req = Http::withHeaders($headers);
            if ($this->asForm) {
                $req = $req->asForm();
            }
            $req = $req->timeout($this->timeout);
            $response = match (strtoupper($this->method)) {
                'POST' => $req->post($this->url, $this->body),
                'PUT' => $req->put($this->url, $this->body),
                'DELETE' => $req->delete($this->url, $this->body),
                default => $req->get($this->url, $this->body ?: []),
            };
            $ms = (int) round((microtime(true) - $start) * 1000);

            $parsed = parse_url($this->url);
            $baseUrl = ($parsed['scheme'] ?? '') . '://' . ($parsed['host'] ?? '') . (isset($parsed['port']) ? ':' . $parsed['port'] : '');

            $isSuccess = $this->reachable ? true : $response->successful();
            $this->status = [
                'success' => $isSuccess,
                'message' => $isSuccess ? 'Berhasil terhubung' : $response->json('message') ?? "Server merespons dengan status {$response->status()}",
                'base_url' => $baseUrl,
                'url' => $this->url,
                'method' => strtoupper($this->method),
                'http_status' => $response->status(),
                'response_time' => $ms,
                'server' => $response->header('Server') ?: null,
                'tested_at' => now()->format('d M Y, H:i:s'),
                'headers' => $headers,
                'payload' => $this->body,
            ];
        } catch (\Throwable $e) {
            $ms = (int) round((microtime(true) - $start) * 1000);

            $parsed = parse_url($this->url);
            $baseUrl = ($parsed['scheme'] ?? '') . '://' . ($parsed['host'] ?? '') . (isset($parsed['port']) ? ':' . $parsed['port'] : '');

            $this->status = [
                'success' => false,
                'message' => $e->getMessage(),
                'base_url' => $baseUrl,
                'url' => $this->url,
                'method' => strtoupper($this->method),
                'http_status' => null,
                'response_time' => $ms,
                'tested_at' => now()->format('d M Y, H:i:s'),
                'headers' => $headers,
                'payload' => $this->body,
            ];
        }
    }
};
?>

{{--
    Komponen merender dua hal:
    1. Tombol pemicu (inline, bisa diletakkan di mana saja)
    2. flux:modal dengan tampilan loading + hasil test
--}}
@php
    $isSuccess = $status['success'] ?? false;

    $knownKeys = [
        'base_url' => 'Base URL',
        'url' => 'Endpoint',
        'method' => 'Method',
        'http_status' => 'HTTP Status',
        'response_time' => 'Response Time',
        'server' => 'Server',
        'rs_id' => 'RS ID',
        'version' => 'Versi',
        'package' => 'Paket',
        'tested_at' => 'Diuji pada',
    ];

    $rows = [];
    if ($status) {
        foreach ($knownKeys as $key => $value) {
            if (!isset($status[$key]) || $status[$key] === null || $status[$key] === '') {
                continue;
            }
            $rows[$value] = $status[$key];
        }
    }

    // Response time color tokens (app-version style)
    if ($status && isset($status['response_time'])) {
        $rtMs = (int) $status['response_time'];
        [$rtBg, $rtText, $rtDot] = match (true) {
            $rtMs < 500 => [
                'bg-emerald-50 dark:bg-emerald-900/30',
                'text-emerald-600 dark:text-emerald-400',
                'bg-emerald-400',
            ],
            $rtMs < 2000 => ['bg-amber-50 dark:bg-amber-900/30', 'text-amber-600 dark:text-amber-400', 'bg-amber-400'],
            default => ['bg-red-50 dark:bg-red-900/30', 'text-red-600 dark:text-red-400', 'bg-red-400'],
        };
        $rtLabel = match (true) {
            $rtMs < 500 => 'cepat',
            $rtMs < 2000 => 'normal',
            default => 'lambat',
        };
    } else {
        $rtMs = 0;
        $rtBg = '';
        $rtText = '';
        $rtDot = '';
        $rtLabel = '';
    }
@endphp

<div class="inline">
    {{-- Tombol pemicu --}}
    <x-atoms.button :size="$size" type="button" :variant="$variant" :icon="$icon" wire:click="runTest"
        x-on:click="$flux.modal('{{ $name }}').show()">
        {{ $label }}
    </x-atoms.button>

    {{-- Modal hasil --}}
    <x-organisms.modal :name="$name" maxWidth="xl" title="">
        <div class="space-y-5">

            <flux:heading size="lg">{{ $title }}</flux:heading>

            {{-- Loading --}}
            <div wire:loading wire:target="runTest" class="flex flex-col justify-center items-center gap-4 py-10 w-full">
                <flux:icon.loading class="size-12 mx-auto" />
                <div class="text-center mt-4 text-green-700">
                    <p class="text-sm font-medium text-zinc-700 dark:text-primary-dark-300">Menguji koneksi...</p>
                    <p class="text-xs text-zinc-400 mt-0.5">Mohon tunggu, sedang menghubungi server</p>
                </div>
            </div>

            {{-- Hasil (tersembunyi saat loading) --}}
            <div wire:loading.remove wire:target="runTest">
                @if ($status !== null)
                    {{-- Banner status --}}
                    <div @class([
                        'flex items-center gap-3 px-4 py-3 rounded-lg border mb-4',
                        'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800' => $isSuccess,
                        'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800' => !$isSuccess,
                    ])>
                        <flux:icon :name="$isSuccess ? 'check-circle' : 'x-circle'"
                            class="size-6 shrink-0 {{ $isSuccess ? 'text-green-500 dark:text-green-400' : 'text-red-500 dark:text-red-400' }}" />
                        <div>
                            <p
                                class="text-sm font-semibold {{ $isSuccess ? 'text-green-800 dark:text-green-200' : 'text-red-800 dark:text-red-200' }}">
                                {{ $isSuccess ? 'Berhasil Terhubung' : 'Koneksi Gagal' }}
                            </p>
                            <p
                                class="text-xs mt-0.5 {{ $isSuccess ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300' }}">
                                {{ $status['message'] }}
                            </p>
                        </div>
                    </div>

                    {{-- Detail --}}
                    @if (count($rows) > 0)
                        <div
                            class="rounded-lg border border-zinc-200 dark:border-primary-dark-700 overflow-hidden text-sm">
                            @foreach ($rows as $key => $value)
                                <div @class([
                                    'flex items-center gap-3 px-4 py-2.5',
                                    'bg-zinc-50 dark:bg-primary-dark-900/40' => $loop->odd,
                                    'bg-white dark:bg-primary-dark-800' => $loop->even,
                                    'border-b border-zinc-100 dark:border-primary-dark-700/60' => !$loop->last,
                                ])>
                                    <span
                                        class="w-28 shrink-0 text-xs text-zinc-500 dark:text-primary-dark-400">{{ $key }}</span>
                                    <span class="flex-1 font-medium text-zinc-800 dark:text-primary-dark-200 break-all">
                                        @if ($key === 'HTTP Status')
                                            @php $code = (int) $value; @endphp
                                            <span @class([
                                                'inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold font-mono',
                                                'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' =>
                                                    $code > 0 && $code < 400,
                                                'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300' =>
                                                    $code >= 400,
                                                'bg-zinc-100 text-zinc-700 dark:bg-primary-dark-700 dark:text-primary-dark-300' =>
                                                    $code === 0,
                                            ])>{{ $value ?: '—' }}</span>
                                        @elseif($key === 'Response Time')
                                            <span class="inline-flex items-center gap-1.5">
                                                <span class="relative flex h-2 w-2 shrink-0">
                                                    <span
                                                        class="animate-ping absolute inline-flex h-full w-full rounded-full {{ $rtDot }} opacity-60"></span>
                                                    <span
                                                        class="relative inline-flex rounded-full h-2 w-2 {{ $rtDot }}"></span>
                                                </span>
                                                <span
                                                    class="font-mono font-semibold px-1.5 py-0.5 rounded-md text-xs {{ $rtBg }} {{ $rtText }}">
                                                    {{ number_format($rtMs) }} ms
                                                </span>
                                                <span class="text-xs text-zinc-400">({{ $rtLabel }})</span>
                                            </span>
                                        @elseif($key === 'Method')
                                            <span
                                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold font-mono bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">
                                                {{ $value }}
                                            </span>
                                        @else
                                            <span class="font-mono text-xs">{{ $value }}</span>
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Payload & Headers & Auth Sections --}}
                    @if ($status && !empty($status['payload']))
                        <div
                            class="mt-4 rounded-lg border border-zinc-200 dark:border-primary-dark-700 overflow-hidden text-sm">
                            <div
                                class="bg-zinc-50 dark:bg-primary-dark-900/60 px-4 py-2 border-b border-zinc-200 dark:border-primary-dark-700 font-semibold text-xs text-zinc-600 dark:text-primary-dark-300">
                                Parameters / Payload
                            </div>
                            <div class="p-3 bg-white dark:bg-primary-dark-800">
                                <x-atoms.code-block language="json" maxHeight="max-h-64">{{ json_encode($status['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</x-atoms.code-block>
                            </div>
                        </div>
                    @endif

                    @if ($status && !empty($status['headers']))
                        <div
                            class="mt-4 rounded-lg border border-zinc-200 dark:border-primary-dark-700 overflow-hidden text-sm">
                            <div
                                class="bg-zinc-50 dark:bg-primary-dark-900/60 px-4 py-2 border-b border-zinc-200 dark:border-primary-dark-700 font-semibold text-xs text-zinc-600 dark:text-primary-dark-300 flex justify-between">
                                <span>Request Headers</span>
                                @php
                                    $authMethod = 'Public / No Auth';
                                    foreach ($status['headers'] as $hKey => $hVal) {
                                        if (strtolower($hKey) === 'authorization') {
                                            $authMethod =
                                                'Authorization: ' .
                                                (str_contains($hVal, 'Bearer') ? 'Bearer Token' : 'Basic Auth');
                                            break;
                                        } elseif (
                                            strtolower($hKey) === 'x-api-key' ||
                                            strtolower($hKey) === 'x-goog-api-key'
                                        ) {
                                            $authMethod = 'API Key Header';
                                            break;
                                        } elseif (strtolower($hKey) === 'x-signature') {
                                            $authMethod = 'BPJS HMAC Signature';
                                            break;
                                        }
                                    }
                                @endphp
                                <span
                                    class="bg-blue-100 text-blue-700 text-[10px] px-2 py-0.5 rounded-full dark:bg-blue-900 dark:text-blue-300 ml-2 shadow-sm font-medium">Auth:
                                    {{ $authMethod }}</span>
                            </div>
                            <div class="p-3 bg-white dark:bg-primary-dark-800">
                                @php
                                    $scrubbedHeaders = [];
                                    foreach ($status['headers'] as $k => $v) {
                                        $lowerK = strtolower($k);
                                        if (in_array($lowerK, ['authorization', 'x-api-key', 'x-goog-api-key', 'x-signature'])) {
                                            $scrubbedHeaders[$k] = '[REDACTED]';
                                        } else {
                                            $scrubbedHeaders[$k] = $v;
                                        }
                                    }
                                @endphp
                                <x-atoms.code-block language="json" maxHeight="max-h-40">{{ json_encode($scrubbedHeaders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</x-atoms.code-block>
                            </div>
                        </div>
                    @endif
                @else
                    {{-- State awal sebelum test (seharusnya tidak terlihat karena loading langsung muncul) --}}
                    <div class="flex items-center justify-center py-8 text-sm text-zinc-400 dark:text-primary-dark-500">
                        Memulai pengujian...
                    </div>
                @endif
            </div>

            {{-- Tombol tutup + ulangi --}}
            <div class="flex justify-between items-center pt-1 border-t border-zinc-200 dark:border-primary-dark-700">
                @if ($status !== null)
                    <flux:button type="button" variant="ghost" icon="arrow-path" wire:click="runTest"
                        wire:loading.attr="disabled" wire:target="runTest">
                        Ulangi
                    </flux:button>
                @else
                @endif
                <button type="button" x-on:click="$flux.modal('{{ $name }}').close()"
                    class="px-4 py-2 text-sm font-medium text-zinc-600 dark:text-primary-dark-400 hover:text-zinc-900 dark:hover:text-primary-dark-100 hover:bg-zinc-100 dark:hover:bg-primary-dark-700 rounded-lg transition-colors">
                    Tutup
                </button>
            </div>

        </div>

    </x-organisms.modal>
</div>
