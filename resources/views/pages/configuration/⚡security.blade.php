<?php

use App\Constants\AppSecurityConfig;
use App\Models\ActivityLog;
use App\Models\IpBlacklist;
use App\Models\LoginAttempt;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithPagination;

new #[Layout('layouts::app')] #[Title('Keamanan Umum')] class extends Component {
    use WithPagination;

    // ── Konfigurasi Security Headers ──────────────────────────────────────
    public bool $headerEnabled = true;
    public string $frameOptions = 'SAMEORIGIN';
    public bool $hstsEnabled = false;
    public bool $cspEnabled = false;
    public string $cspValue = "default-src 'self'";
    public bool $forceHttps = false;

    // ── Konfigurasi Login Throttle ────────────────────────────────────────
    public bool $loginLimitEnabled = true;
    public int $loginMaxAttempts = 5;
    public int $loginLockoutMinutes = 15;

    // ── Filter Log ────────────────────────────────────────────────────────
    public string $filterIp = '';
    public string $filterSuccess = '';
    public string $filterUser = '';

    // ── Blacklist Management ───────────────────────────────────────────────
    public bool $showBlacklistModal = false;
    public string $editBlacklistId = ''; // kosong = mode tambah
    public string $newBlacklistIp = '';
    public string $newBlacklistReason = '';
    public int $newBlacklistDurationHours = 24; // 0 = permanen
    public string $filterBlacklistIp = '';

    // ── Konfigurasi Blacklist & CAPTCHA ────────────────────────────────────
    public bool $autoBlacklistEnabled = false;
    public int $autoBlacklistThreshold = 20;
    public int $autoBlacklistDurationHours = 24;
    public bool $captchaEnabled = true;
    public int $captchaTriggerAfter = 2;

    public function mount(): void
    {
        $this->headerEnabled = AppSecurityConfig::bool('app.security.headers.enabled');
        $this->frameOptions = AppSecurityConfig::get('app.security.headers.frame_options');
        $this->hstsEnabled = AppSecurityConfig::bool('app.security.headers.hsts');
        $this->cspEnabled = AppSecurityConfig::bool('app.security.headers.csp_enabled');
        $this->cspValue = AppSecurityConfig::get('app.security.headers.csp_value');
        $this->forceHttps = AppSecurityConfig::bool('app.security.force_https');

        $this->loginLimitEnabled = AppSecurityConfig::bool('app.security.login.enabled');
        $this->loginMaxAttempts = AppSecurityConfig::int('app.security.login.max_attempts');
        $this->loginLockoutMinutes = AppSecurityConfig::int('app.security.login.lockout_minutes');

        $this->autoBlacklistEnabled = AppSecurityConfig::bool('app.security.blacklist.auto_enabled');
        $this->autoBlacklistThreshold = AppSecurityConfig::int('app.security.blacklist.auto_threshold');
        $this->autoBlacklistDurationHours = AppSecurityConfig::int('app.security.blacklist.auto_duration_hours');
        $this->captchaEnabled = AppSecurityConfig::bool('app.security.captcha.enabled');
        $this->captchaTriggerAfter = AppSecurityConfig::int('app.security.captcha.trigger_after');
    }

    public function saveLogin(): void
    {
        $this->validate([
            'loginMaxAttempts' => 'required|integer|min:1|max:100',
            'loginLockoutMinutes' => 'required|integer|min:1|max:1440',
        ]);

        AppSecurityConfig::set('app.security.login.enabled', $this->loginLimitEnabled ? '1' : '0');
        AppSecurityConfig::set('app.security.login.max_attempts', $this->loginMaxAttempts);
        AppSecurityConfig::set('app.security.login.lockout_minutes', $this->loginLockoutMinutes);

        $this->dispatch('toast', type: 'success', message: 'Konfigurasi Login Security disimpan.');
    }

    public function saveHeaders(): void
    {
        $this->validate([
            'frameOptions' => 'required|in:SAMEORIGIN,DENY,ALLOWALL',
            'cspValue' => 'nullable|string|max:2000',
        ]);

        AppSecurityConfig::set('app.security.headers.enabled', $this->headerEnabled ? '1' : '0');
        AppSecurityConfig::set('app.security.headers.frame_options', $this->frameOptions);
        AppSecurityConfig::set('app.security.headers.hsts', $this->hstsEnabled ? '1' : '0');
        AppSecurityConfig::set('app.security.headers.csp_enabled', $this->cspEnabled ? '1' : '0');
        AppSecurityConfig::set('app.security.headers.csp_value', $this->cspValue ?? '');
        AppSecurityConfig::set('app.security.force_https', $this->forceHttps ? '1' : '0');

        $this->dispatch('toast', type: 'success', message: 'Konfigurasi Security Headers disimpan.');
    }

    public function saveBlacklistConfig(): void
    {
        $this->validate([
            'autoBlacklistThreshold' => 'required|integer|min:5|max:1000',
            'autoBlacklistDurationHours' => 'required|integer|min:0|max:8760',
            'captchaTriggerAfter' => 'required|integer|min:1|max:100',
        ]);

        AppSecurityConfig::set('app.security.blacklist.auto_enabled', $this->autoBlacklistEnabled ? '1' : '0');
        AppSecurityConfig::set('app.security.blacklist.auto_threshold', $this->autoBlacklistThreshold);
        AppSecurityConfig::set('app.security.blacklist.auto_duration_hours', $this->autoBlacklistDurationHours);
        AppSecurityConfig::set('app.security.captcha.enabled', $this->captchaEnabled ? '1' : '0');
        AppSecurityConfig::set('app.security.captcha.trigger_after', $this->captchaTriggerAfter);

        $this->dispatch('toast', type: 'success', message: 'Konfigurasi Blacklist & CAPTCHA disimpan.');
    }

    /** Buka modal tambah/edit — kosongkan id untuk mode tambah */
    public function openBlacklistModal(?string $id = null): void
    {
        $this->resetValidation(['newBlacklistIp', 'newBlacklistReason', 'newBlacklistDurationHours']);

        if ($id) {
            $entry = IpBlacklist::findOrFail($id);
            $this->editBlacklistId = $id;
            $this->newBlacklistIp = $entry->ip_address;
            $this->newBlacklistReason = $entry->reason ?? '';
            $this->newBlacklistDurationHours = $entry->expires_at
                ? (int) ceil(now()->diffInHours($entry->expires_at, false) * -1) // sisa jam
                : 0;
            // Hitung sisa jam dari sekarang
            $this->newBlacklistDurationHours = $entry->expires_at ? max(1, (int) ceil($entry->expires_at->diffInMinutes(now()) / -60)) : 0;
        } else {
            $this->editBlacklistId = '';
            $this->newBlacklistIp = '';
            $this->newBlacklistReason = '';
            $this->newBlacklistDurationHours = 24;
        }

        $this->showBlacklistModal = true;
    }

    public function saveBlacklist(): void
    {
        $rules = [
            'newBlacklistReason' => 'nullable|string|max:255',
            'newBlacklistDurationHours' => 'required|integer|min:0|max:8760',
        ];
        if (!$this->editBlacklistId) {
            $rules['newBlacklistIp'] = 'required|ip';
        }
        $this->validate($rules);

        $ip = $this->editBlacklistId ? IpBlacklist::findOrFail($this->editBlacklistId)->ip_address : $this->newBlacklistIp;

        $hours = $this->newBlacklistDurationHours;
        $expiresAt = $hours > 0 ? now()->addHours($hours) : null;

        IpBlacklist::block($ip, $this->newBlacklistReason ?: 'Diblokir manual oleh admin', $expiresAt, auth()->user()->username ?? 'admin');

        $action = $this->editBlacklistId ? 'diperbarui' : 'ditambahkan ke blacklist';
        ActivityLog::log(type: 'ip_blacklisted', subject: "IP {$ip} {$action}", description: null, properties: ['ip' => $ip, 'reason' => $this->newBlacklistReason, 'expires_at' => $expiresAt]);

        $this->editBlacklistId = '';
        $this->newBlacklistIp = '';
        $this->newBlacklistReason = '';
        $this->newBlacklistDurationHours = 24;

        $this->showBlacklistModal = false;
        $this->dispatch('toast', type: 'success', message: "IP {$ip} berhasil {$action}.");
    }

    public function removeFromBlacklist(string $id): void
    {
        $entry = IpBlacklist::findOrFail($id);
        $ip = $entry->ip_address;
        $entry->delete();

        ActivityLog::log(type: 'ip_unblacklisted', subject: "IP {$ip} dihapus dari blacklist", description: null, properties: ['ip' => $ip]);

        $this->dispatch('toast', type: 'success', message: "IP {$ip} berhasil dihapus dari blacklist.");
    }

    /**
     * Reset lockout untuk satu kombinasi user + IP
     */
    public function resetUserLockout(string $username, string $ip): void
    {
        $key = 'login.' . Str::lower(trim($username)) . '|' . $ip;
        RateLimiter::clear($key);

        ActivityLog::log(type: 'security_lockout_reset', subject: "Reset lockout untuk user: {$username} dari IP: {$ip}", description: null, properties: ['username' => $username, 'ip_address' => $ip]);

        $this->dispatch('toast', type: 'success', message: "Lockout untuk {$username} berhasil direset.");
    }

    /**
     * Reset semua lockout yang masih aktif di RateLimiter
     */
    public function resetAllLockouts(): void
    {
        $max = $this->loginMaxAttempts;
        $candidates = LoginAttempt::failed()->recent($this->loginLockoutMinutes)->selectRaw('username, ip_address')->groupBy('username', 'ip_address')->get();

        $count = 0;
        foreach ($candidates as $row) {
            $key = 'login.' . Str::lower(trim($row->username)) . '|' . $row->ip_address;
            if (RateLimiter::tooManyAttempts($key, $max)) {
                RateLimiter::clear($key);
                $count++;
            }
        }

        ActivityLog::log(type: 'security_lockout_reset_all', subject: 'Reset semua lockout login aktif', description: null, properties: ['total_keys_cleared' => $count]);

        $this->dispatch('toast', type: 'success', message: "Semua lockout aktif berhasil direset ({$count} user).");
    }

    public function updatingFilterIp(): void
    {
        $this->resetPage();
    }
    public function updatingFilterSuccess(): void
    {
        $this->resetPage();
    }
    public function updatingFilterUser(): void
    {
        $this->resetPage();
    }
    public function updatingFilterBlacklistIp(): void
    {
        $this->resetPage('bl-page');
    }

    public function with(): array
    {
        // ── Stats ────────────────────────────────────────────────────────────
        $totalAttempts = LoginAttempt::count();
        $failedToday = LoginAttempt::failed()->whereDate('created_at', today())->count();
        $successToday = LoginAttempt::whereSuccess(true)->whereDate('created_at', today())->count();
        $uniqueIpsToday = LoginAttempt::failed()->whereDate('created_at', today())->distinct('ip_address')->count('ip_address');

        // ── Log percobaan login (dengan filter) ──────────────────────────────
        $recentAttempts = LoginAttempt::query()
            ->when($this->filterIp, fn($q) => $q->where('ip_address', 'like', "%{$this->filterIp}%"))
            ->when($this->filterUser, fn($q) => $q->where('username', 'like', "%{$this->filterUser}%"))
            ->when($this->filterSuccess === 'failed', fn($q) => $q->whereSuccess(false))
            ->when($this->filterSuccess === 'success', fn($q) => $q->whereSuccess(true))
            ->orderByDesc('created_at')
            ->paginate(20);

        // ── Top 10 IP dengan kegagalan terbanyak ─────────────────────────────
        $topFailedIps = LoginAttempt::failed()->selectRaw('ip_address, COUNT(*) as total')->groupBy('ip_address')->orderByDesc('total')->limit(10)->get();

        // ── User yang sedang terkunci (cek via RateLimiter) ──────────────────
        $lockedUsers = collect();
        if ($this->loginLimitEnabled) {
            $max = $this->loginMaxAttempts;
            $candidates = LoginAttempt::failed()->recent($this->loginLockoutMinutes)->selectRaw('username, ip_address, COUNT(*) as failed_count')->groupBy('username', 'ip_address')->having('failed_count', '>=', $max)->get();

            foreach ($candidates as $candidate) {
                $key = 'login.' . Str::lower(trim($candidate->username)) . '|' . $candidate->ip_address;
                if (RateLimiter::tooManyAttempts($key, $max)) {
                    $seconds = RateLimiter::availableIn($key);
                    $h = intdiv($seconds, 3600);
                    $m = intdiv($seconds % 3600, 60);
                    $s = $seconds % 60;
                    $candidate->available_in_seconds = $seconds;
                    $candidate->available_in_label = $h > 0 ? "{$h}j {$m}m" : ($m > 0 ? "{$m} menit" : "{$s} detik");
                    $lockedUsers->push($candidate);
                }
            }
        }

        // ── Daftar IP di blacklist ────────────────────────────────────────────
        $blacklistedIps = IpBlacklist::query()->when($this->filterBlacklistIp, fn($q) => $q->where('ip_address', 'like', "%{$this->filterBlacklistIp}%"))->orderByDesc('created_at')->paginate(15, pageName: 'bl-page');

        return compact('totalAttempts', 'failedToday', 'successToday', 'uniqueIpsToday', 'recentAttempts', 'topFailedIps', 'lockedUsers', 'blacklistedIps');
    }
};
?>

<div x-data="{ configTab: 'login' }">
    <x-ui.page-header title="Keamanan Umum"
        subtitle="Kelola keamanan login, HTTP security headers, dan pantau percobaan masuk ke sistem" />

    {{-- Stats Cards ─────────────────────────────────────────────────────── --}}
    <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-organisms.stat-card title="Total Percobaan" :value="number_format($totalAttempts)" color="zinc" icon="arrow-right-end-on-rectangle"
            subtitle="sepanjang waktu" />

        <x-organisms.stat-card title="Gagal Hari Ini" :value="number_format($failedToday)" color="red" icon="x-circle"
            :subtitle="today()->format('d M Y')" />

        <x-organisms.stat-card title="Berhasil Hari Ini" :value="number_format($successToday)" color="emerald" icon="check-circle"
            :subtitle="today()->format('d M Y')" />

        <x-organisms.stat-card title="IP Unik Hari Ini" :value="number_format($uniqueIpsToday)" color="blue" icon="globe-alt"
            subtitle="IP yang gagal login" />
    </div>

    {{-- Layout dua kolom: Konfigurasi + Konten ──────────────────────────── --}}
    <div class="mb-4 grid grid-cols-1 gap-4 lg:grid-cols-4">

        {{-- Panel Konfigurasi (kiri) ─────────────────────────────────────── --}}
        <div class="lg:col-span-1 space-y-4">

            {{-- Status Perlindungan ──────────────────────────────────────── --}}
            <x-organisms.data-panel title="Status Perlindungan" icon="shield-check">
                <div class="p-5 grid grid-cols-1 gap-3">
                    @php
                        $protectionMap = [
                            'emerald' =>
                                'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-800/40 text-emerald-700 dark:text-emerald-400',
                            'zinc' =>
                                'bg-zinc-50 dark:bg-primary-dark-800/60 border-zinc-200 dark:border-primary-dark-700 text-zinc-500 dark:text-primary-dark-400',
                        ];
                        $loginActive = $loginLimitEnabled;
                        $headersActive = $headerEnabled;
                    @endphp

                    {{-- Login Throttle --}}
                    <div class="flex items-center gap-3 rounded-xl border p-3 {{ $protectionMap[$loginActive ? 'emerald' : 'zinc'] }}">
                        <flux:icon name="{{ $loginActive ? 'shield-check' : 'shield-exclamation' }}" class="size-5 shrink-0" />
                        <div class="min-w-0">
                            <p class="text-xs font-semibold">Login Throttle</p>
                            <p class="text-xs opacity-70">
                                {{ $loginActive ? "Aktif — maks. {$loginMaxAttempts}x / {$loginLockoutMinutes} mnt" : 'Nonaktif' }}
                            </p>
                        </div>
                    </div>

                    {{-- Security Headers --}}
                    <div class="flex items-center gap-3 rounded-xl border p-3 {{ $protectionMap[$headersActive ? 'emerald' : 'zinc'] }}">
                        <flux:icon name="{{ $headersActive ? 'shield-check' : 'shield-exclamation' }}" class="size-5 shrink-0" />
                        <div class="min-w-0">
                            <p class="text-xs font-semibold">Security Headers</p>
                            <p class="text-xs opacity-70">{{ $headersActive ? 'Aktif — ' . $frameOptions : 'Nonaktif' }}</p>
                        </div>
                    </div>

                    {{-- CAPTCHA --}}
                    @php $captchaActive = $captchaEnabled; @endphp
                    <div class="flex items-center gap-3 rounded-xl border p-3 {{ $protectionMap[$captchaActive ? 'emerald' : 'zinc'] }}">
                        <flux:icon name="{{ $captchaActive ? 'shield-check' : 'shield-exclamation' }}" class="size-5 shrink-0" />
                        <div class="min-w-0">
                            <p class="text-xs font-semibold">CAPTCHA</p>
                            <p class="text-xs opacity-70">{{ $captchaActive ? "Aktif — setelah {$captchaTriggerAfter}x gagal" : 'Nonaktif' }}</p>
                        </div>
                    </div>

                    {{-- IP Blacklist --}}
                    @php $blacklistCount = IpBlacklist::active()->count(); @endphp
                    <div class="flex items-center gap-3 rounded-xl border p-3 {{ $protectionMap[$blacklistCount > 0 ? 'emerald' : 'zinc'] }}">
                        <flux:icon name="no-symbol" class="size-5 shrink-0" />
                        <div class="min-w-0">
                            <p class="text-xs font-semibold">IP Blacklist</p>
                            <p class="text-xs opacity-70">{{ $blacklistCount > 0 ? "{$blacklistCount} IP diblokir" : 'Kosong' }}</p>
                        </div>
                    </div>
                </div>
            </x-organisms.data-panel>

            {{-- Panel Konfigurasi (tabbed) ─────────────────────────────── --}}
            <x-organisms.data-panel title="Konfigurasi" icon="cog-6-tooth">

                {{-- Tab Nav ──────────────────────────────────────────────── --}}
                <div
                    class="flex gap-1 px-5 border-b border-zinc-100 dark:border-primary-dark-700/60 mt-3 overflow-auto">
                    @foreach ([['key' => 'login', 'label' => 'Login Security', 'icon' => 'lock-closed'], ['key' => 'headers', 'label' => 'HTTP Headers', 'icon' => 'shield-check'], ['key' => 'blacklist', 'label' => 'Blacklist & Bot', 'icon' => 'no-symbol']] as $tab)
                        <button @click="configTab = '{{ $tab['key'] }}'"
                            :class="configTab === '{{ $tab['key'] }}'
                                ?
                                'border-b-2 border-primary-500 text-primary-600 dark:text-primary-400' :
                                'text-zinc-500 dark:text-primary-dark-400 hover:text-zinc-700 dark:hover:text-primary-dark-200'"
                            class="flex items-center gap-1.5 px-3 pb-3 text-xs font-medium transition-colors whitespace-nowrap">
                            <flux:icon :name="$tab['icon']" class="w-3.5 h-3.5" />
                            {{ $tab['label'] }}
                        </button>
                    @endforeach
                </div>

                {{-- Tab: Login Security ────────────────────────────────── --}}
                <div x-show="configTab === 'login'" class="p-5 space-y-4">
                    <div class="flex items-center justify-between">
                        <p class="text-xs text-zinc-500 dark:text-primary-dark-400">Batasi percobaan login untuk
                            mencegah
                            brute-force.</p>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <span class="text-xs font-medium text-zinc-600 dark:text-primary-dark-300">Aktifkan</span>
                            <input type="checkbox" wire:model="loginLimitEnabled" class="rounded">
                        </label>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-zinc-600 dark:text-primary-dark-300 mb-1">
                            Maks. Percobaan Gagal
                        </label>
                        <input type="number" wire:model="loginMaxAttempts" min="1" max="100"
                            class="w-full rounded-lg border border-zinc-300 dark:border-primary-dark-600 bg-white dark:bg-primary-dark-700 px-3 py-1.5 text-sm text-zinc-700 dark:text-primary-dark-200 focus:outline-none focus:ring-2 focus:ring-primary-400" />
                        @error('loginMaxAttempts')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-zinc-600 dark:text-primary-dark-300 mb-1">
                            Durasi Lockout (menit)
                        </label>
                        <input type="number" wire:model="loginLockoutMinutes" min="1" max="1440"
                            class="w-full rounded-lg border border-zinc-300 dark:border-primary-dark-600 bg-white dark:bg-primary-dark-700 px-3 py-1.5 text-sm text-zinc-700 dark:text-primary-dark-200 focus:outline-none focus:ring-2 focus:ring-primary-400" />
                        @error('loginLockoutMinutes')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <x-atoms.button wire:click="saveLogin" variant="primary" size="sm" icon="check"
                        class="w-full">
                        Simpan Login Security
                    </x-atoms.button>
                </div>

                {{-- Tab: Security Headers ─────────────────────────────── --}}
                <div x-show="configTab === 'headers'" class="p-5 space-y-4">
                    <div class="flex items-center justify-between">
                        <p class="text-xs text-zinc-500 dark:text-primary-dark-400">Tambahkan HTTP headers keamanan pada
                            setiap
                            response.</p>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <span class="text-xs font-medium text-zinc-600 dark:text-primary-dark-300">Aktifkan</span>
                            <input type="checkbox" wire:model="headerEnabled" class="rounded">
                        </label>
                    </div>

                    <div>
                        <label
                            class="block text-xs font-semibold text-zinc-600 dark:text-primary-dark-300 mb-1">X-Frame-Options</label>
                        <select wire:model="frameOptions"
                            class="w-full rounded-lg border border-zinc-300 dark:border-primary-dark-600 bg-white dark:bg-primary-dark-700 px-3 py-1.5 text-sm text-zinc-700 dark:text-primary-dark-200 focus:outline-none focus:ring-2 focus:ring-primary-400">
                            <option value="SAMEORIGIN">SAMEORIGIN</option>
                            <option value="DENY">DENY</option>
                            <option value="ALLOWALL">ALLOWALL</option>
                        </select>
                        @error('frameOptions')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div
                        class="flex items-center justify-between rounded-lg border border-zinc-200 dark:border-primary-dark-600 px-3 py-2">
                        <div>
                            <p class="text-xs font-semibold text-zinc-700 dark:text-primary-dark-300">HSTS</p>
                            <p class="text-xs text-zinc-400">Strict-Transport-Security (max-age 1 tahun)</p>
                        </div>
                        <input type="checkbox" wire:model="hstsEnabled" class="rounded">
                    </div>

                    <div
                        class="flex items-center justify-between rounded-lg border border-zinc-200 dark:border-primary-dark-600 px-3 py-2">
                        <div>
                            <p class="text-xs font-semibold text-zinc-700 dark:text-primary-dark-300">CSP</p>
                            <p class="text-xs text-zinc-400">Content-Security-Policy</p>
                        </div>
                        <input type="checkbox" wire:model="cspEnabled" class="rounded">
                    </div>

                    <div x-show="$wire.cspEnabled">
                        <label class="block text-xs font-semibold text-zinc-600 dark:text-primary-dark-300 mb-1">Nilai
                            CSP</label>
                        <textarea wire:model="cspValue" rows="3"
                            class="w-full rounded-lg border border-zinc-300 dark:border-primary-dark-600 bg-white dark:bg-primary-dark-700 px-3 py-1.5 text-xs text-zinc-700 dark:text-primary-dark-200 focus:outline-none focus:ring-2 focus:ring-primary-400 font-mono"></textarea>
                        @error('cspValue')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div
                        class="flex items-center justify-between rounded-lg border border-zinc-200 dark:border-primary-dark-600 px-3 py-2">
                        <div>
                            <p class="text-xs font-semibold text-zinc-700 dark:text-primary-dark-300">Force HTTPS</p>
                            <p class="text-xs text-zinc-400">Redirect HTTP → HTTPS (non-lokal)</p>
                        </div>
                        <input type="checkbox" wire:model="forceHttps" class="rounded">
                    </div>

                    <x-atoms.button wire:click="saveHeaders" variant="primary" size="sm" icon="check"
                        class="w-full">
                        Simpan HTTP Headers
                    </x-atoms.button>
                </div>

                {{-- Tab: Blacklist & Bot ─────────────────────────────── --}}
                <div x-show="configTab === 'blacklist'" class="p-5 space-y-4">
                    <p class="text-xs text-zinc-500 dark:text-primary-dark-400">Konfigurasi auto-blacklist dan CAPTCHA
                        anti-robot.</p>

                    <div
                        class="flex items-center justify-between rounded-lg border border-zinc-200 dark:border-primary-dark-600 px-3 py-2">
                        <div>
                            <p class="text-xs font-semibold text-zinc-700 dark:text-primary-dark-300">CAPTCHA
                                Anti-Robot</p>
                            <p class="text-xs text-zinc-400">Tampilkan soal matematika setelah N kegagalan</p>
                        </div>
                        <input type="checkbox" wire:model="captchaEnabled" class="rounded">
                    </div>

                    <div>
                        <label
                            class="block text-xs font-semibold text-zinc-600 dark:text-primary-dark-300 mb-1">Tampilkan
                            setelah N kegagalan</label>
                        <input type="number" wire:model="captchaTriggerAfter" min="1" max="100"
                            class="w-full rounded-lg border border-zinc-300 dark:border-primary-dark-600 bg-white dark:bg-primary-dark-700 px-3 py-1.5 text-sm text-zinc-700 dark:text-primary-dark-200 focus:outline-none focus:ring-2 focus:ring-primary-400" />
                        @error('captchaTriggerAfter')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="border-t border-zinc-100 dark:border-primary-dark-700 pt-3">
                        <div
                            class="flex items-center justify-between rounded-lg border border-zinc-200 dark:border-primary-dark-600 px-3 py-2">
                            <div>
                                <p class="text-xs font-semibold text-zinc-700 dark:text-primary-dark-300">
                                    Auto-Blacklist IP</p>
                                <p class="text-xs text-zinc-400">Blokir otomatis IP setelah N kegagalan dalam 60 mnt
                                </p>
                            </div>
                            <input type="checkbox" wire:model="autoBlacklistEnabled" class="rounded">
                        </div>
                    </div>

                    <div>
                        <label
                            class="block text-xs font-semibold text-zinc-600 dark:text-primary-dark-300 mb-1">Threshold
                            (kegagalan / 60 mnt)</label>
                        <input type="number" wire:model="autoBlacklistThreshold" min="5" max="1000"
                            class="w-full rounded-lg border border-zinc-300 dark:border-primary-dark-600 bg-white dark:bg-primary-dark-700 px-3 py-1.5 text-sm text-zinc-700 dark:text-primary-dark-200 focus:outline-none focus:ring-2 focus:ring-primary-400" />
                        @error('autoBlacklistThreshold')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-zinc-600 dark:text-primary-dark-300 mb-1">Durasi
                            Blokir
                            (jam, 0 = permanen)</label>
                        <input type="number" wire:model="autoBlacklistDurationHours" min="0" max="8760"
                            class="w-full rounded-lg border border-zinc-300 dark:border-primary-dark-600 bg-white dark:bg-primary-dark-700 px-3 py-1.5 text-sm text-zinc-700 dark:text-primary-dark-200 focus:outline-none focus:ring-2 focus:ring-primary-400" />
                        @error('autoBlacklistDurationHours')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <x-atoms.button wire:click="saveBlacklistConfig" variant="primary" size="sm" icon="check"
                        class="w-full">
                        Simpan Blacklist & Bot
                    </x-atoms.button>
                </div>
            </x-organisms.data-panel>
        </div>

        {{-- Panel Kanan ──────────────────────────────────────────────────── --}}
        <div class="lg:col-span-3 space-y-4">

            {{-- User Terkunci ────────────────────────────────────────────── --}}
            <x-organisms.data-panel icon="lock-closed">
                <x-slot:title>
                    User Terkunci Saat Ini
                    @if ($lockedUsers->isNotEmpty())
                        <flux:badge color="red" size="sm" class="ml-1">{{ $lockedUsers->count() }}</flux:badge>
                    @endif
                </x-slot:title>

                @if ($lockedUsers->isNotEmpty())
                    <x-slot:action>
                        <x-atoms.button wire:click="resetAllLockouts"
                            wire:confirm="Reset semua lockout aktif? Semua user yang terkunci dapat login kembali."
                            size="xs" variant="ghost" icon="arrow-path">
                            Reset Semua
                        </x-atoms.button>
                    </x-slot:action>
                @endif

                @if ($lockedUsers->isEmpty())
                    <div class="flex flex-col items-center gap-2 py-8 text-center">
                        <flux:icon name="shield-check" class="size-8 text-emerald-400 dark:text-emerald-500" />
                        <p class="text-xs text-zinc-400">Tidak ada user yang sedang terkunci.</p>
                    </div>
                @else
                    <x-organisms.table>
                        <x-slot:headings>
                            <x-atoms.table-heading>Username</x-atoms.table-heading>
                            <x-atoms.table-heading>IP Address</x-atoms.table-heading>
                            <x-atoms.table-heading>Percobaan Gagal</x-atoms.table-heading>
                            <x-atoms.table-heading>Terbuka Dalam</x-atoms.table-heading>
                            <x-atoms.table-heading align="right">Aksi</x-atoms.table-heading>
                        </x-slot:headings>

                        @foreach ($lockedUsers as $locked)
                            <x-molecules.table-row>
                                <x-atoms.table-cell class="font-mono text-xs font-semibold text-zinc-800 dark:text-primary-dark-100">
                                    {{ $locked->username }}
                                </x-atoms.table-cell>
                                <x-atoms.table-cell class="font-mono text-xs text-zinc-500 dark:text-primary-dark-400">
                                    {{ $locked->ip_address }}
                                </x-atoms.table-cell>
                                <x-atoms.table-cell>
                                    <flux:badge color="red" size="sm">{{ $locked->failed_count }}x gagal</flux:badge>
                                </x-atoms.table-cell>
                                <x-atoms.table-cell>
                                    <span class="inline-flex items-center gap-1 text-xs font-medium text-amber-600 dark:text-amber-400">
                                        <flux:icon name="clock" class="size-3.5" />
                                        {{ $locked->available_in_label }}
                                    </span>
                                </x-atoms.table-cell>
                                <x-atoms.table-cell align="right">
                                    <x-atoms.button
                                        wire:click="resetUserLockout('{{ $locked->username }}', '{{ $locked->ip_address }}')"
                                        wire:confirm="Izinkan {{ $locked->username }} login kembali dari IP {{ $locked->ip_address }}?"
                                        size="xs" variant="ghost" icon="lock-open">
                                        Buka Kunci
                                    </x-atoms.button>
                                </x-atoms.table-cell>
                            </x-molecules.table-row>
                        @endforeach
                    </x-organisms.table>
                @endif
            </x-organisms.data-panel>

            {{-- Daftar IP Diblokir ─────────────────────────────────────── --}}
            <x-organisms.data-panel icon="no-symbol">
                <x-slot:title>
                    IP Diblokir (Blacklist)
                    @if ($blacklistedIps->total() > 0)
                        <flux:badge color="red" size="sm" class="ml-1">{{ $blacklistedIps->total() }}</flux:badge>
                    @endif
                </x-slot:title>

                <x-slot:action>
                    <x-atoms.button wire:click="openBlacklistModal()" size="xs" variant="ghost" icon="plus">
                        Tambah IP
                    </x-atoms.button>
                </x-slot:action>

                <x-slot:filter>
                    <flux:input wire:model.live.debounce.400ms="filterBlacklistIp"
                        placeholder="Filter IP..."
                        class="font-mono w-48" size="sm" />
                </x-slot:filter>

                {{-- Tabel --}}
                <x-organisms.table class="w-full text-sm">
                    <x-slot:headings>
                        <x-atoms.table-heading>IP Address</x-atoms.table-heading>
                        <x-atoms.table-heading>Alasan</x-atoms.table-heading>
                        <x-atoms.table-heading>Diblokir Oleh</x-atoms.table-heading>
                        <x-atoms.table-heading>Berakhir</x-atoms.table-heading>
                        <x-atoms.table-heading align="right">Aksi</x-atoms.table-heading>
                    </x-slot:headings>

                    @forelse ($blacklistedIps as $entry)
                        <x-molecules.table-row>
                            <x-atoms.table-cell
                                class="font-mono text-xs font-semibold text-zinc-800 dark:text-primary-dark-100">
                                {{ $entry->ip_address }}
                            </x-atoms.table-cell>
                            <x-atoms.table-cell
                                class="text-xs text-zinc-600 dark:text-primary-dark-300 max-w-xs truncate"
                                title="{{ $entry->reason }}">
                                {{ $entry->reason ?? '—' }}
                            </x-atoms.table-cell>
                            <x-atoms.table-cell>
                                <flux:badge color="{{ $entry->blocked_by === 'system' ? 'amber' : 'blue' }}"
                                    size="sm">
                                    {{ $entry->blocked_by ?? 'system' }}
                                </flux:badge>
                            </x-atoms.table-cell>
                            <x-atoms.table-cell class="text-xs">
                                @if ($entry->expires_at)
                                    @if ($entry->expires_at->isPast())
                                        <flux:badge color="zinc" size="sm">Kedaluwarsa</flux:badge>
                                    @else
                                        <span class="text-amber-600 dark:text-amber-400"
                                            title="{{ $entry->expires_at->format('d M Y H:i') }}">
                                            {{ $entry->expires_at->diffForHumans() }}
                                        </span>
                                    @endif
                                @else
                                    <flux:badge color="red" size="sm">Permanen</flux:badge>
                                @endif
                            </x-atoms.table-cell>
                            <x-atoms.table-cell align="right">
                                <div class="flex items-center justify-end gap-1">
                                    <x-atoms.button wire:click="openBlacklistModal('{{ $entry->id }}')"
                                        size="xs" variant="ghost" icon="pencil-square">
                                        Edit
                                    </x-atoms.button>
                                    <x-atoms.button wire:click="removeFromBlacklist('{{ $entry->id }}')"
                                        wire:confirm="Hapus {{ $entry->ip_address }} dari blacklist?" size="xs"
                                        variant="ghost" icon="trash">
                                        Hapus
                                    </x-atoms.button>
                                </div>
                            </x-atoms.table-cell>
                        </x-molecules.table-row>
                    @empty
                        <x-molecules.table-row>
                            <x-atoms.table-cell colspan="5" align="center" class="py-8 text-xs text-zinc-400">
                                Tidak ada IP yang diblokir.
                            </x-atoms.table-cell>
                        </x-molecules.table-row>
                    @endforelse

                    @if ($blacklistedIps->hasPages())
                        <x-slot:footer>
                            <div class="px-5 py-3">
                                {{ $blacklistedIps->links() }}
                            </div>
                        </x-slot:footer>
                    @endif
                </x-organisms.table>
            </x-organisms.data-panel>

            {{-- Modal Tambah / Edit IP ──────────────────────────────────── --}}
            <x-organisms.modal wire:model="showBlacklistModal" maxWidth="md" title="">
                <div class="space-y-5">
                    {{-- Heading --}}
                    <div class="flex items-center gap-3">
                        <div
                            class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-red-100 dark:bg-red-900/30">
                            <flux:icon name="no-symbol" class="size-4 text-red-600 dark:text-red-400" />
                        </div>
                        <div>
                            <flux:heading size="sm">
                                {{ $editBlacklistId ? 'Edit IP Diblokir' : 'Tambah IP ke Blacklist' }}
                            </flux:heading>
                            <flux:text size="sm" class="text-zinc-400">
                                {{ $editBlacklistId ? 'Perbarui alasan atau durasi blokir' : 'Blokir akses dari IP tertentu' }}
                            </flux:text>
                        </div>
                    </div>

                    {{-- IP Address --}}
                    <div>
                        <flux:label>IP Address</flux:label>
                        @if ($editBlacklistId)
                            <div
                                class="mt-1 flex items-center gap-2 rounded-lg border border-zinc-200 dark:border-primary-dark-600 bg-zinc-50 dark:bg-primary-dark-700/50 px-3 py-2">
                                <flux:icon name="lock-closed" class="size-3.5 text-zinc-400 shrink-0" />
                                <span
                                    class="font-mono text-sm text-zinc-700 dark:text-primary-dark-200">{{ $newBlacklistIp }}</span>
                            </div>
                        @else
                            <flux:input wire:model="newBlacklistIp" placeholder="192.168.1.1" class="font-mono" />
                            @error('newBlacklistIp')
                                <flux:error>{{ $message }}</flux:error>
                            @enderror
                        @endif
                    </div>

                    {{-- Alasan --}}
                    <div>
                        <flux:label>Alasan <span class="font-normal text-zinc-400">(opsional)</span></flux:label>
                        <flux:input wire:model="newBlacklistReason"
                            placeholder="Aktivitas mencurigakan, scraping, dll..." />
                        @error('newBlacklistReason')
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                    </div>

                    {{-- Durasi --}}
                    <div>
                        <flux:label>Durasi Blokir</flux:label>
                        <div class="mt-1 grid grid-cols-3 gap-2">
                            @foreach ([['value' => 1, 'label' => '1 Jam'], ['value' => 6, 'label' => '6 Jam'], ['value' => 24, 'label' => '1 Hari'], ['value' => 168, 'label' => '1 Minggu'], ['value' => 720, 'label' => '1 Bulan'], ['value' => 0, 'label' => 'Permanen']] as $opt)
                                <x-atoms.button type="button"
                                    wire:click="$set('newBlacklistDurationHours', {{ $opt['value'] }})"
                                    class="rounded-lg border px-3 py-2 text-xs font-medium transition-colors
                                        {{ $newBlacklistDurationHours == $opt['value']
                                            ? 'border-primary-500 bg-primary-50 text-primary-700 dark:border-primary-400 dark:bg-primary-900/20 dark:text-primary-300'
                                            : 'border-zinc-200 bg-white text-zinc-600 hover:border-zinc-300 hover:bg-zinc-50 dark:border-primary-dark-600 dark:bg-primary-dark-700/50 dark:text-primary-dark-300 dark:hover:bg-primary-dark-700' }}">
                                    {{ $opt['label'] }}
                                </x-atoms.button>
                            @endforeach
                        </div>
                        <div class="mt-2 flex items-center gap-2">
                            <flux:input wire:model="newBlacklistDurationHours" type="number" class="w-28" />
                            <span class="text-xs text-zinc-400">jam (0 = permanen)</span>
                        </div>
                        @error('newBlacklistDurationHours')
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                    </div>

                    {{-- Footer --}}
                    
        <x-slot:footer>
            <div class="flex justify-end gap-2 pt-1">
                        <x-atoms.button wire:click="$set('showBlacklistModal', false)" variant="ghost">
                            Batal
                        </x-atoms.button>
                        <x-atoms.button wire:click="saveBlacklist" variant="primary" icon="no-symbol">
                            {{ $editBlacklistId ? 'Simpan Perubahan' : 'Blokir IP' }}
                        </x-atoms.button>
                    </div>
                </x-slot:footer>
    </div>
    </x-organisms.modal>

            {{-- Top IP Gagal ─────────────────────────────────────────────── --}}
            @if ($topFailedIps->isNotEmpty())
                <x-organisms.data-panel title="Top IP Percobaan Gagal" icon="globe-alt" subtitle="Sepanjang waktu">
                    <div class="p-5">
                        <div class="space-y-2">
                            @foreach ($topFailedIps as $item)
                                <div class="flex items-center justify-between">
                                    <span class="font-mono text-xs text-zinc-600 dark:text-primary-dark-300">{{ $item->ip_address }}</span>
                                    <flux:badge color="amber" size="sm">{{ number_format($item->total) }}x</flux:badge>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </x-organisms.data-panel>
            @endif

            {{-- Log Percobaan Login ─────────────────────────────────────────── --}}
            <x-organisms.data-panel title="Log Percobaan Login" icon="clipboard-document-list">
                <x-slot:filter>
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:input wire:model.live.debounce.400ms="filterUser"
                            placeholder="Filter username..." class="w-36" size="sm" />
                        <flux:input wire:model.live.debounce.400ms="filterIp"
                            placeholder="Filter IP..." class="w-32 font-mono" size="sm" />
                        <flux:select wire:model.live="filterSuccess" size="sm">
                            <flux:select.option value="">Semua Status</flux:select.option>
                            <flux:select.option value="failed">Gagal</flux:select.option>
                            <flux:select.option value="success">Berhasil</flux:select.option>
                        </flux:select>
                    </div>
                </x-slot:filter>

                <div class="overflow-x-auto">
                    <x-organisms.table class="min-w-full">
                        <x-slot:headings>
                            <x-atoms.table-heading>Username</x-atoms.table-heading>
                            <x-atoms.table-heading>IP Address</x-atoms.table-heading>
                            <x-atoms.table-heading>Status</x-atoms.table-heading>
                            <x-atoms.table-heading>Alasan</x-atoms.table-heading>
                            <x-atoms.table-heading align="right">Waktu</x-atoms.table-heading>
                        </x-slot:headings>

                        @forelse ($recentAttempts as $attempt)
                            <x-molecules.table-row>
                                <x-atoms.table-cell class="font-mono text-xs text-zinc-700 dark:text-primary-dark-200">
                                    {{ $attempt->username }}
                                </x-atoms.table-cell>
                                <x-atoms.table-cell class="font-mono text-xs text-zinc-600 dark:text-primary-dark-300">
                                    {{ $attempt->ip_address }}
                                </x-atoms.table-cell>
                                <x-atoms.table-cell>
                                    @if ($attempt->success)
                                        <flux:badge color="emerald" size="sm">Berhasil</flux:badge>
                                    @else
                                        <flux:badge color="red" size="sm">Gagal</flux:badge>
                                    @endif
                                </x-atoms.table-cell>
                                <x-atoms.table-cell>
                                    @if ($attempt->failure_reason)
                                        @php
                                            $reasonLabels = [
                                                'wrong_credentials' => ['label' => 'Kredensial Salah', 'color' => 'amber'],
                                                'account_inactive' => ['label' => 'Akun Nonaktif', 'color' => 'orange'],
                                                'rate_limited' => ['label' => 'Rate Limited', 'color' => 'red'],
                                            ];
                                            $reason = $reasonLabels[$attempt->failure_reason] ?? [
                                                'label' => $attempt->failure_reason, 'color' => 'zinc',
                                            ];
                                        @endphp
                                        <flux:badge color="{{ $reason['color'] }}" size="sm">{{ $reason['label'] }}</flux:badge>
                                    @else
                                        <span class="text-xs text-zinc-400">—</span>
                                    @endif
                                </x-atoms.table-cell>
                                <x-atoms.table-cell align="right"
                                    class="text-xs text-zinc-500 dark:text-primary-dark-400"
                                    title="{{ $attempt->created_at->format('d M Y H:i:s') }}">
                                    {{ $attempt->created_at->diffForHumans() }}
                                </x-atoms.table-cell>
                            </x-molecules.table-row>
                        @empty
                            <x-molecules.table-row>
                                <x-atoms.table-cell colspan="5" align="center" class="py-8 text-xs text-zinc-400">
                                    Belum ada percobaan login yang tercatat.
                                </x-atoms.table-cell>
                            </x-molecules.table-row>
                        @endforelse
                    </x-organisms.table>
                </div>

                @if ($recentAttempts->hasPages())
                    <x-slot:footer>
                        <div class="px-5 py-3">
                            {{ $recentAttempts->links() }}
                        </div>
                    </x-slot:footer>
                @endif
            </x-organisms.data-panel>

        </div>
    </div>
</div>
