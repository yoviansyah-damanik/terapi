<?php

use App\Helpers\ConfigurationHelper;
use App\Services\GowaService;
use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts::app', ['title' => 'GOWA'])] class extends Component {
    public ?array $connectionStatus = null;
    public ?string $qrCode = null;
    public ?string $qrError = null;
    public ?string $pairingCode = null;
    public string $pairingPhone = '';
    public ?array $devices = null;

    // Pengaturan
    public string $apiUrl = '';
    public string $username = '';
    public string $password = '';
    public string $deviceId = '';
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
        $this->apiUrl = ConfigurationHelper::get('gowa.api_url', 'http://localhost:3000');
        $this->username = ConfigurationHelper::get('gowa.username', '');
        $this->password = ConfigurationHelper::get('gowa.password', '');
        $this->deviceId = ConfigurationHelper::get('gowa.device_id', '');
        $this->delay = ConfigurationHelper::get('gowa.delay', '3');
        $this->tries = ConfigurationHelper::get('gowa.tries', '3');
        $this->backoff = ConfigurationHelper::get('gowa.backoff', '10');

        $service = app(GowaService::class);
        $this->webhookUrl = $service->getWebhookUrl() ?? '';
    }

    /**
     * Cek status koneksi GOWA
     */
    public function checkConnection(): void
    {
        $this->qrCode = null;
        $this->qrError = null;
        $this->pairingCode = null;

        if (empty($this->apiUrl)) {
            $this->connectionStatus = [
                'connected' => false,
                'status' => 'FAILED',
                'message' => 'GOWA API URL belum dikonfigurasi',
            ];
            return;
        }

        try {
            $service = app(GowaService::class);
            $result = $service->getDevices();

            if ($result['success']) {
                $data = $result['data'] ?? [];

                $this->connectionStatus = [
                    'connected' => true,
                    'status' => 'CONNECTED',
                    'message' => 'Terhubung ke server GOWA',
                ];
                $this->devices = is_array($data) ? $data : [];
            } else {
                $statusCode = $result['status_code'] ?? 0;

                if ($statusCode === 401) {
                    $this->connectionStatus = [
                        'connected' => false,
                        'status' => 'UNAUTHORIZED',
                        'message' => 'Username atau password salah',
                    ];
                } else {
                    $this->connectionStatus = [
                        'connected' => false,
                        'status' => 'DISCONNECTED',
                        'message' => $result['message'] ?? 'Tidak terhubung ke WhatsApp. Silakan login.',
                    ];
                }
            }
        } catch (\Throwable $e) {
            $this->connectionStatus = [
                'connected' => false,
                'status' => 'ERROR',
                'message' => 'Tidak dapat terhubung: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Polling status koneksi
     */
    public function pollConnection(): void
    {
        if (empty($this->apiUrl)) {
            return;
        }

        try {
            $service = app(GowaService::class);
            $result = $service->getDevices();

            if ($result['success']) {
                $wasDisconnected = !($this->connectionStatus['connected'] ?? false);

                $this->connectionStatus = [
                    'connected' => true,
                    'status' => 'CONNECTED',
                    'message' => 'Terhubung ke server GOWA',
                ];
                $this->devices = is_array($result['data']) ? $result['data'] : [];

                if ($wasDisconnected) {
                    $this->qrCode = null;
                    $this->qrError = null;
                    $this->pairingCode = null;
                    $this->toastSuccess('WhatsApp berhasil terhubung!');
                }
            }
        } catch (\Throwable $e) {
            // Polling gagal, biarkan status lama
        }
    }

    /**
     * Login via QR Code
     */
    public function loginQr(): void
    {
        $this->pairingCode = null;

        try {
            $service = app(GowaService::class);
            $result = $service->login();

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
     * Login via Pairing Code
     */
    public function loginPairingCode(): void
    {
        $this->validate(
            [
                'pairingPhone' => 'required|string|min:10|max:15',
            ],
            [
                'pairingPhone.required' => 'Nomor telepon harus diisi',
            ],
        );

        $this->qrCode = null;
        $this->qrError = null;

        try {
            $service = app(GowaService::class);
            $result = $service->loginWithCode($this->pairingPhone);

            if ($result['success']) {
                $this->pairingCode = $result['data']['code'] ?? ($result['data']['pairing_code'] ?? ($result['message'] ?? null));
                $this->toastSuccess('Pairing code berhasil dibuat. Masukkan kode di WhatsApp HP Anda.');
            } else {
                $this->toastError($result['message'] ?? 'Gagal membuat pairing code');
            }
        } catch (\Throwable $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Logout dari WhatsApp
     */
    public function logoutWhatsapp(): void
    {
        try {
            $service = app(GowaService::class);
            $result = $service->logout();

            if ($result['success']) {
                $this->toastSuccess('Berhasil logout dari WhatsApp');
            } else {
                $this->toastError($result['message'] ?? 'Gagal logout');
            }

            sleep(2);
            $this->checkConnection();
        } catch (\Throwable $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Reconnect ke WhatsApp
     */
    public function reconnectWhatsapp(): void
    {
        try {
            $service = app(GowaService::class);
            $result = $service->reconnect();

            if ($result['success']) {
                $this->toastSuccess('Berhasil reconnect');
                sleep(2);
                $this->checkConnection();
            } else {
                $this->toastError($result['message'] ?? 'Gagal reconnect');
                $this->checkConnection();
            }
        } catch (\Throwable $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Simpan pengaturan GOWA
     */
    public function saveSettings(): void
    {
        $this->validate(
            [
                'apiUrl' => 'required|url',
                'delay' => 'required|integer|min:0|max:60',
                'tries' => 'required|integer|min:1|max:10',
                'backoff' => 'required|integer|min:1|max:300',
            ],
            [
                'apiUrl.required' => 'API URL harus diisi',
                'apiUrl.url' => 'Format API URL tidak valid',
                'delay.required' => 'Delay harus diisi',
                'delay.integer' => 'Delay harus berupa angka',
                'tries.required' => 'Retry harus diisi',
                'tries.integer' => 'Retry harus berupa angka',
                'backoff.required' => 'Backoff harus diisi',
                'backoff.integer' => 'Backoff harus berupa angka',
            ],
        );

        ConfigurationHelper::set('gowa.api_url', $this->apiUrl);
        ConfigurationHelper::set('gowa.username', $this->username);
        ConfigurationHelper::set('gowa.password', $this->password);
        ConfigurationHelper::set('gowa.device_id', $this->deviceId);
        ConfigurationHelper::set('gowa.delay', $this->delay);
        ConfigurationHelper::set('gowa.tries', $this->tries);
        ConfigurationHelper::set('gowa.backoff', $this->backoff);

        $this->toastSuccess('Konfigurasi GOWA berhasil disimpan');
    }
};
?>

@php
    $isConnected = $connectionStatus['connected'] ?? false;
    $isWaitingQr = $qrCode !== null;
@endphp

<div wire:init="checkConnection" @if ($isWaitingQr) wire:poll.15s="loginQr" @endif
    @if (
        $connectionStatus &&
            !$isConnected &&
            !in_array($connectionStatus['status'] ?? '', ['FAILED', 'ERROR', 'UNAUTHORIZED'])) wire:poll.5s="pollConnection" @endif>

    {{-- Header --}}
    <x-ui.page-header title="Konfigurasi GOWA"
        subtitle="Pengaturan koneksi WhatsApp Gateway (Go-WhatsApp-Web-Multidevice)" />

    {{-- Status Koneksi --}}
    <div class="grid grid-cols-1 gap-6 mb-6 lg:grid-cols-2">
        {{-- Status Panel --}}
        <div
            class="p-6 bg-white border rounded-lg dark:bg-primary-dark-800 border-zinc-200 dark:border-primary-dark-700">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Status Koneksi</h2>
                <x-atoms.button variant="subtle" size="sm" icon="arrow-path" wire:click="checkConnection"
                    wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="checkConnection">Refresh</span>
                    <span wire:loading wire:target="checkConnection">Memeriksa...</span>
                </x-atoms.button>
            </div>

            @if ($connectionStatus)
                <div class="space-y-4">
                    {{-- Indikator Status --}}
                    @php
                        $bgClass = $isConnected
                            ? 'bg-emerald-50 dark:bg-emerald-950/30'
                            : ($isWaitingQr
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
                            @elseif ($isWaitingQr)
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
                                class="font-semibold {{ $isConnected ? 'text-emerald-700 dark:text-emerald-300' : ($isWaitingQr ? 'text-amber-700 dark:text-amber-300' : 'text-red-700 dark:text-red-300') }}">
                                {{ $isConnected ? 'Terhubung' : ($isWaitingQr ? 'Menunggu Scan QR' : 'Tidak Terhubung') }}
                            </p>
                            <p
                                class="text-sm {{ $isConnected ? 'text-emerald-600 dark:text-emerald-400' : ($isWaitingQr ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400') }}">
                                {{ $connectionStatus['message'] }}
                            </p>
                        </div>
                    </div>

                    {{-- Detail --}}
                    <div class="space-y-3">
                        <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                            <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Status</span>
                            <flux:badge :color="$isConnected ? 'green' : ($isWaitingQr ? 'yellow' : 'red')"
                                size="sm">
                                {{ $connectionStatus['status'] }}
                            </flux:badge>
                        </div>
                    </div>

                    {{-- QR Code Section --}}
                    @if ($qrCode)
                        <div class="pt-4 border-t border-zinc-200 dark:border-primary-dark-700">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-300">
                                    Scan QR Code dengan WhatsApp
                                </h3>
                                <x-atoms.button variant="subtle" size="sm" icon="arrow-path" wire:click="loginQr"
                                    wire:loading.attr="disabled" wire:target="loginQr">
                                    <span wire:loading.remove wire:target="loginQr">Refresh QR</span>
                                    <span wire:loading wire:target="loginQr">Memuat...</span>
                                </x-atoms.button>
                            </div>
                            <div class="flex flex-col items-center gap-3">
                                <img src="{{ $qrCode }}" alt="GOWA QR Code"
                                    class="max-w-[280px] rounded border border-zinc-200 dark:border-primary-dark-700 bg-white p-2" />
                                <p class="text-xs text-zinc-400">QR Code diperbarui otomatis tiap 15 detik</p>
                            </div>
                            <div class="mt-3 p-3 rounded-lg bg-blue-50 dark:bg-blue-950/30">
                                <p class="text-xs text-blue-700 dark:text-blue-300">
                                    <strong>Cara scan:</strong> Buka WhatsApp di HP &rarr; Menu &rarr; Perangkat Tertaut
                                    &rarr; Tautkan Perangkat &rarr; Arahkan kamera ke QR Code di atas.
                                </p>
                            </div>
                        </div>
                    @endif

                    {{-- Pairing Code Section --}}
                    @if ($pairingCode)
                        <div class="pt-4 border-t border-zinc-200 dark:border-primary-dark-700">
                            <h3 class="mb-3 text-sm font-semibold text-zinc-700 dark:text-primary-dark-300">Pairing Code
                            </h3>
                            <div
                                class="flex items-center justify-center p-4 rounded-lg bg-emerald-50 dark:bg-emerald-950/30">
                                <span
                                    class="text-2xl font-mono font-bold tracking-widest text-emerald-700 dark:text-emerald-300">
                                    {{ $pairingCode }}
                                </span>
                            </div>
                            <div class="mt-3 p-3 rounded-lg bg-blue-50 dark:bg-blue-950/30">
                                <p class="text-xs text-blue-700 dark:text-blue-300">
                                    <strong>Cara pairing:</strong> Buka WhatsApp di HP &rarr; Menu &rarr; Perangkat
                                    Tertaut &rarr; Tautkan Perangkat &rarr; Pilih "Tautkan dengan Nomor Telepon" &rarr;
                                    Masukkan kode di atas.
                                </p>
                            </div>
                        </div>
                    @endif

                    {{-- QR Error --}}
                    @if ($qrError)
                        <div class="p-3 rounded-lg bg-red-50 dark:bg-red-950/30">
                            <p class="text-sm text-red-600 dark:text-red-400">{{ $qrError }}</p>
                        </div>
                    @endif

                    {{-- Tombol Aksi --}}
                    <div class="flex flex-wrap gap-2 pt-2">
                        @if (!$isConnected)
                            <x-atoms.button variant="primary" size="sm" icon="qr-code" wire:click="loginQr"
                                wire:loading.attr="disabled" wire:target="loginQr">
                                <span wire:loading.remove wire:target="loginQr">Login (QR)</span>
                                <span wire:loading wire:target="loginQr">Memuat...</span>
                            </x-atoms.button>

                            <div class="flex items-end gap-2">
                                <flux:input wire:model="pairingPhone" placeholder="08xxxxxxxxxx" size="sm"
                                    class="w-40" />
                                <x-atoms.button variant="filled" size="sm" icon="device-phone-mobile"
                                    wire:click="loginPairingCode" wire:loading.attr="disabled"
                                    wire:target="loginPairingCode">
                                    <span wire:loading.remove wire:target="loginPairingCode">Pairing Code</span>
                                    <span wire:loading wire:target="loginPairingCode">Memproses...</span>
                                </x-atoms.button>
                            </div>
                        @endif
                        @if ($isConnected)
                            <x-atoms.button variant="filled" size="sm" icon="arrow-path"
                                wire:click="reconnectWhatsapp" wire:loading.attr="disabled"
                                wire:target="reconnectWhatsapp">
                                <span wire:loading.remove wire:target="reconnectWhatsapp">Reconnect</span>
                                <span wire:loading wire:target="reconnectWhatsapp">Reconnect...</span>
                            </x-atoms.button>
                            <x-atoms.button variant="danger" size="sm" icon="arrow-right-start-on-rectangle"
                                wire:click="logoutWhatsapp" wire:loading.attr="disabled" wire:target="logoutWhatsapp"
                                wire:confirm="Yakin ingin logout? Anda perlu login ulang.">
                                <span wire:loading.remove wire:target="logoutWhatsapp">Logout</span>
                                <span wire:loading wire:target="logoutWhatsapp">Logout...</span>
                            </x-atoms.button>
                        @endif
                    </div>

                    {{-- Devices --}}
                    @if ($isConnected && $devices)
                        <div class="pt-4 border-t border-zinc-200 dark:border-primary-dark-700">
                            <h3 class="mb-2 text-sm font-semibold text-zinc-700 dark:text-primary-dark-300">Device
                                Terhubung
                            </h3>
                            <x-atoms.code-block language="json" maxHeight="max-h-40">{{ json_encode($devices, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</x-atoms.code-block>
                        </div>
                    @endif
                </div>
            @else
                <div class="flex items-center justify-center h-32">
                    <div class="text-center">
                        <flux:icon name="arrow-path"
                            class="w-8 h-8 mx-auto mb-2 text-zinc-300 dark:text-primary-dark-500 animate-spin" />
                        <p class="text-sm text-zinc-400">Memeriksa status koneksi...</p>
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
                    <span class="text-sm text-zinc-500 dark:text-primary-dark-400">GOWA API URL</span>
                    <span class="text-sm font-mono text-zinc-900 dark:text-primary-dark-100">
                        {{ $apiUrl ?: '-' }}
                    </span>
                </div>
                <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                    <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Username</span>
                    <span class="text-sm font-mono text-zinc-900 dark:text-primary-dark-100">
                        {{ $username ?: '-' }}
                    </span>
                </div>
                <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                    <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Password</span>
                    <span class="text-sm font-mono text-zinc-900 dark:text-primary-dark-100">
                        {{ $password ? str_repeat('*', min(strlen($password), 20)) : '-' }}
                    </span>
                </div>
                <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                    <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Device ID</span>
                    <span class="text-sm font-mono text-zinc-900 dark:text-primary-dark-100">
                        {{ $deviceId ?: '-' }}
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
                        <p class="mt-1 text-xs text-zinc-400">Konfigurasikan URL ini di GOWA server sebagai webhook</p>
                    @else
                        <p class="mt-1 text-sm text-zinc-400">Belum dikonfigurasi</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Form Pengaturan --}}
    <div class="p-6 bg-white border rounded-lg dark:bg-primary-dark-800 border-zinc-200 dark:border-primary-dark-700">
        <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Pengaturan GOWA Gateway</h2>

        <form wire:submit="saveSettings" class="space-y-5">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <flux:field>
                    <flux:label>GOWA API URL</flux:label>
                    <flux:input wire:model="apiUrl" placeholder="http://localhost:3000" />
                    @error('apiUrl')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>

                <flux:field>
                    <flux:label>Username (Basic Auth)</flux:label>
                    <flux:input wire:model="username" placeholder="Username" />
                </flux:field>

                <flux:field>
                    <flux:label>Password (Basic Auth)</flux:label>
                    <flux:input type="password" wire:model="password" placeholder="Password" />
                </flux:field>

                <flux:field>
                    <flux:label>Device ID</flux:label>
                    <flux:input wire:model="deviceId" placeholder="Opsional" />
                    <p class="mt-1 text-xs text-zinc-400">Header X-Device-Id (kosongkan jika tidak perlu)</p>
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
