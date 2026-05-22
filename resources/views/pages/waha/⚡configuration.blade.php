<?php

use App\Helpers\ConfigurationHelper;
use App\Services\WahaService;
use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts::app', ['title' => 'WhatsApp'])] class extends Component {
    public ?array $sessionStatus = null;
    public ?string $qrCode = null;
    public ?string $qrError = null;

    // Pengaturan
    public string $apiUrl = '';
    public string $apiKey = '';
    public string $sessionName = '';
    public string $delay = '3';
    public string $tries = '3';
    public string $backoff = '10';
    public string $webhookUrl = '';

    public function mount(): void
    {
        $this->loadSettings();
    }

    private function loadSettings(): void
    {
        $this->apiUrl = ConfigurationHelper::get('whatsapp.api_url', 'http://localhost:3000');
        $this->apiKey = ConfigurationHelper::get('whatsapp.api_key', '');
        $this->sessionName = ConfigurationHelper::get('whatsapp.session', 'default');
        $this->delay = ConfigurationHelper::get('whatsapp.delay', '3');
        $this->tries = ConfigurationHelper::get('whatsapp.tries', '3');
        $this->backoff = ConfigurationHelper::get('whatsapp.backoff', '10');

        $service = app(WahaService::class);
        $this->webhookUrl = $service->getWebhookUrl() ?? '';
    }

    /**
     * Cek status session WAHA
     */
    public function checkSession(): void
    {
        $this->qrCode = null;
        $this->qrError = null;

        if (empty($this->apiUrl)) {
            $this->sessionStatus = [
                'connected' => false,
                'status' => 'FAILED',
                'message' => 'WAHA API URL belum dikonfigurasi',
            ];
            return;
        }

        try {
            $service = app(WahaService::class);
            $result = $service->getSessionStatus();

            if ($result['success']) {
                $data = $result['data'] ?? [];
                $status = $data['status'] ?? 'UNKNOWN';

                $this->sessionStatus = [
                    'connected' => in_array($status, ['WORKING', 'AUTHENTICATED']),
                    'status' => $status,
                    'message' => match ($status) {
                        'WORKING', 'AUTHENTICATED' => 'Session aktif dan terhubung',
                        'SCAN_QR_CODE' => 'Menunggu scan QR Code',
                        'STARTING' => 'Session sedang dimulai...',
                        'STOPPED' => 'Session dihentikan',
                        default => "Status: {$status}",
                    },
                    'name' => $data['name'] ?? $this->sessionName,
                ];

                if ($status === 'SCAN_QR_CODE') {
                    $this->loadQrCode();
                }
            } else {
                $statusCode = $result['status_code'] ?? 0;

                // 404 = session belum ada
                if ($statusCode === 404) {
                    $this->sessionStatus = [
                        'connected' => false,
                        'status' => 'NOT_FOUND',
                        'message' => 'Session belum dibuat. Klik "Start Session" untuk memulai.',
                    ];
                } else {
                    $this->sessionStatus = [
                        'connected' => false,
                        'status' => 'FAILED',
                        'message' => $result['message'] ?? ($result['error'] ?? 'Gagal memeriksa status session'),
                    ];
                }
            }
        } catch (\Throwable $e) {
            $this->sessionStatus = [
                'connected' => false,
                'status' => 'ERROR',
                'message' => 'Tidak dapat terhubung: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Polling: cek status session (dipanggil otomatis tiap N detik oleh wire:poll)
     */
    public function pollSession(): void
    {
        if (empty($this->apiUrl)) {
            return;
        }

        try {
            $service = app(WahaService::class);
            $result = $service->getSessionStatus();

            if (!$result['success']) {
                return;
            }

            $data = $result['data'] ?? [];
            $newStatus = $data['status'] ?? 'UNKNOWN';
            $oldStatus = $this->sessionStatus['status'] ?? null;

            // Update status
            $this->sessionStatus = [
                'connected' => in_array($newStatus, ['WORKING', 'AUTHENTICATED']),
                'status' => $newStatus,
                'message' => match ($newStatus) {
                    'WORKING', 'AUTHENTICATED' => 'Session aktif dan terhubung',
                    'SCAN_QR_CODE' => 'Menunggu scan QR Code',
                    'STARTING' => 'Session sedang dimulai...',
                    'STOPPED' => 'Session dihentikan',
                    default => "Status: {$newStatus}",
                },
                'name' => $data['name'] ?? $this->sessionName,
            ];

            // Jika berhasil terhubung setelah scan QR
            if (in_array($newStatus, ['WORKING', 'AUTHENTICATED']) && $oldStatus === 'SCAN_QR_CODE') {
                $this->qrCode = null;
                $this->qrError = null;
                $this->toastSuccess('WhatsApp berhasil terhubung!');
            }
        } catch (\Throwable $e) {
            // Polling gagal, biarkan status lama
        }
    }

    /**
     * Mulai session WAHA
     */
    public function startSession(): void
    {
        try {
            $service = app(WahaService::class);
            $result = $service->startSession();

            if ($result['success']) {
                $this->toastSuccess($result['message'] ?? 'Session berhasil dimulai');
                sleep(2);
                $this->checkSession();
            } else {
                $this->toastError($result['message'] ?? ($result['data']['message'] ?? 'Gagal memulai session'));
                $this->checkSession();
            }
        } catch (\Throwable $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Hentikan session WAHA
     */
    public function stopSession(): void
    {
        try {
            $service = app(WahaService::class);
            $result = $service->stopSession();

            if ($result['success']) {
                $this->toastSuccess('Session berhasil dihentikan');
            } else {
                $this->toastError($result['message'] ?? 'Gagal menghentikan session');
            }

            $this->checkSession();
        } catch (\Throwable $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Restart session WAHA
     */
    public function restartSession(): void
    {
        try {
            $service = app(WahaService::class);
            $result = $service->restartSession();

            if ($result['success']) {
                $this->toastSuccess('Session berhasil di-restart');
                sleep(2);
                $this->checkSession();
            } else {
                $this->toastError($result['message'] ?? 'Gagal me-restart session');
                $this->checkSession();
            }
        } catch (\Throwable $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Logout session WAHA (putuskan koneksi WhatsApp)
     */
    public function logoutSession(): void
    {
        try {
            $service = app(WahaService::class);
            $result = $service->logoutSession();

            if ($result['success']) {
                $this->toastSuccess('Session berhasil di-logout');
            } else {
                $this->toastError($result['message'] ?? 'Gagal logout session');
            }

            sleep(2);
            $this->checkSession();
        } catch (\Throwable $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Muat QR Code dari WAHA
     */
    public function loadQrCode(): void
    {
        $this->qrError = null;

        try {
            $service = app(WahaService::class);
            $result = $service->getQrCode();

            if ($result['success'] && isset($result['data'])) {
                $this->qrCode = is_string($result['data']) ? $result['data'] : null;
                if (!$this->qrCode) {
                    $this->qrError = 'Format QR code tidak dikenali';
                }
            } else {
                $this->qrCode = null;
                $this->qrError = $result['message'] ?? 'Gagal mengambil QR code';
            }
        } catch (\Throwable $e) {
            $this->qrCode = null;
            $this->qrError = 'Error: ' . $e->getMessage();
        }
    }

    /**
     * Simpan pengaturan WhatsApp
     */
    public function saveSettings(): void
    {
        $this->validate(
            [
                'apiUrl' => 'required|url',
                'sessionName' => 'required|string|max:50',
                'delay' => 'required|integer|min:0|max:60',
                'tries' => 'required|integer|min:1|max:10',
                'backoff' => 'required|integer|min:1|max:300',
            ],
            [
                'apiUrl.required' => 'API URL harus diisi',
                'apiUrl.url' => 'Format API URL tidak valid',
                'sessionName.required' => 'Nama session harus diisi',
                'delay.required' => 'Delay harus diisi',
                'delay.integer' => 'Delay harus berupa angka',
                'tries.required' => 'Retry harus diisi',
                'tries.integer' => 'Retry harus berupa angka',
                'backoff.required' => 'Backoff harus diisi',
                'backoff.integer' => 'Backoff harus berupa angka',
            ],
        );

        ConfigurationHelper::set('whatsapp.api_url', $this->apiUrl);
        ConfigurationHelper::set('whatsapp.api_key', $this->apiKey);
        ConfigurationHelper::set('whatsapp.session', $this->sessionName);
        ConfigurationHelper::set('whatsapp.delay', $this->delay);
        ConfigurationHelper::set('whatsapp.tries', $this->tries);
        ConfigurationHelper::set('whatsapp.backoff', $this->backoff);

        $this->toastSuccess('Konfigurasi WhatsApp berhasil disimpan');
    }
};
?>

{{-- Polling otomatis: refresh QR tiap 15 detik saat menunggu scan, cek status tiap 5 detik --}}
@php
    $isWaitingQr = ($sessionStatus['status'] ?? '') === 'SCAN_QR_CODE';
@endphp

<div wire:init="checkSession" @if ($isWaitingQr) wire:poll.15s="loadQrCode" @endif
    @if (
        $sessionStatus &&
            !($sessionStatus['connected'] ?? false) &&
            ($sessionStatus['status'] ?? '') !== 'STOPPED' &&
            ($sessionStatus['status'] ?? '') !== 'NOT_FOUND' &&
            ($sessionStatus['status'] ?? '') !== 'FAILED' &&
            ($sessionStatus['status'] ?? '') !== 'ERROR') wire:poll.5s="pollSession" @endif>

    {{-- Header --}}
    <x-ui.page-header title="Konfigurasi WhatsApp" subtitle="Pengaturan koneksi dan session WhatsApp Gateway (WAHA)" />

    {{-- Status Koneksi --}}
    <div class="grid grid-cols-1 gap-6 mb-6 lg:grid-cols-2">
        {{-- Status Session --}}
        <div
            class="p-6 bg-white border rounded-lg dark:bg-primary-dark-800 border-zinc-200 dark:border-primary-dark-700">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Status Session</h2>
                <x-atoms.button variant="subtle" size="sm" icon="arrow-path" wire:click="checkSession"
                    wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="checkSession">Refresh</span>
                    <span wire:loading wire:target="checkSession">Memeriksa...</span>
                </x-atoms.button>
            </div>

            @if ($sessionStatus)
                <div class="space-y-4">
                    {{-- Indikator Status --}}
                    @php
                        $isConnected = $sessionStatus['connected'];
                        $isQr = $sessionStatus['status'] === 'SCAN_QR_CODE';
                        $bgClass = $isConnected
                            ? 'bg-emerald-50 dark:bg-emerald-950/30'
                            : ($isQr
                                ? 'bg-amber-50 dark:bg-amber-950/30'
                                : 'bg-red-50 dark:bg-red-950/30');
                    @endphp
                    <div class="flex items-center gap-3 p-4 rounded-lg {{ $bgClass }}">
                        <div class="flex-shrink-0">
                            @if ($isConnected)
                                <div
                                    class="flex items-center justify-center w-10 h-10 rounded-full bg-emerald-100 dark:bg-emerald-900/50">
                                    <flux:icon name="check-circle"
                                        class="w-6 h-6 text-emerald-600 dark:text-emerald-400" />
                                </div>
                            @elseif ($isQr)
                                <div
                                    class="flex items-center justify-center w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/50">
                                    <flux:icon name="qr-code" class="w-6 h-6 text-amber-600 dark:text-amber-400" />
                                </div>
                            @else
                                <div
                                    class="flex items-center justify-center w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/50">
                                    <flux:icon name="x-circle" class="w-6 h-6 text-red-600 dark:text-red-400" />
                                </div>
                            @endif
                        </div>
                        <div>
                            <p
                                class="font-semibold {{ $isConnected ? 'text-emerald-700 dark:text-emerald-300' : ($isQr ? 'text-amber-700 dark:text-amber-300' : 'text-red-700 dark:text-red-300') }}">
                                {{ $isConnected ? 'Terhubung' : ($isQr ? 'Menunggu Scan QR' : 'Tidak Terhubung') }}
                            </p>
                            <p
                                class="text-sm {{ $isConnected ? 'text-emerald-600 dark:text-emerald-400' : ($isQr ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400') }}">
                                {{ $sessionStatus['message'] }}
                            </p>
                        </div>
                    </div>

                    {{-- Detail Session --}}
                    <div class="space-y-3">
                        <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                            <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Session</span>
                            <span class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">
                                {{ $sessionStatus['name'] ?? $sessionName }}
                            </span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                            <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Status</span>
                            <flux:badge :color="$isConnected ? 'green' : ($isQr ? 'yellow' : 'red')" size="sm">
                                {{ $sessionStatus['status'] }}
                            </flux:badge>
                        </div>
                    </div>

                    {{-- QR Code Section --}}
                    @if ($isQr)
                        <div class="pt-4 border-t border-zinc-200 dark:border-primary-dark-700">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-300">
                                    Scan QR Code dengan WhatsApp
                                </h3>
                                <x-atoms.button variant="subtle" size="sm" icon="arrow-path" wire:click="loadQrCode"
                                    wire:loading.attr="disabled" wire:target="loadQrCode">
                                    <span wire:loading.remove wire:target="loadQrCode">Refresh QR</span>
                                    <span wire:loading wire:target="loadQrCode">Memuat...</span>
                                </x-atoms.button>
                            </div>

                            @if ($qrCode)
                                <div class="flex flex-col items-center gap-3">
                                    <img src="{{ $qrCode }}" alt="WhatsApp QR Code"
                                        class="max-w-[280px] rounded border border-zinc-200 dark:border-primary-dark-700 bg-white p-2" />
                                    <p class="text-xs text-zinc-400">QR Code diperbarui otomatis tiap 15 detik</p>
                                </div>
                            @else
                                <div
                                    class="flex flex-col items-center justify-center h-48 rounded bg-zinc-100 dark:bg-primary-dark-700">
                                    <div wire:loading wire:target="loadQrCode" class="text-center">
                                        <flux:icon name="arrow-path"
                                            class="w-8 h-8 mx-auto mb-2 text-zinc-400 animate-spin" />
                                        <p class="text-sm text-zinc-400">Memuat QR Code...</p>
                                    </div>
                                    <div wire:loading.remove wire:target="loadQrCode" class="text-center">
                                        <flux:icon name="qr-code" class="w-8 h-8 mx-auto mb-2 text-zinc-400" />
                                        <p class="text-sm text-zinc-400">{{ $qrError ?? 'QR Code tidak tersedia' }}</p>
                                        <x-atoms.button variant="subtle" size="sm" class="mt-2"
                                            wire:click="loadQrCode">
                                            Coba Lagi
                                        </x-atoms.button>
                                    </div>
                                </div>
                            @endif

                            <div class="mt-3 p-3 rounded-lg bg-blue-50 dark:bg-blue-950/30">
                                <p class="text-xs text-blue-700 dark:text-blue-300">
                                    <strong>Cara scan:</strong> Buka WhatsApp di HP &rarr; Menu &rarr; Perangkat Tertaut
                                    &rarr; Tautkan Perangkat &rarr; Arahkan kamera ke QR Code di atas.
                                </p>
                            </div>
                        </div>
                    @endif

                    {{-- Tombol Aksi --}}
                    <div class="flex flex-wrap gap-2 pt-2">
                        @if (!$isConnected && !$isQr)
                            <x-atoms.button variant="primary" size="sm" icon="play" wire:click="startSession"
                                wire:loading.attr="disabled" wire:target="startSession">
                                <span wire:loading.remove wire:target="startSession">Start Session</span>
                                <span wire:loading wire:target="startSession">Memulai...</span>
                            </x-atoms.button>
                        @endif
                        @if ($isConnected)
                            <x-atoms.button variant="filled" size="sm" icon="arrow-path" wire:click="restartSession"
                                wire:loading.attr="disabled" wire:target="restartSession">
                                <span wire:loading.remove wire:target="restartSession">Restart</span>
                                <span wire:loading wire:target="restartSession">Restart...</span>
                            </x-atoms.button>
                            <x-atoms.button variant="danger" size="sm" icon="arrow-right-start-on-rectangle"
                                wire:click="logoutSession" wire:loading.attr="disabled" wire:target="logoutSession"
                                wire:confirm="Yakin ingin logout session? Anda perlu scan QR Code ulang.">
                                <span wire:loading.remove wire:target="logoutSession">Logout</span>
                                <span wire:loading wire:target="logoutSession">Logout...</span>
                            </x-atoms.button>
                        @endif
                        @if (
                            $sessionStatus['status'] !== 'STOPPED' &&
                                $sessionStatus['status'] !== 'NOT_FOUND' &&
                                $sessionStatus['status'] !== 'FAILED' &&
                                $sessionStatus['status'] !== 'ERROR')
                            <x-atoms.button variant="danger" size="sm" icon="stop" wire:click="stopSession"
                                wire:loading.attr="disabled" wire:target="stopSession">
                                <span wire:loading.remove wire:target="stopSession">Stop</span>
                                <span wire:loading wire:target="stopSession">Menghentikan...</span>
                            </x-atoms.button>
                        @endif
                    </div>
                </div>
            @else
                <div class="flex items-center justify-center h-32">
                    <div class="text-center">
                        <flux:icon name="arrow-path"
                            class="w-8 h-8 mx-auto mb-2 text-zinc-300 dark:text-primary-dark-500 animate-spin" />
                        <p class="text-sm text-zinc-400">Memeriksa status session...</p>
                    </div>
                </div>
            @endif
        </div>

        {{-- Informasi Konfigurasi --}}
        <div
            class="p-6 bg-white border rounded-lg dark:bg-primary-dark-800 border-zinc-200 dark:border-primary-dark-700">
            <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Informasi Konfigurasi</h2>
            <div class="space-y-3">
                <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                    <span class="text-sm text-zinc-500 dark:text-primary-dark-400">WAHA API URL</span>
                    <span class="text-sm font-mono text-zinc-900 dark:text-primary-dark-100">
                        {{ $apiUrl ?: '-' }}
                    </span>
                </div>
                <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                    <span class="text-sm text-zinc-500 dark:text-primary-dark-400">API Key</span>
                    <span class="text-sm font-mono text-zinc-900 dark:text-primary-dark-100">
                        {{ $apiKey ? str_repeat('*', min(strlen($apiKey), 20)) : '-' }}
                    </span>
                </div>
                <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                    <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Session</span>
                    <span class="text-sm font-mono text-zinc-900 dark:text-primary-dark-100">
                        {{ $sessionName ?: '-' }}
                    </span>
                </div>
                <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                    <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Delay Antar Pesan</span>
                    <span class="text-sm font-mono text-zinc-900 dark:text-primary-dark-100">{{ $delay }}
                        detik</span>
                </div>
                <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                    <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Retry</span>
                    <span
                        class="text-sm font-mono text-zinc-900 dark:text-primary-dark-100">{{ $tries }}x</span>
                </div>
                <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                    <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Backoff</span>
                    <span class="text-sm font-mono text-zinc-900 dark:text-primary-dark-100">{{ $backoff }}
                        detik</span>
                </div>

                {{-- Webhook URL --}}
                <div class="py-2">
                    <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Webhook URL</span>
                    @if ($webhookUrl)
                        <div class="flex items-center gap-2 mt-1">
                            <code
                                class="text-xs bg-zinc-100 dark:bg-primary-dark-700 px-2 py-1 rounded text-zinc-800 dark:text-primary-dark-200 break-all">
                                {{ $webhookUrl }}
                            </code>
                        </div>
                        <p class="mt-1 text-xs text-zinc-400">URL ini akan otomatis dikirim ke WAHA saat Start Session
                        </p>
                    @else
                        <p class="mt-1 text-sm text-zinc-400">Belum dikonfigurasi</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Form Pengaturan --}}
    <div class="p-6 bg-white border rounded-lg dark:bg-primary-dark-800 border-zinc-200 dark:border-primary-dark-700">
        <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Pengaturan WhatsApp Gateway
        </h2>

        <form wire:submit="saveSettings" class="space-y-5">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <flux:field>
                    <flux:label>WAHA API URL</flux:label>
                    <flux:input wire:model="apiUrl" placeholder="http://localhost:3000" />
                    @error('apiUrl')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>

                <flux:field>
                    <flux:label>API Key</flux:label>
                    <flux:input type="password" wire:model="apiKey" placeholder="Kosongkan jika tidak ada" />
                    @error('apiKey')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>

                <flux:field>
                    <flux:label>Nama Session</flux:label>
                    <flux:input wire:model="sessionName" placeholder="default" />
                    @error('sessionName')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                <flux:field>
                    <flux:label>Delay Antar Pesan (detik)</flux:label>
                    <flux:input type="number" wire:model="delay" min="0" max="60" />
                    @error('delay')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                    <p class="mt-1 text-xs text-zinc-400">Jeda antar pengiriman pesan</p>
                </flux:field>

                <flux:field>
                    <flux:label>Retry</flux:label>
                    <flux:input type="number" wire:model="tries" min="1" max="10" />
                    @error('tries')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                    <p class="mt-1 text-xs text-zinc-400">Jumlah percobaan ulang jika gagal</p>
                </flux:field>

                <flux:field>
                    <flux:label>Backoff (detik)</flux:label>
                    <flux:input type="number" wire:model="backoff" min="1" max="300" />
                    @error('backoff')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                    <p class="mt-1 text-xs text-zinc-400">Jeda antar percobaan ulang</p>
                </flux:field>
            </div>

            <div class="flex justify-end pt-2">
                <x-atoms.button type="submit" variant="primary" icon="check">
                    Simpan Konfigurasi
                </x-atoms.button>
            </div>
        </form>
    </div>
</div>
