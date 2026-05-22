<?php

use App\Helpers\ConfigurationHelper;
use App\Services\WahaService;
use App\Services\GowaService;
use Livewire\Component;

new class extends Component {
    public string $activeGateway = 'waha';

    // === WAHA ===
    public ?array $sessionStatus = null;
    public ?string $qrCode = null;
    public ?string $qrError = null;
    public string $wahaApiUrl = '';
    public string $wahaApiKey = '';
    public string $wahaSessionName = '';
    public string $wahaDelay = '3';
    public string $wahaTries = '3';
    public string $wahaBackoff = '10';
    public string $wahaWebhookUrl = '';

    // === GOWA ===
    public ?array $connectionStatus = null;
    public ?string $gowaQrCode = null;
    public ?string $gowaQrError = null;
    public ?string $pairingCode = null;
    public string $pairingPhone = '';
    public ?array $devices = null;
    public string $gowaApiUrl = '';
    public string $gowaUsername = '';
    public string $gowaPassword = '';
    public string $gowaDeviceId = '';
    public string $gowaDelay = '3';
    public string $gowaTries = '3';
    public string $gowaBackoff = '10';
    public string $gowaWebhookUrl = '';

    public function mount(): void
    {
        $this->activeGateway = ConfigurationHelper::get('whatsapp.active_gateway', 'waha');
        $this->loadWahaSettings();
        $this->loadGowaSettings();
    }

    /**
     * Switch gateway aktif
     */
    public function switchGateway(string $gateway): void
    {
        if (!in_array($gateway, ['waha', 'gowa'])) {
            return;
        }

        $this->activeGateway = $gateway;
        ConfigurationHelper::set('whatsapp.active_gateway', $gateway);
        $this->toastSuccess('Gateway aktif diubah ke ' . strtoupper($gateway));

        // Reset status
        $this->sessionStatus = null;
        $this->qrCode = null;
        $this->qrError = null;
        $this->connectionStatus = null;
        $this->gowaQrCode = null;
        $this->gowaQrError = null;
        $this->pairingCode = null;
        $this->devices = null;
    }

    // =============================================
    // WAHA Methods
    // =============================================

    private function loadWahaSettings(): void
    {
        $this->wahaApiUrl = ConfigurationHelper::get('whatsapp.api_url', 'http://localhost:3000');
        $this->wahaApiKey = ConfigurationHelper::get('whatsapp.api_key', '');
        $this->wahaSessionName = ConfigurationHelper::get('whatsapp.session', 'default');
        $this->wahaDelay = ConfigurationHelper::get('whatsapp.delay', '3');
        $this->wahaTries = ConfigurationHelper::get('whatsapp.tries', '3');
        $this->wahaBackoff = ConfigurationHelper::get('whatsapp.backoff', '10');

        $service = app(WahaService::class);
        $this->wahaWebhookUrl = $service->getWebhookUrl() ?? '';
    }

    public function checkSession(): void
    {
        $this->qrCode = null;
        $this->qrError = null;

        if (empty($this->wahaApiUrl)) {
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
                    'name' => $data['name'] ?? $this->wahaSessionName,
                ];

                if ($status === 'SCAN_QR_CODE') {
                    $this->loadQrCode();
                }
            } else {
                $statusCode = $result['status_code'] ?? 0;

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

    public function pollSession(): void
    {
        if (empty($this->wahaApiUrl)) {
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
                'name' => $data['name'] ?? $this->wahaSessionName,
            ];

            if (in_array($newStatus, ['WORKING', 'AUTHENTICATED']) && $oldStatus === 'SCAN_QR_CODE') {
                $this->qrCode = null;
                $this->qrError = null;
                $this->toastSuccess('WhatsApp berhasil terhubung!');
            }
        } catch (\Throwable $e) {
            // Polling gagal, biarkan status lama
        }
    }

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

    public function saveWahaSettings(): void
    {
        $this->validate(
            [
                'wahaApiUrl' => 'required|url',
                'wahaSessionName' => 'required|string|max:50',
                'wahaDelay' => 'required|integer|min:0|max:60',
                'wahaTries' => 'required|integer|min:1|max:10',
                'wahaBackoff' => 'required|integer|min:1|max:300',
            ],
            [
                'wahaApiUrl.required' => 'API URL harus diisi',
                'wahaApiUrl.url' => 'Format API URL tidak valid',
                'wahaSessionName.required' => 'Nama session harus diisi',
                'wahaDelay.required' => 'Delay harus diisi',
                'wahaTries.required' => 'Retry harus diisi',
                'wahaBackoff.required' => 'Backoff harus diisi',
            ],
        );

        ConfigurationHelper::set('whatsapp.api_url', $this->wahaApiUrl);
        ConfigurationHelper::set('whatsapp.api_key', $this->wahaApiKey);
        ConfigurationHelper::set('whatsapp.session', $this->wahaSessionName);
        ConfigurationHelper::set('whatsapp.delay', $this->wahaDelay);
        ConfigurationHelper::set('whatsapp.tries', $this->wahaTries);
        ConfigurationHelper::set('whatsapp.backoff', $this->wahaBackoff);

        $this->toastSuccess('Konfigurasi WAHA berhasil disimpan');
    }

    // =============================================
    // GOWA Methods
    // =============================================

    private function loadGowaSettings(): void
    {
        $this->gowaApiUrl = ConfigurationHelper::get('gowa.api_url', 'http://localhost:3000');
        $this->gowaUsername = ConfigurationHelper::get('gowa.username', '');
        $this->gowaPassword = ConfigurationHelper::get('gowa.password', '');
        $this->gowaDeviceId = ConfigurationHelper::get('gowa.device_id', '');
        $this->gowaDelay = ConfigurationHelper::get('gowa.delay', '3');
        $this->gowaTries = ConfigurationHelper::get('gowa.tries', '3');
        $this->gowaBackoff = ConfigurationHelper::get('gowa.backoff', '10');

        $service = app(GowaService::class);
        $this->gowaWebhookUrl = $service->getWebhookUrl() ?? '';
    }

    public function checkConnection(): void
    {
        $this->gowaQrCode = null;
        $this->gowaQrError = null;
        $this->pairingCode = null;

        if (empty($this->gowaApiUrl)) {
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

    public function pollConnection(): void
    {
        if (empty($this->gowaApiUrl)) {
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
                    $this->gowaQrCode = null;
                    $this->gowaQrError = null;
                    $this->pairingCode = null;
                    $this->toastSuccess('WhatsApp berhasil terhubung!');
                }
            }
        } catch (\Throwable $e) {
            // Polling gagal, biarkan status lama
        }
    }

    public function loginQr(): void
    {
        $this->pairingCode = null;

        try {
            $service = app(GowaService::class);
            $result = $service->login();

            if ($result['success'] && isset($result['data'])) {
                $this->gowaQrCode = is_string($result['data']) ? $result['data'] : null;
                if (!$this->gowaQrCode) {
                    $this->gowaQrError = 'Format QR code tidak dikenali';
                }
            } else {
                $this->gowaQrCode = null;
                $this->gowaQrError = $result['message'] ?? 'Gagal mengambil QR code';
            }
        } catch (\Throwable $e) {
            $this->gowaQrCode = null;
            $this->gowaQrError = 'Error: ' . $e->getMessage();
        }
    }

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

        $this->gowaQrCode = null;
        $this->gowaQrError = null;

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

    public function saveGowaSettings(): void
    {
        $this->validate(
            [
                'gowaApiUrl' => 'required|url',
                'gowaDelay' => 'required|integer|min:0|max:60',
                'gowaTries' => 'required|integer|min:1|max:10',
                'gowaBackoff' => 'required|integer|min:1|max:300',
            ],
            [
                'gowaApiUrl.required' => 'API URL harus diisi',
                'gowaApiUrl.url' => 'Format API URL tidak valid',
                'gowaDelay.required' => 'Delay harus diisi',
                'gowaTries.required' => 'Retry harus diisi',
                'gowaBackoff.required' => 'Backoff harus diisi',
            ],
        );

        ConfigurationHelper::set('gowa.api_url', $this->gowaApiUrl);
        ConfigurationHelper::set('gowa.username', $this->gowaUsername);
        ConfigurationHelper::set('gowa.password', $this->gowaPassword);
        ConfigurationHelper::set('gowa.device_id', $this->gowaDeviceId);
        ConfigurationHelper::set('gowa.delay', $this->gowaDelay);
        ConfigurationHelper::set('gowa.tries', $this->gowaTries);
        ConfigurationHelper::set('gowa.backoff', $this->gowaBackoff);

        $this->toastSuccess('Konfigurasi GOWA berhasil disimpan');
    }
};
?>

@php
    $isWaha = $activeGateway === 'waha';

    // WAHA polling
    $isWaitingWahaQr = ($sessionStatus['status'] ?? '') === 'SCAN_QR_CODE';
    $shouldPollWaha =
        $isWaha &&
        $sessionStatus &&
        !($sessionStatus['connected'] ?? false) &&
        !in_array($sessionStatus['status'] ?? '', ['STOPPED', 'NOT_FOUND', 'FAILED', 'ERROR']);

    // GOWA polling
    $isGowaWaitingQr = $gowaQrCode !== null;
    $isGowaConnected = $connectionStatus['connected'] ?? false;
    $shouldPollGowa =
        !$isWaha &&
        $connectionStatus &&
        !$isGowaConnected &&
        !in_array($connectionStatus['status'] ?? '', ['FAILED', 'ERROR', 'UNAUTHORIZED']);
@endphp

<div @if ($isWaha) wire:init="checkSession"
        @if ($isWaitingWahaQr) wire:poll.15s="loadQrCode" @endif
@if ($shouldPollWaha) wire:poll.5s="pollSession" @endif @else wire:init="checkConnection"
    @if ($isGowaWaitingQr) wire:poll.15s="loginQr" @endif
    @if ($shouldPollGowa) wire:poll.5s="pollConnection" @endif @endif
    >
    {{-- Header --}}
    
    {{-- Gateway Switch Toggle --}}
    <div
        class="p-4 mb-6 bg-white border rounded-lg dark:bg-primary-dark-800 border-zinc-200 dark:border-primary-dark-700">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-sm font-semibold text-zinc-900 dark:text-primary-dark-100">Gateway Aktif</h2>
                <p class="text-xs text-zinc-500 dark:text-primary-dark-400">Pilih gateway WhatsApp yang akan digunakan
                </p>
            </div>
            <div class="flex items-center gap-1 p-1 rounded-lg bg-zinc-100 dark:bg-primary-dark-700">
                <x-atoms.button wire:click="switchGateway('waha')"
                    class="px-4 py-2 text-sm font-medium rounded-md transition-colors {{ $isWaha ? 'bg-white dark:bg-primary-dark-600 text-zinc-900 dark:text-primary-dark-100 shadow-sm' : 'text-zinc-500 dark:text-primary-dark-400 hover:text-zinc-700 dark:hover:text-primary-dark-200' }}">
                    WAHA
                </x-atoms.button>
                <x-atoms.button wire:click="switchGateway('gowa')"
                    class="px-4 py-2 text-sm font-medium rounded-md transition-colors {{ !$isWaha ? 'bg-white dark:bg-primary-dark-600 text-zinc-900 dark:text-primary-dark-100 shadow-sm' : 'text-zinc-500 dark:text-primary-dark-400 hover:text-zinc-700 dark:hover:text-primary-dark-200' }}">
                    GOWA
                </x-atoms.button>
            </div>
        </div>
    </div>

    {{-- ========== WAHA Section ========== --}}
    @if ($isWaha)
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

                        <div class="space-y-3">
                            <div
                                class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                                <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Session</span>
                                <span class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">
                                    {{ $sessionStatus['name'] ?? $wahaSessionName }}
                                </span>
                            </div>
                            <div
                                class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                                <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Status</span>
                                <flux:badge :color="$isConnected ? 'green' : ($isQr ? 'yellow' : 'red')" size="sm">
                                    {{ $sessionStatus['status'] }}
                                </flux:badge>
                            </div>
                        </div>

                        {{-- QR Code --}}
                        @if ($isQr)
                            <div class="pt-4 border-t border-zinc-200 dark:border-primary-dark-700">
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-300">Scan QR
                                        Code
                                        dengan WhatsApp</h3>
                                    <x-atoms.button variant="subtle" size="sm" icon="arrow-path"
                                        wire:click="loadQrCode" wire:loading.attr="disabled" wire:target="loadQrCode">
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
                                            <p class="text-sm text-zinc-400">{{ $qrError ?? 'QR Code tidak tersedia' }}
                                            </p>
                                            <x-atoms.button variant="subtle" size="sm" class="mt-2"
                                                wire:click="loadQrCode">Coba Lagi</x-atoms.button>
                                        </div>
                                    </div>
                                @endif

                                <div class="mt-3 p-3 rounded-lg bg-blue-50 dark:bg-blue-950/30">
                                    <p class="text-xs text-blue-700 dark:text-blue-300">
                                        <strong>Cara scan:</strong> Buka WhatsApp di HP &rarr; Menu &rarr; Perangkat
                                        Tertaut &rarr; Tautkan Perangkat &rarr; Arahkan kamera ke QR Code di atas.
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
                                <x-atoms.button variant="filled" size="sm" icon="arrow-path"
                                    wire:click="restartSession" wire:loading.attr="disabled"
                                    wire:target="restartSession">
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
                            @if (!in_array($sessionStatus['status'], ['STOPPED', 'NOT_FOUND', 'FAILED', 'ERROR']))
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

            {{-- Informasi Konfigurasi WAHA --}}
            <div
                class="p-6 bg-white border rounded-lg dark:bg-primary-dark-800 border-zinc-200 dark:border-primary-dark-700">
                <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Informasi Konfigurasi
                </h2>
                <div class="space-y-3">
                    <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                        <span class="text-sm text-zinc-500 dark:text-primary-dark-400">WAHA API URL</span>
                        <span
                            class="text-sm font-mono text-zinc-900 dark:text-primary-dark-100">{{ $wahaApiUrl ?: '-' }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                        <span class="text-sm text-zinc-500 dark:text-primary-dark-400">API Key</span>
                        <span
                            class="text-sm font-mono text-zinc-900 dark:text-primary-dark-100">{{ $wahaApiKey ? str_repeat('*', min(strlen($wahaApiKey), 20)) : '-' }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                        <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Session</span>
                        <span
                            class="text-sm font-mono text-zinc-900 dark:text-primary-dark-100">{{ $wahaSessionName ?: '-' }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                        <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Delay Antar Pesan</span>
                        <span class="text-sm font-mono text-zinc-900 dark:text-primary-dark-100">{{ $wahaDelay }}
                            detik</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                        <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Retry</span>
                        <span
                            class="text-sm font-mono text-zinc-900 dark:text-primary-dark-100">{{ $wahaTries }}x</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                        <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Backoff</span>
                        <span class="text-sm font-mono text-zinc-900 dark:text-primary-dark-100">{{ $wahaBackoff }}
                            detik</span>
                    </div>
                    <div class="py-2">
                        <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Webhook URL</span>
                        @if ($wahaWebhookUrl)
                            <div class="flex items-center gap-2 mt-1">
                                <code
                                    class="text-xs bg-zinc-100 dark:bg-primary-dark-700 px-2 py-1 rounded text-zinc-800 dark:text-primary-dark-200 break-all">{{ $wahaWebhookUrl }}</code>
                            </div>
                            <p class="mt-1 text-xs text-zinc-400">URL ini akan otomatis dikirim ke WAHA saat Start
                                Session</p>
                        @else
                            <p class="mt-1 text-sm text-zinc-400">Belum dikonfigurasi</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Form Pengaturan WAHA --}}
        <div
            class="p-6 bg-white border rounded-lg dark:bg-primary-dark-800 border-zinc-200 dark:border-primary-dark-700">
            <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Pengaturan WAHA Gateway
            </h2>

            <form wire:submit="saveWahaSettings" class="space-y-5">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <flux:field>
                        <flux:label>WAHA API URL</flux:label>
                        <flux:input wire:model="wahaApiUrl" placeholder="http://localhost:3000" />
                        @error('wahaApiUrl')
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                    </flux:field>
                    <flux:field>
                        <flux:label>API Key</flux:label>
                        <flux:input type="password" wire:model="wahaApiKey" placeholder="Kosongkan jika tidak ada" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Nama Session</flux:label>
                        <flux:input wire:model="wahaSessionName" placeholder="default" />
                        @error('wahaSessionName')
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                    </flux:field>
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                    <flux:field>
                        <flux:label>Delay Antar Pesan (detik)</flux:label>
                        <flux:input type="number" wire:model="wahaDelay" min="0" max="60" />
                        @error('wahaDelay')
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                        <p class="mt-1 text-xs text-zinc-400">Jeda antar pengiriman pesan</p>
                    </flux:field>
                    <flux:field>
                        <flux:label>Retry</flux:label>
                        <flux:input type="number" wire:model="wahaTries" min="1" max="10" />
                        @error('wahaTries')
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                        <p class="mt-1 text-xs text-zinc-400">Jumlah percobaan ulang jika gagal</p>
                    </flux:field>
                    <flux:field>
                        <flux:label>Backoff (detik)</flux:label>
                        <flux:input type="number" wire:model="wahaBackoff" min="1" max="300" />
                        @error('wahaBackoff')
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                        <p class="mt-1 text-xs text-zinc-400">Jeda antar percobaan ulang</p>
                    </flux:field>
                </div>

                <div class="flex justify-end pt-2">
                    <x-atoms.button type="submit" variant="primary" icon="check">Simpan Konfigurasi</x-atoms.button>
                </div>
            </form>
        </div>
    @endif

    {{-- ========== GOWA Section ========== --}}
    @if (!$isWaha)
        <div class="grid grid-cols-1 gap-6 mb-6 lg:grid-cols-2">
            {{-- Status Koneksi --}}
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
                        @php
                            $bgClass = $isGowaConnected
                                ? 'bg-emerald-50 dark:bg-emerald-950/30'
                                : ($isGowaWaitingQr
                                    ? 'bg-amber-50 dark:bg-amber-950/30'
                                    : 'bg-red-50 dark:bg-red-950/30');
                        @endphp
                        <div class="flex items-center gap-3 p-4 rounded-lg {{ $bgClass }}">
                            <div class="flex-shrink-0">
                                @if ($isGowaConnected)
                                    <div
                                        class="flex items-center justify-center w-10 h-10 rounded-full bg-emerald-100 dark:bg-emerald-900/50">
                                        <flux:icon name="check-circle"
                                            class="w-6 h-6 text-emerald-600 dark:text-emerald-400" />
                                    </div>
                                @elseif ($isGowaWaitingQr)
                                    <div
                                        class="flex items-center justify-center w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/50">
                                        <flux:icon name="qr-code"
                                            class="w-6 h-6 text-amber-600 dark:text-amber-400" />
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
                                    class="font-semibold {{ $isGowaConnected ? 'text-emerald-700 dark:text-emerald-300' : ($isGowaWaitingQr ? 'text-amber-700 dark:text-amber-300' : 'text-red-700 dark:text-red-300') }}">
                                    {{ $isGowaConnected ? 'Terhubung' : ($isGowaWaitingQr ? 'Menunggu Scan QR' : 'Tidak Terhubung') }}
                                </p>
                                <p
                                    class="text-sm {{ $isGowaConnected ? 'text-emerald-600 dark:text-emerald-400' : ($isGowaWaitingQr ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400') }}">
                                    {{ $connectionStatus['message'] }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div
                                class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                                <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Status</span>
                                <flux:badge :color="$isGowaConnected ? 'green' : ($isGowaWaitingQr ? 'yellow' : 'red')"
                                    size="sm">
                                    {{ $connectionStatus['status'] }}
                                </flux:badge>
                            </div>
                        </div>

                        {{-- QR Code --}}
                        @if ($gowaQrCode)
                            <div class="pt-4 border-t border-zinc-200 dark:border-primary-dark-700">
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-300">Scan QR
                                        Code
                                        dengan WhatsApp</h3>
                                    <x-atoms.button variant="subtle" size="sm" icon="arrow-path"
                                        wire:click="loginQr" wire:loading.attr="disabled" wire:target="loginQr">
                                        <span wire:loading.remove wire:target="loginQr">Refresh QR</span>
                                        <span wire:loading wire:target="loginQr">Memuat...</span>
                                    </x-atoms.button>
                                </div>
                                <div class="flex flex-col items-center gap-3">
                                    <img src="{{ $gowaQrCode }}" alt="GOWA QR Code"
                                        class="max-w-[280px] rounded border border-zinc-200 dark:border-primary-dark-700 bg-white p-2" />
                                    <p class="text-xs text-zinc-400">QR Code diperbarui otomatis tiap 15 detik</p>
                                </div>
                                <div class="mt-3 p-3 rounded-lg bg-blue-50 dark:bg-blue-950/30">
                                    <p class="text-xs text-blue-700 dark:text-blue-300">
                                        <strong>Cara scan:</strong> Buka WhatsApp di HP &rarr; Menu &rarr; Perangkat
                                        Tertaut &rarr; Tautkan Perangkat &rarr; Arahkan kamera ke QR Code di atas.
                                    </p>
                                </div>
                            </div>
                        @endif

                        {{-- Pairing Code --}}
                        @if ($pairingCode)
                            <div class="pt-4 border-t border-zinc-200 dark:border-primary-dark-700">
                                <h3 class="mb-3 text-sm font-semibold text-zinc-700 dark:text-primary-dark-300">Pairing
                                    Code
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
                                        Tertaut &rarr; Tautkan Perangkat &rarr; Pilih "Tautkan dengan Nomor Telepon"
                                        &rarr; Masukkan kode di atas.
                                    </p>
                                </div>
                            </div>
                        @endif

                        {{-- QR Error --}}
                        @if ($gowaQrError)
                            <div class="p-3 rounded-lg bg-red-50 dark:bg-red-950/30">
                                <p class="text-sm text-red-600 dark:text-red-400">{{ $gowaQrError }}</p>
                            </div>
                        @endif

                        {{-- Tombol Aksi --}}
                        <div class="flex flex-wrap gap-2 pt-2">
                            @if (!$isGowaConnected)
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
                            @if ($isGowaConnected)
                                <x-atoms.button variant="filled" size="sm" icon="arrow-path"
                                    wire:click="reconnectWhatsapp" wire:loading.attr="disabled"
                                    wire:target="reconnectWhatsapp">
                                    <span wire:loading.remove wire:target="reconnectWhatsapp">Reconnect</span>
                                    <span wire:loading wire:target="reconnectWhatsapp">Reconnect...</span>
                                </x-atoms.button>
                                <x-atoms.button variant="danger" size="sm" icon="arrow-right-start-on-rectangle"
                                    wire:click="logoutWhatsapp" wire:loading.attr="disabled"
                                    wire:target="logoutWhatsapp"
                                    wire:confirm="Yakin ingin logout? Anda perlu login ulang.">
                                    <span wire:loading.remove wire:target="logoutWhatsapp">Logout</span>
                                    <span wire:loading wire:target="logoutWhatsapp">Logout...</span>
                                </x-atoms.button>
                            @endif
                        </div>

                        {{-- Devices --}}
                        @if ($isGowaConnected && $devices)
                            <div class="pt-4 border-t border-zinc-200 dark:border-primary-dark-700">
                                <h3 class="mb-2 text-sm font-semibold text-zinc-700 dark:text-primary-dark-300">Device
                                    Terhubung</h3>
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

            {{-- Informasi Konfigurasi GOWA --}}
            <div
                class="p-6 bg-white border rounded-lg dark:bg-primary-dark-800 border-zinc-200 dark:border-primary-dark-700">
                <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Informasi Konfigurasi
                </h2>
                <div class="space-y-3">
                    <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                        <span class="text-sm text-zinc-500 dark:text-primary-dark-400">GOWA API URL</span>
                        <span
                            class="text-sm font-mono text-zinc-900 dark:text-primary-dark-100">{{ $gowaApiUrl ?: '-' }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                        <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Username</span>
                        <span
                            class="text-sm font-mono text-zinc-900 dark:text-primary-dark-100">{{ $gowaUsername ?: '-' }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                        <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Password</span>
                        <span
                            class="text-sm font-mono text-zinc-900 dark:text-primary-dark-100">{{ $gowaPassword ? str_repeat('*', min(strlen($gowaPassword), 20)) : '-' }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                        <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Device ID</span>
                        <span
                            class="text-sm font-mono text-zinc-900 dark:text-primary-dark-100">{{ $gowaDeviceId ?: '-' }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                        <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Delay Antar Pesan</span>
                        <span class="text-sm font-mono text-zinc-900 dark:text-primary-dark-100">{{ $gowaDelay }}
                            detik</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                        <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Retry</span>
                        <span
                            class="text-sm font-mono text-zinc-900 dark:text-primary-dark-100">{{ $gowaTries }}x</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                        <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Backoff</span>
                        <span class="text-sm font-mono text-zinc-900 dark:text-primary-dark-100">{{ $gowaBackoff }}
                            detik</span>
                    </div>
                    <div class="py-2">
                        <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Webhook URL</span>
                        @if ($gowaWebhookUrl)
                            <div class="flex items-center gap-2 mt-1">
                                <code
                                    class="text-xs bg-zinc-100 dark:bg-primary-dark-700 px-2 py-1 rounded text-zinc-800 dark:text-primary-dark-200 break-all">{{ $gowaWebhookUrl }}</code>
                            </div>
                            <p class="mt-1 text-xs text-zinc-400">Konfigurasikan URL ini di GOWA server sebagai webhook
                            </p>
                        @else
                            <p class="mt-1 text-sm text-zinc-400">Belum dikonfigurasi</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Form Pengaturan GOWA --}}
        <div
            class="p-6 bg-white border rounded-lg dark:bg-primary-dark-800 border-zinc-200 dark:border-primary-dark-700">
            <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Pengaturan GOWA Gateway
            </h2>

            <form wire:submit="saveGowaSettings" class="space-y-5">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <flux:field>
                        <flux:label>GOWA API URL</flux:label>
                        <flux:input wire:model="gowaApiUrl" placeholder="http://localhost:3000" />
                        @error('gowaApiUrl')
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                    </flux:field>
                    <flux:field>
                        <flux:label>Username (Basic Auth)</flux:label>
                        <flux:input wire:model="gowaUsername" placeholder="Username" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Password (Basic Auth)</flux:label>
                        <flux:input type="password" wire:model="gowaPassword" placeholder="Password" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Device ID</flux:label>
                        <flux:input wire:model="gowaDeviceId" placeholder="Opsional" />
                        <p class="mt-1 text-xs text-zinc-400">Header X-Device-Id (kosongkan jika tidak perlu)</p>
                    </flux:field>
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                    <flux:field>
                        <flux:label>Delay Antar Pesan (detik)</flux:label>
                        <flux:input type="number" wire:model="gowaDelay" min="0" max="60" />
                        @error('gowaDelay')
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                        <p class="mt-1 text-xs text-zinc-400">Jeda antar pengiriman pesan</p>
                    </flux:field>
                    <flux:field>
                        <flux:label>Retry</flux:label>
                        <flux:input type="number" wire:model="gowaTries" min="1" max="10" />
                        @error('gowaTries')
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                        <p class="mt-1 text-xs text-zinc-400">Jumlah percobaan ulang jika gagal</p>
                    </flux:field>
                    <flux:field>
                        <flux:label>Backoff (detik)</flux:label>
                        <flux:input type="number" wire:model="gowaBackoff" min="1" max="300" />
                        @error('gowaBackoff')
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                        <p class="mt-1 text-xs text-zinc-400">Jeda antar percobaan ulang</p>
                    </flux:field>
                </div>

                <div class="flex justify-end pt-2">
                    <x-atoms.button type="submit" variant="primary" icon="check">Simpan Konfigurasi</x-atoms.button>
                </div>
            </form>
        </div>
    @endif
</div>
