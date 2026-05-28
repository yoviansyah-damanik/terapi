<?php

use App\Constants\AppSecurityConfig;
use App\Models\IpBlacklist;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Models\ActivityLog;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Jenssegers\Agent\Agent;

new #[Layout('layouts::auth', ['title' => 'Login'])] class extends Component {
    public bool $isLoading = false;
    public string $username = '';
    public string $password = '';
    public bool $rememberMe = false;

    // Anti-robot
    public string $website = ''; // honeypot
    public string $captchaQuestion = '';
    public string $captchaInput = '';
    public string $captchaToken = '';
    public bool $showCaptcha = false;

    public function mount(): void
    {
        $this->generateCaptcha();
        $this->refreshCaptchaVisibility();
    }

    protected function generateCaptcha(): void
    {
        $a = rand(1, 20);
        $b = rand(1, 20);
        $this->captchaQuestion = "{$a} + {$b}";
        $this->captchaToken = Str::random(32);
        cache()->put("captcha_{$this->captchaToken}", $a + $b, now()->addMinutes(15));
    }

    protected function refreshCaptchaVisibility(): void
    {
        if (!AppSecurityConfig::bool('app.security.captcha.enabled')) {
            $this->showCaptcha = false;
            return;
        }
        $triggerAfter = AppSecurityConfig::int('app.security.captcha.trigger_after');
        $recentFailed = LoginAttempt::failed()
            ->forIp(request()->ip())
            ->recent(60)
            ->count();
        $this->showCaptcha = $recentFailed >= $triggerAfter;
    }

    protected function autoBlacklistIfNeeded(string $ip): void
    {
        if (!AppSecurityConfig::bool('app.security.blacklist.auto_enabled')) {
            return;
        }

        $threshold = AppSecurityConfig::int('app.security.blacklist.auto_threshold');
        $recentFailed = LoginAttempt::failed()->forIp($ip)->recent(60)->count();

        if ($recentFailed >= $threshold && !IpBlacklist::isBlocked($ip)) {
            $hours = AppSecurityConfig::int('app.security.blacklist.auto_duration_hours');
            $expiresAt = $hours > 0 ? now()->addHours($hours) : null;
            IpBlacklist::block($ip, "Auto-blacklist: {$recentFailed}x gagal login dalam 60 menit", $expiresAt);
        }
    }

    public function rules()
    {
        return [
            'username' => 'required|string',
            'password' => 'required|string',
            'rememberMe' => 'nullable|boolean',
        ];
    }

    public function login()
    {
        // Honeypot — bot yang mengisi field tersembunyi langsung ditolak
        if ($this->website !== '') {
            $this->isLoading = false;
            return;
        }

        $throttleKey = 'login.' . Str::lower(trim($this->username)) . '|' . request()->ip();

        // Cek login throttle sebelum validasi
        if (AppSecurityConfig::bool('app.security.login.enabled')) {
            $max = AppSecurityConfig::int('app.security.login.max_attempts');
            $lockout = AppSecurityConfig::int('app.security.login.lockout_minutes');

            if (RateLimiter::tooManyAttempts($throttleKey, $max)) {
                $seconds = RateLimiter::availableIn($throttleKey);
                LoginAttempt::log($this->username, request()->ip(), request()->userAgent(), false, 'rate_limited');
                $this->isLoading = false;
                $this->toastError("Akun terkunci sementara. Coba lagi dalam {$seconds} detik.", 'Terlalu Banyak Percobaan');
                return;
            }
        }

        // Validasi CAPTCHA jika ditampilkan
        if ($this->showCaptcha && AppSecurityConfig::bool('app.security.captcha.enabled')) {
            $expected = cache()->get("captcha_{$this->captchaToken}");
            if ($expected === null || (int) $this->captchaInput !== (int) $expected) {
                cache()->forget("captcha_{$this->captchaToken}");
                $this->generateCaptcha();
                $this->captchaInput = '';
                $this->isLoading = false;
                $this->toastError('Jawaban CAPTCHA salah atau sudah kedaluwarsa.', 'Verifikasi Gagal');
                return;
            }
            cache()->forget("captcha_{$this->captchaToken}");
        }

        $this->validate();

        $this->isLoading = true;

        try {
            $agent = new Agent();
            if ($agent->isRobot()) {
                $this->isLoading = false;
                return;
            }

            $user = User::whereUsername($this->username)->first();

            if (!$user || !Hash::check($this->password, $user->password)) {
                if (AppSecurityConfig::bool('app.security.login.enabled')) {
                    $lockout = AppSecurityConfig::int('app.security.login.lockout_minutes');
                    RateLimiter::hit($throttleKey, $lockout * 60);
                }
                LoginAttempt::log($this->username, request()->ip(), request()->userAgent(), false, 'wrong_credentials');
                $this->autoBlacklistIfNeeded(request()->ip());
                $this->refreshCaptchaVisibility();
                $this->generateCaptcha();
                $this->captchaInput = '';
                $this->isLoading = false;
                $this->toastError('Nama pengguna atau kata sandi salah', 'Login Gagal');
                return;
            }

            // Cek status akun aktif
            if (!$user->is_active) {
                if (AppSecurityConfig::bool('app.security.login.enabled')) {
                    $lockout = AppSecurityConfig::int('app.security.login.lockout_minutes');
                    RateLimiter::hit($throttleKey, $lockout * 60);
                }
                LoginAttempt::log($this->username, request()->ip(), request()->userAgent(), false, 'account_inactive');
                $this->autoBlacklistIfNeeded(request()->ip());
                $this->refreshCaptchaVisibility();
                $this->generateCaptcha();
                $this->captchaInput = '';
                $this->isLoading = false;
                $this->toastError('Akun Anda tidak aktif. Hubungi administrator.', 'Login Gagal');
                return;
            }

            RateLimiter::clear($throttleKey);
            LoginAttempt::log($this->username, request()->ip(), request()->userAgent(), true);

            Auth::login($user, $this->rememberMe);
            request()->session()->regenerate();
            $user->update(['last_login_at' => now()]);

            ActivityLog::log(
                type: 'user_login',
                subject: 'Login ke sistem',
                description: null,
                properties: [
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ],
            );

            $this->toastSuccess('Selamat datang kembali, ' . $user->name, 'Login Berhasil');

            return $this->redirectIntended(route('home'), navigate: false);
        } catch (\Exception $e) {
            $this->isLoading = false;
            $this->toastError('Terjadi kesalahan sistem. Silakan coba lagi.', 'Error');
        }
    }
};
?>

<div class="flex min-h-screen bg-white dark:bg-primary-dark-900">
    {{-- Left side - Branding with Background Image --}}
    <div class="relative items-center justify-center flex-1 hidden overflow-hidden lg:flex"
        style="background-image: url('{{ Vite::image('login-bg.jpg') }}'); background-size: cover; background-position: center;">
        {{-- Gradient Overlay --}}
        <div
            class="absolute inset-0 bg-gradient-to-br from-primary-700/90 via-primary-800/85 to-secondary-900/80 dark:from-primary-950/95 dark:via-primary-900/90 dark:to-primary-dark-900/85">
        </div>

        {{-- Content --}}
        <div class="relative z-10 flex flex-col items-center gap-6 p-8">
            <img src="{{ Vite::image('logo.png') }}" class="w-full max-w-lg drop-shadow-lg" alt="Logo" />
            <h1 class="text-4xl mt-3 font-bold text-center text-secondary-400 font-title drop-shadow-lg uppercase">
                {{ config('app.name') }}
            </h1>
        </div>
    </div>

    {{-- Right side - Login Form --}}
    <div class="flex items-center justify-center w-full px-6 lg:w-[480px] lg:px-12">
        <div class="w-full max-w-sm">
            {{-- Mobile branding --}}
            <div class="flex flex-col items-start gap-3 mb-8 lg:hidden">
                <img src="{{ Vite::image('logo.png') }}" class="w-full max-w-48" alt="Logo" />
                <h1 class="text-base font-bold text-start text-primary-600 dark:text-primary-400 font-title uppercase">
                    {{ config('app.name') }}
                </h1>
            </div>

            {{-- Login heading --}}
            <div class="flex items-center justify-between mb-8">
                <div class="hidden lg:block">
                    <h2 class="text-2xl font-bold text-primary-700 dark:text-primary-300">Selamat Datang</h2>
                    <p class="mt-1 text-primary-500 dark:text-primary-400">Silakan masuk ke akun Anda</p>
                </div>
                <x-dark-mode-toggle variant="switch" />
            </div>

            {{-- Login form --}}
            <form wire:submit='login' class="space-y-5">
                {{-- Honeypot — jangan diisi (untuk bot) --}}
                <div style="position:absolute;left:-9999px;top:-9999px;" aria-hidden="true">
                    <input type="text" wire:model="website" name="website" tabindex="-1" autocomplete="off" />
                </div>

                <flux:field>
                    <flux:label class="text-primary-700 dark:text-primary-300">Nama Pengguna</flux:label>
                    <flux:input wire:model.blur="username" placeholder="Masukkan nama pengguna" icon="user"
                        :disabled="$isLoading" class="!rounded-full" />
                    @error('username')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>

                <flux:field>
                    <flux:label class="text-primary-700 dark:text-primary-300">Kata Sandi</flux:label>
                    <flux:input wire:model.blur="password" type="password" placeholder="Masukkan kata sandi"
                        icon="lock-closed" :disabled="$isLoading" class="!rounded-full" />
                    @error('password')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>

                {{-- CAPTCHA anti-robot --}}
                @if ($showCaptcha)
                    <div
                        class="rounded-xl border border-amber-200 bg-amber-50 dark:border-amber-800/40 dark:bg-amber-900/20 p-4 space-y-2">
                        <p class="text-xs font-semibold text-amber-700 dark:text-amber-400 flex items-center gap-1.5">
                            <flux:icon name="shield-exclamation" class="size-4" />
                            Verifikasi Anti-Robot
                        </p>
                        <p class="text-sm font-medium text-amber-800 dark:text-amber-300">
                            Berapa hasil dari <strong>{{ $captchaQuestion }}</strong>?
                        </p>
                        <flux:input wire:model="captchaInput" type="number" placeholder="Jawaban..." icon="calculator"
                            :disabled="$isLoading" class="!rounded-full" />
                        @error('captchaInput')
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                    </div>
                @endif

                <div class="flex items-center justify-between pt-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <flux:checkbox wire:model="rememberMe" :disabled="$isLoading" />
                        <span class="text-sm text-primary-600 dark:text-primary-400">Ingatkan Saya</span>
                    </label>
                </div>

                <x-atoms.button type="submit" variant="primary"
                    class="w-full !rounded-full !bg-primary-600 hover:!bg-primary-700 dark:!bg-primary-500 dark:hover:!bg-primary-600 !text-white font-semibold mt-6"
                    :disabled="$isLoading" wire:loading.attr="disabled" wire:target="login">
                    Masuk
                </x-atoms.button>
            </form>

            {{-- OAuth RS Login --}}
            @if (config('services.oauth_rs.base_url'))
                <div class="mt-6">
                    <div class="relative flex items-center">
                        <div class="flex-1 border-t border-zinc-200 dark:border-primary-dark-700"></div>
                        <span class="px-3 text-xs text-zinc-400">atau</span>
                        <div class="flex-1 border-t border-zinc-200 dark:border-primary-dark-700"></div>
                    </div>

                    <a href="{{ $isLoading ? '#' : route('oauth.redirect') }}"
                        class="mt-4 flex items-center justify-center gap-2 w-full rounded-full border border-primary-200 dark:border-primary-800 px-4 py-2.5 text-sm font-medium text-primary-700 dark:text-primary-300 hover:bg-primary-50 dark:hover:bg-primary-900/30 transition-colors {{ $isLoading ? 'opacity-50 pointer-events-none cursor-not-allowed' : '' }}">
                        <flux:icon name="shield-check" class="size-4 shrink-0" />
                        Login dengan OAuth RS
                    </a>

                    @error('oauth')
                        <p class="mt-2 text-xs text-center text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            {{-- Footer --}}
            <p class="mt-8 text-sm text-center text-primary-400 dark:text-primary-500">
                &copy; {{ date('Y') }} {{ config('hospital.name') }}
            </p>
        </div>
    </div>
</div>
