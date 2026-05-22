<?php

use App\Constants\SecurityConfig;
use App\Models\Api\ApiSecurityLog;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithPagination;

new #[Layout('layouts::app')] #[Title('Keamanan API')] class extends Component {
    use WithPagination;

    // ── Filter log ────────────────────────────────────────────────────────
    public string $filterType = '';
    public string $filterIp = '';
    public bool $onlyActive = false;

    // ── Konfigurasi Rate Limiter ──────────────────────────────────────────
    public bool $rateLimitEnabled = true;
    public int $authMax = 10;
    public int $authWindow = 5;
    public int $generalMax = 300;
    public int $webhookMax = 60;

    // ── Konfigurasi Input Size ────────────────────────────────────────────
    public bool $inputSizeEnabled = true;
    public int $authKb = 256;
    public int $simrsKb = 2048;
    public int $waKb = 5120;
    public int $tteKb = 20480;

    // ── Konfigurasi Anomaly Detection ─────────────────────────────────────
    public bool $anomalyEnabled = true;
    public int $anomalyWindow = 15;
    public int $anomalyMinReq = 20;
    public int $anomalyErrorRate = 30;
    public int $anomalyHighVolume = 500;
    public int $anomalyBrute = 20;

    // ── CORS ──────────────────────────────────────────────────────────────
    public string $corsOrigins = '*';

    public function mount(): void
    {
        $this->rateLimitEnabled = SecurityConfig::bool('api.security.rate_limit.enabled');
        $this->authMax = SecurityConfig::int('api.security.rate_limit.auth_max');
        $this->authWindow = SecurityConfig::int('api.security.rate_limit.auth_window');
        $this->generalMax = SecurityConfig::int('api.security.rate_limit.general_max');
        $this->webhookMax = SecurityConfig::int('api.security.rate_limit.webhook_max');

        $this->inputSizeEnabled = SecurityConfig::bool('api.security.input_size.enabled');
        $this->authKb = SecurityConfig::int('api.security.input_size.auth_kb');
        $this->simrsKb = SecurityConfig::int('api.security.input_size.simrs_kb');
        $this->waKb = SecurityConfig::int('api.security.input_size.wa_kb');
        $this->tteKb = SecurityConfig::int('api.security.input_size.tte_kb');

        $this->anomalyEnabled = SecurityConfig::bool('api.security.anomaly.enabled');
        $this->anomalyWindow = SecurityConfig::int('api.security.anomaly.window_minutes');
        $this->anomalyMinReq = SecurityConfig::int('api.security.anomaly.min_requests');
        $this->anomalyErrorRate = SecurityConfig::int('api.security.anomaly.error_rate_pct');
        $this->anomalyHighVolume = SecurityConfig::int('api.security.anomaly.high_volume');
        $this->anomalyBrute = SecurityConfig::int('api.security.anomaly.brute_force');

        $this->corsOrigins = SecurityConfig::get('api.security.cors.allowed_origins');
    }

    public function saveRateLimit(): void
    {
        $this->validate([
            'authMax' => 'required|integer|min:1|max:1000',
            'authWindow' => 'required|integer|min:1|max:60',
            'generalMax' => 'required|integer|min:1|max:10000',
            'webhookMax' => 'required|integer|min:1|max:1000',
        ]);

        SecurityConfig::set('api.security.rate_limit.enabled', $this->rateLimitEnabled ? '1' : '0');
        SecurityConfig::set('api.security.rate_limit.auth_max', $this->authMax);
        SecurityConfig::set('api.security.rate_limit.auth_window', $this->authWindow);
        SecurityConfig::set('api.security.rate_limit.general_max', $this->generalMax);
        SecurityConfig::set('api.security.rate_limit.webhook_max', $this->webhookMax);

        $this->dispatch('toast', type: 'success', message: 'Konfigurasi Rate Limiter disimpan.');
    }

    public function saveInputSize(): void
    {
        $this->validate([
            'authKb' => 'required|integer|min:1|max:10240',
            'simrsKb' => 'required|integer|min:1|max:102400',
            'waKb' => 'required|integer|min:1|max:102400',
            'tteKb' => 'required|integer|min:1|max:204800',
        ]);

        SecurityConfig::set('api.security.input_size.enabled', $this->inputSizeEnabled ? '1' : '0');
        SecurityConfig::set('api.security.input_size.auth_kb', $this->authKb);
        SecurityConfig::set('api.security.input_size.simrs_kb', $this->simrsKb);
        SecurityConfig::set('api.security.input_size.wa_kb', $this->waKb);
        SecurityConfig::set('api.security.input_size.tte_kb', $this->tteKb);

        $this->dispatch('toast', type: 'success', message: 'Konfigurasi Input Size disimpan.');
    }

    public function saveAnomaly(): void
    {
        $this->validate([
            'anomalyWindow' => 'required|integer|min:1|max:60',
            'anomalyMinReq' => 'required|integer|min:1|max:1000',
            'anomalyErrorRate' => 'required|integer|min:1|max:100',
            'anomalyHighVolume' => 'required|integer|min:1|max:100000',
            'anomalyBrute' => 'required|integer|min:1|max:1000',
        ]);

        SecurityConfig::set('api.security.anomaly.enabled', $this->anomalyEnabled ? '1' : '0');
        SecurityConfig::set('api.security.anomaly.window_minutes', $this->anomalyWindow);
        SecurityConfig::set('api.security.anomaly.min_requests', $this->anomalyMinReq);
        SecurityConfig::set('api.security.anomaly.error_rate_pct', $this->anomalyErrorRate);
        SecurityConfig::set('api.security.anomaly.high_volume', $this->anomalyHighVolume);
        SecurityConfig::set('api.security.anomaly.brute_force', $this->anomalyBrute);

        $this->dispatch('toast', type: 'success', message: 'Konfigurasi Anomaly Detection disimpan.');
    }

    public function saveCors(): void
    {
        $this->validate([
            'corsOrigins' => 'required|string|max:2000',
        ]);

        SecurityConfig::set('api.security.cors.allowed_origins', $this->corsOrigins);

        $this->dispatch('toast', type: 'success', message: 'Konfigurasi CORS disimpan.');
    }

    public function updatingFilterType(): void
    {
        $this->resetPage();
    }
    public function updatingFilterIp(): void
    {
        $this->resetPage();
    }
    public function updatingOnlyActive(): void
    {
        $this->resetPage();
    }

    public function resolve(string $id): void
    {
        ApiSecurityLog::findOrFail($id)->update(['resolved_at' => now()]);
        $this->dispatch('toast', type: 'success', message: 'Log ditandai selesai.');
    }

    public function resolveAll(): void
    {
        ApiSecurityLog::whereNull('resolved_at')->update(['resolved_at' => now()]);
        $this->dispatch('toast', type: 'success', message: 'Semua log aktif telah diselesaikan.');
    }

    public function with(): array
    {
        // ── Stats ────────────────────────────────────────────────────────────
        $totalLogs = ApiSecurityLog::count();
        $activeLogs = ApiSecurityLog::whereNull('resolved_at')->count();
        $resolvedLogs = ApiSecurityLog::whereNotNull('resolved_at')->count();
        $todayLogs = ApiSecurityLog::whereDate('created_at', today())->count();

        $byType = ApiSecurityLog::select('type', DB::raw('COUNT(*) as total'))->groupBy('type')->pluck('total', 'type');

        // ── Tren 7 hari ──────────────────────────────────────────────────────
        $trendData = ApiSecurityLog::select(DB::raw('date(created_at) as day'), 'type', DB::raw('COUNT(*) as total'))
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->groupBy('day', 'type')
            ->orderBy('day')
            ->get()
            ->groupBy('day');

        $trendDays = collect(range(6, 0))->map(fn($i) => now()->subDays($i)->format('Y-m-d'));
        $trendLabels = $trendDays->map(fn($d) => now()->parse($d)->isoFormat('D MMM'))->values()->all();

        $types = ['rate_limited', 'oversized_request', 'anomaly_high_failure', 'anomaly_high_volume', 'anomaly_brute_force'];
        $trendSeries = collect($types)
            ->mapWithKeys(function ($type) use ($trendDays, $trendData) {
                $counts = $trendDays->map(fn($day) => $trendData->get($day, collect())->firstWhere('type', $type)?->total ?? 0)->values()->all();
                return [$type => $counts];
            })
            ->all();

        // ── Top IP ───────────────────────────────────────────────────────────
        $topIps = ApiSecurityLog::select('ip_address', DB::raw('COUNT(*) as total'))->groupBy('ip_address')->orderByDesc('total')->limit(10)->get();

        // ── Log list ─────────────────────────────────────────────────────────
        $logs = ApiSecurityLog::query()->when($this->filterType, fn($q) => $q->where('type', $this->filterType))->when($this->filterIp, fn($q) => $q->where('ip_address', 'like', "%{$this->filterIp}%"))->when($this->onlyActive, fn($q) => $q->whereNull('resolved_at'))->orderByDesc('created_at')->paginate(20);

        return compact('totalLogs', 'activeLogs', 'resolvedLogs', 'todayLogs', 'byType', 'trendLabels', 'trendSeries', 'topIps', 'logs');
    }
};

?>

<div x-data="{ configTab: 'rate-limit' }">
    <x-ui.page-header title="Keamanan API" subtitle="Pantau ancaman, anomali, dan insiden keamanan pada lapisan API" />

    {{-- Stats Cards --}}
    <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-organisms.stat-card title="Total Insiden" :value="number_format($totalLogs)" icon="shield-exclamation" color="zinc"
            subtitle="sepanjang waktu" />
        <x-organisms.stat-card title="Masih Aktif" :value="number_format($activeLogs)" icon="exclamation-triangle" color="red"
            subtitle="belum diselesaikan" />
        <x-organisms.stat-card title="Diselesaikan" :value="number_format($resolvedLogs)" icon="check-circle" color="emerald"
            subtitle="sudah ditangani" />
        <x-organisms.stat-card title="Hari Ini" :value="number_format($todayLogs)" icon="calendar-days" color="blue"
            :subtitle="now()->format('d M Y')" />
    </div>

    {{-- Threat Overview + Tren --}}
    <div class="mb-4 grid grid-cols-1 gap-4 lg:grid-cols-3">

        {{-- Insiden per Tipe --}}
        <x-organisms.data-panel title="Insiden per Tipe">
            <div class="p-5 space-y-3">
                @foreach ([['type' => 'rate_limited', 'label' => 'Rate Limited', 'color' => 'amber'], ['type' => 'oversized_request', 'label' => 'Request Terlalu Besar', 'color' => 'orange'], ['type' => 'anomaly_high_failure', 'label' => 'Error Rate Tinggi', 'color' => 'red'], ['type' => 'anomaly_high_volume', 'label' => 'Volume Anomali', 'color' => 'violet'], ['type' => 'anomaly_brute_force', 'label' => 'Brute Force', 'color' => 'red']] as $item)
                    @php $count = $byType->get($item['type'], 0); @endphp
                    <div class="flex items-center justify-between">
                        <flux:badge color="{{ $item['color'] }}" size="sm">{{ $item['label'] }}</flux:badge>
                        <span
                            class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-200">{{ number_format($count) }}</span>
                    </div>
                @endforeach
            </div>
        </x-organisms.data-panel>

        {{-- Tren 7 Hari --}}
        <x-organisms.data-panel class="lg:col-span-2" title="Tren Insiden (7 Hari)">
            <div class="p-5" wire:ignore>
                <canvas id="secTrendChart" height="110"></canvas>
            </div>
        </x-organisms.data-panel>
    </div>

    {{-- Panel Konfigurasi --}}
    <x-organisms.data-panel class="mb-4" title="Konfigurasi Perlindungan" icon="cog-6-tooth">

        {{-- Tab Nav --}}
        <div class="px-5 border-b border-zinc-100 dark:border-primary-dark-700/60 mt-3">
            <x-molecules.tabs class="mb-0 border-none">
                @foreach ([['key' => 'rate-limit', 'label' => 'Rate Limiter', 'icon' => 'bolt'], ['key' => 'input-size', 'label' => 'Input Size', 'icon' => 'archive-box-x-mark'], ['key' => 'anomaly', 'label' => 'Anomaly Detection', 'icon' => 'eye'], ['key' => 'cors', 'label' => 'CORS & Proxy', 'icon' => 'globe-alt']] as $cfgTab)
                    <x-atoms.tab-item @click="configTab = '{{ $cfgTab['key'] }}'" ::active="configTab === '{{ $cfgTab['key'] }}'"
                        class="flex items-center gap-1.5 pb-3">
                        <flux:icon :name="$cfgTab['icon']" class="w-3.5 h-3.5" />
                        {{ $cfgTab['label'] }}
                    </x-atoms.tab-item>
                @endforeach
            </x-molecules.tabs>
        </div>

        {{-- Tab: Rate Limiter --}}
        <div x-show="configTab === 'rate-limit'" class="divide-y divide-zinc-100 dark:divide-primary-dark-700/60">
            {{-- Description banner --}}
            <div class="flex items-center justify-between gap-4 px-5 py-3.5 bg-amber-50/60 dark:bg-amber-900/10">
                <div class="flex items-center gap-2.5">
                    <div
                        class="flex items-center justify-center w-7 h-7 rounded-lg bg-amber-100 dark:bg-amber-900/40 shrink-0">
                        <flux:icon.bolt class="w-3.5 h-3.5 text-amber-600 dark:text-amber-400" />
                    </div>
                    <p class="text-xs text-zinc-500 dark:text-primary-dark-400">Batas jumlah request per periode untuk
                        mencegah DDoS dan abuse token.</p>
                </div>
                <x-atoms.toggle wire:model="rateLimitEnabled" id="toggle-ratelimit" label="Aktifkan"
                    labelPosition="left" class="shrink-0" />
            </div>
            {{-- Fields --}}
            <div class="p-5 grid grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach ([['model' => 'authMax', 'label' => 'Auth — Maks. Request', 'unit' => 'req', 'hint' => 'per window'], ['model' => 'authWindow', 'label' => 'Auth — Window', 'unit' => 'menit', 'hint' => 'durasi window'], ['model' => 'generalMax', 'label' => 'API Umum — Maks.', 'unit' => 'req/min', 'hint' => 'per menit / token'], ['model' => 'webhookMax', 'label' => 'Webhook — Maks.', 'unit' => 'req/min', 'hint' => 'per menit / IP']] as $f)
                    <div class="group">
                        <label
                            class="block text-xs font-semibold text-zinc-600 dark:text-primary-dark-300 mb-0.5">{{ $f['label'] }}</label>
                        <p class="text-[10px] text-zinc-400 dark:text-primary-dark-500 mb-1.5">{{ $f['hint'] }}</p>
                        <div
                            class="flex items-stretch rounded-lg overflow-hidden border border-zinc-200 dark:border-primary-dark-600 focus-within:ring-1 focus-within:ring-primary-400 focus-within:border-primary-400">
                            <input type="number" wire:model="{{ $f['model'] }}" min="1"
                                class="flex-1 min-w-0 text-sm px-3 py-2 bg-white dark:bg-primary-dark-900 text-zinc-700 dark:text-primary-dark-200 focus:outline-none">
                            <span
                                class="flex items-center px-2.5 text-xs text-zinc-400 bg-zinc-50 dark:bg-primary-dark-800 border-l border-zinc-200 dark:border-primary-dark-600 whitespace-nowrap">{{ $f['unit'] }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="flex justify-end px-5 py-3">
                <x-atoms.button variant="primary" wire:click="saveRateLimit" icon="check" size="sm">Simpan Rate
                    Limiter</x-atoms.button>
            </div>
        </div>

        {{-- Tab: Input Size --}}
        <div x-show="configTab === 'input-size'" class="divide-y divide-zinc-100 dark:divide-primary-dark-700/60">
            <div class="flex items-center justify-between gap-4 px-5 py-3.5 bg-orange-50/60 dark:bg-orange-900/10">
                <div class="flex items-center gap-2.5">
                    <div
                        class="flex items-center justify-center w-7 h-7 rounded-lg bg-orange-100 dark:bg-orange-900/40 shrink-0">
                        <flux:icon.archive-box-x-mark class="w-3.5 h-3.5 text-orange-600 dark:text-orange-400" />
                    </div>
                    <p class="text-xs text-zinc-500 dark:text-primary-dark-400">Batas ukuran body request per endpoint
                        untuk mencegah oversized payload attack.</p>
                </div>
                <x-atoms.toggle wire:model="inputSizeEnabled" id="toggle-inputsize" label="Aktifkan"
                    labelPosition="left" class="shrink-0" />
            </div>
            <div class="p-5 grid grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach ([['model' => 'authKb', 'label' => 'Auth Token', 'hint' => 'POST /auth/token'], ['model' => 'simrsKb', 'label' => 'SIMRS Log', 'hint' => 'POST /simrs/log*'], ['model' => 'waKb', 'label' => 'WhatsApp', 'hint' => 'POST /whatsapp/*'], ['model' => 'tteKb', 'label' => 'TTE / PDF', 'hint' => 'POST /tte/*']] as $field)
                    <div>
                        <label
                            class="block text-xs font-semibold text-zinc-600 dark:text-primary-dark-300 mb-0.5">{{ $field['label'] }}</label>
                        <p class="text-[10px] text-zinc-400 dark:text-primary-dark-500 mb-1.5 font-mono">
                            {{ $field['hint'] }}</p>
                        <div
                            class="flex items-stretch rounded-lg overflow-hidden border border-zinc-200 dark:border-primary-dark-600 focus-within:ring-1 focus-within:ring-primary-400 focus-within:border-primary-400">
                            <input type="number" wire:model="{{ $field['model'] }}" min="1"
                                class="flex-1 min-w-0 text-sm px-3 py-2 bg-white dark:bg-primary-dark-900 text-zinc-700 dark:text-primary-dark-200 focus:outline-none">
                            <span
                                class="flex items-center px-2.5 text-xs text-zinc-400 bg-zinc-50 dark:bg-primary-dark-800 border-l border-zinc-200 dark:border-primary-dark-600">KB</span>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="flex justify-end px-5 py-3">
                <x-atoms.button variant="primary" wire:click="saveInputSize" icon="check" size="sm">Simpan Input
                    Size</x-atoms.button>
            </div>
        </div>

        {{-- Tab: Anomaly Detection --}}
        <div x-show="configTab === 'anomaly'" class="divide-y divide-zinc-100 dark:divide-primary-dark-700/60">
            <div class="flex items-center justify-between gap-4 px-5 py-3.5 bg-red-50/60 dark:bg-red-900/10">
                <div class="flex items-center gap-2.5">
                    <div
                        class="flex items-center justify-center w-7 h-7 rounded-lg bg-red-100 dark:bg-red-900/40 shrink-0">
                        <flux:icon.eye class="w-3.5 h-3.5 text-red-600 dark:text-red-400" />
                    </div>
                    <p class="text-xs text-zinc-500 dark:text-primary-dark-400">Scheduler otomatis mendeteksi pola
                        mencurigakan dari log API berdasarkan threshold berikut.</p>
                </div>
                <x-atoms.toggle wire:model="anomalyEnabled" id="toggle-anomaly" label="Aktifkan"
                    labelPosition="left" class="shrink-0" />
            </div>
            <div class="p-5 grid grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ([['model' => 'anomalyWindow', 'label' => 'Window Analisis', 'unit' => 'menit', 'hint' => 'Periode yang dianalisis tiap deteksi'], ['model' => 'anomalyMinReq', 'label' => 'Min. Request Error Rate', 'unit' => 'request', 'hint' => 'Minimal request agar error rate dihitung'], ['model' => 'anomalyErrorRate', 'label' => 'Threshold Error Rate', 'unit' => '%', 'hint' => 'Error rate (%) yang dianggap anomali'], ['model' => 'anomalyHighVolume', 'label' => 'Threshold Volume Tinggi', 'unit' => 'request', 'hint' => 'Jumlah request per IP dalam window'], ['model' => 'anomalyBrute', 'label' => 'Threshold Brute Force', 'unit' => 'kegagalan', 'hint' => 'Auth 401 per IP dalam window']] as $f)
                    <div>
                        <label
                            class="block text-xs font-semibold text-zinc-600 dark:text-primary-dark-300 mb-0.5">{{ $f['label'] }}</label>
                        <p class="text-[10px] text-zinc-400 dark:text-primary-dark-500 mb-1.5">{{ $f['hint'] }}</p>
                        <div
                            class="flex items-stretch rounded-lg overflow-hidden border border-zinc-200 dark:border-primary-dark-600 focus-within:ring-1 focus-within:ring-primary-400 focus-within:border-primary-400">
                            <input type="number" wire:model="{{ $f['model'] }}" min="1"
                                class="flex-1 min-w-0 text-sm px-3 py-2 bg-white dark:bg-primary-dark-900 text-zinc-700 dark:text-primary-dark-200 focus:outline-none">
                            <span
                                class="flex items-center px-2.5 text-xs text-zinc-400 bg-zinc-50 dark:bg-primary-dark-800 border-l border-zinc-200 dark:border-primary-dark-600 whitespace-nowrap">{{ $f['unit'] }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="flex justify-end px-5 py-3">
                <x-atoms.button variant="primary" wire:click="saveAnomaly" icon="check" size="sm">Simpan
                    Anomaly</x-atoms.button>
            </div>
        </div>

        {{-- Tab: CORS & Proxy --}}
        <div x-show="configTab === 'cors'" class="divide-y divide-zinc-100 dark:divide-primary-dark-700/60">
            <div class="flex items-center gap-2.5 px-5 py-3.5 bg-violet-50/60 dark:bg-violet-900/10">
                <div
                    class="flex items-center justify-center w-7 h-7 rounded-lg bg-violet-100 dark:bg-violet-900/40 shrink-0">
                    <flux:icon.globe-alt class="w-3.5 h-3.5 text-violet-600 dark:text-violet-400" />
                </div>
                <p class="text-xs text-zinc-500 dark:text-primary-dark-400">Konfigurasi domain yang diizinkan mengakses
                    API dari browser, dan pengaturan Trusted Proxy.</p>
            </div>
            <div class="p-5 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-zinc-600 dark:text-primary-dark-300 mb-0.5">CORS
                        Allowed Origins</label>
                    <p class="text-[10px] text-zinc-400 dark:text-primary-dark-500 mb-1.5">Gunakan <code
                            class="font-mono">*</code> untuk semua domain, atau daftar domain dipisah koma.</p>
                    <div
                        class="flex items-stretch rounded-lg overflow-hidden border border-zinc-200 dark:border-primary-dark-600 focus-within:ring-1 focus-within:ring-primary-400">
                        <input type="text" wire:model="corsOrigins"
                            placeholder="* atau https://domain1.com,https://domain2.com"
                            class="flex-1 text-sm px-3 py-2 bg-white dark:bg-primary-dark-900 text-zinc-700 dark:text-primary-dark-200 focus:outline-none">
                    </div>
                </div>
                <div
                    class="flex gap-3 p-3.5 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200/80 dark:border-amber-800/40">
                    <flux:icon.information-circle class="w-4 h-4 text-amber-500 dark:text-amber-400 shrink-0 mt-0.5" />
                    <div>
                        <p class="text-xs font-semibold text-amber-700 dark:text-amber-300 mb-0.5">Trusted Proxy
                            (Read-only)</p>
                        <p class="text-xs text-amber-600 dark:text-amber-400 leading-relaxed">
                            Dikonfigurasi di <code class="font-mono">bootstrap/app.php</code> — <code
                                class="font-mono">trustProxies(at: '*')</code>.
                            Perubahan memerlukan deployment ulang.
                        </p>
                    </div>
                </div>
            </div>
            <div class="flex justify-end px-5 py-3">
                <x-atoms.button variant="primary" wire:click="saveCors" icon="check" size="sm">Simpan
                    CORS</x-atoms.button>
            </div>
        </div>
    </x-organisms.data-panel>

    {{-- Status Perlindungan --}}
    <x-organisms.data-panel class="mb-4" title="Status Perlindungan Aktif"
        subtitle="Ringkasan konfigurasi keamanan yang berjalan">
        <div class="p-5 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
            @php
                $protections = [
                    [
                        'label' => 'Trusted Proxy',
                        'icon' => 'server',
                        'icon_color' => 'text-blue-600 dark:text-blue-400',
                        'icon_bg' => 'bg-blue-100 dark:bg-blue-900/40',
                        'active' => true,
                        'badge_label' => 'Aktif',
                        'desc' =>
                            'Membaca IP asli klien di balik load balancer / reverse proxy. Mencegah spoofing via X-Forwarded-For.',
                    ],
                    [
                        'label' => 'Rate Limiting',
                        'icon' => 'bolt',
                        'icon_color' => 'text-amber-600 dark:text-amber-400',
                        'icon_bg' => 'bg-amber-100 dark:bg-amber-900/40',
                        'active' => $rateLimitEnabled,
                        'badge_label' => $rateLimitEnabled
                            ? "Auth {$authMax}×/{$authWindow}min · API {$generalMax}/min · WH {$webhookMax}/min"
                            : 'Nonaktif',
                        'desc' => 'Membatasi jumlah request per interval untuk mencegah DDoS dan abuse.',
                    ],
                    [
                        'label' => 'CORS Policy',
                        'icon' => 'globe-alt',
                        'icon_color' => 'text-violet-600 dark:text-violet-400',
                        'icon_bg' => 'bg-violet-100 dark:bg-violet-900/40',
                        'active' => true,
                        'badge_label' =>
                            'Origins: ' .
                            (strlen($corsOrigins) > 30 ? substr($corsOrigins, 0, 30) . '…' : $corsOrigins),
                        'desc' =>
                            'Mengontrol domain yang boleh akses API dari browser. Konfigurasi via panel konfigurasi.',
                    ],
                    [
                        'label' => 'Input Size Limit',
                        'icon' => 'archive-box-x-mark',
                        'icon_color' => 'text-orange-600 dark:text-orange-400',
                        'icon_bg' => 'bg-orange-100 dark:bg-orange-900/40',
                        'active' => $inputSizeEnabled,
                        'badge_label' => $inputSizeEnabled
                            ? "Auth {$authKb}KB · SIMRS {$simrsKb}KB · WA {$waKb}KB · TTE {$tteKb}KB"
                            : 'Nonaktif',
                        'desc' => 'Membatasi ukuran body request per endpoint untuk mencegah oversized payload attack.',
                    ],
                    [
                        'label' => 'Anomaly Detection',
                        'icon' => 'eye',
                        'icon_color' => 'text-red-600 dark:text-red-400',
                        'icon_bg' => 'bg-red-100 dark:bg-red-900/40',
                        'active' => $anomalyEnabled,
                        'badge_label' => $anomalyEnabled
                            ? "Window {$anomalyWindow}min · Error >{$anomalyErrorRate}% · Vol >{$anomalyHighVolume}"
                            : 'Nonaktif',
                        'desc' => 'Scheduler otomatis deteksi anomali traffic dan catat ke log keamanan.',
                    ],
                    [
                        'label' => 'Bearer Token Auth',
                        'icon' => 'key',
                        'icon_color' => 'text-green-600 dark:text-green-400',
                        'icon_bg' => 'bg-green-100 dark:bg-green-900/40',
                        'active' => true,
                        'badge_label' => 'SHA-256 + Scope-based',
                        'desc' =>
                            'Token di-hash SHA-256, tidak pernah disimpan plaintext. Scope membatasi akses per endpoint.',
                    ],
                ];
            @endphp
            @foreach ($protections as $p)
                <div
                    class="flex gap-3.5 rounded-xl border p-4 transition-shadow hover:shadow-md
                    {{ $p['active']
                        ? 'bg-white dark:bg-primary-dark-800 border-zinc-200/70 dark:border-primary-dark-700/60'
                        : 'bg-zinc-50 dark:bg-primary-dark-900/40 border-zinc-200 dark:border-primary-dark-700/40 opacity-60' }}">
                    {{-- Icon circle menggunakan warna unik per item --}}
                    <div class="flex items-center justify-center w-9 h-9 rounded-xl shrink-0 {{ $p['icon_bg'] }}">
                        <flux:icon :name="$p['icon']" class="w-4 h-4 {{ $p['icon_color'] }}" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-1 mb-1">
                            <span
                                class="text-xs font-semibold text-zinc-700 dark:text-primary-dark-200">{{ $p['label'] }}</span>
                            @if ($p['active'])
                                <span
                                    class="inline-flex items-center gap-1 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400 whitespace-nowrap">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>Aktif
                                </span>
                            @else
                                <span
                                    class="text-[10px] font-semibold text-zinc-400 dark:text-primary-dark-500 whitespace-nowrap">Nonaktif</span>
                            @endif
                        </div>
                        @if ($p['active'] && $p['badge_label'] !== 'Aktif')
                            <p class="text-[10px] font-mono text-zinc-500 dark:text-primary-dark-400 leading-relaxed mb-1 truncate"
                                title="{{ $p['badge_label'] }}">{{ $p['badge_label'] }}</p>
                        @endif
                        <p class="text-[10px] leading-relaxed text-zinc-400 dark:text-primary-dark-500">
                            {{ $p['desc'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </x-organisms.data-panel>

    {{-- Top IP + Log List --}}
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">

        {{-- Top IP Offender --}}
        <x-organisms.data-panel title="Top IP Tersangka">
            <div class="p-5">
                @forelse ($topIps as $i => $row)
                    <div
                        class="flex items-center justify-between py-2 {{ !$loop->last ? 'border-b border-zinc-100 dark:border-primary-dark-700/60' : '' }}">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-zinc-400 w-5 text-center">{{ $i + 1 }}</span>
                            <span
                                class="text-xs font-mono text-zinc-600 dark:text-primary-dark-300">{{ $row->ip_address }}</span>
                        </div>
                        <flux:badge color="red" size="sm">{{ $row->total }}×</flux:badge>
                    </div>
                @empty
                    <p class="text-xs text-zinc-400 text-center py-4">Tidak ada data</p>
                @endforelse
            </div>
        </x-organisms.data-panel>

        {{-- Log List ── --}}
        <x-organisms.data-panel class="lg:col-span-3" title="Log Insiden Keamanan"
            subtitle="Riwayat insiden yang terdeteksi oleh sistem">
            <x-slot:filter>
                <div class="flex flex-row items-center gap-3">
                    <flux:select wire:model.live="filterType" size="sm" class="w-44">
                        <flux:select.option value="">Semua Tipe</flux:select.option>
                        <flux:select.option value="rate_limited">Rate Limited</flux:select.option>
                        <flux:select.option value="oversized_request">Request Terlalu Besar</flux:select.option>
                        <flux:select.option value="anomaly_high_failure">Error Rate Tinggi</flux:select.option>
                        <flux:select.option value="anomaly_high_volume">Volume Anomali</flux:select.option>
                        <flux:select.option value="anomaly_brute_force">Brute Force</flux:select.option>
                    </flux:select>
                    <flux:input wire:model.live.debounce.400ms="filterIp" placeholder="Filter IP..."
                        icon="magnifying-glass" size="sm" class="w-36" />
                    <x-atoms.toggle wire:model.live="onlyActive" label="Aktif saja" id="toggle-only-active" />
                </div>
            </x-slot:filter>
            @if (ApiSecurityLog::whereNull('resolved_at')->exists())
                <x-slot:action>
                    <x-atoms.button wire:click="resolveAll" size="sm" variant="primary" icon="check-circle">
                        Selesaikan Semua
                    </x-atoms.button>
                </x-slot:action>
            @endif

            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading>Tipe</x-atoms.table-heading>
                    <x-atoms.table-heading>IP Address</x-atoms.table-heading>
                    <x-atoms.table-heading class="hidden md:table-cell">Path</x-atoms.table-heading>
                    <x-atoms.table-heading class="hidden lg:table-cell">Detail</x-atoms.table-heading>
                    <x-atoms.table-heading>Status</x-atoms.table-heading>
                    <x-atoms.table-heading>Waktu</x-atoms.table-heading>
                    <x-atoms.table-heading align="right"></x-atoms.table-heading>
                </x-slot:headings>

                @forelse ($logs as $log)
                    <x-molecules.table-row wire:key="sec-log-{{ $log->id }}">
                        <x-atoms.table-cell>
                            <flux:badge color="{{ $log->type_color }}" size="sm">{{ $log->type_label }}
                            </flux:badge>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            <code
                                class="text-xs font-mono text-zinc-600 dark:text-primary-dark-300">{{ $log->ip_address }}</code>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell class="hidden md:table-cell max-w-[160px] truncate">
                            @if ($log->method)
                                <span
                                    class="font-semibold text-zinc-600 dark:text-primary-dark-300">{{ $log->method }}</span>
                            @endif
                            {{ $log->path ? '/' . $log->path : '—' }}
                        </x-atoms.table-cell>
                        <x-atoms.table-cell
                            class="hidden lg:table-cell text-zinc-400 dark:text-primary-dark-500 max-w-[200px]">
                            @if ($log->detail)
                                @php
                                    $d = $log->detail;
                                    $snippet = match ($log->type) {
                                        'rate_limited' => "Limiter: {$d['limiter']}, Retry: {$d['retry_after']}s",
                                        'oversized_request' => 'Size: ' .
                                            round($d['content_length_bytes'] / 1024, 1) .
                                            ' KB',
                                        'anomaly_high_failure'
                                            => "Error: {$d['error_rate_pct']}% ({$d['error_count']}/{$d['total_requests']})",
                                        'anomaly_high_volume'
                                            => "Total: {$d['total_requests']} req/{$d['window_minutes']}min",
                                        'anomaly_brute_force' => "Percobaan: {$d['attempts']}×",
                                        default => '',
                                    };
                                @endphp
                                <span class="truncate block"
                                    title="{{ json_encode($d) }}">{{ $snippet }}</span>
                            @else
                                —
                            @endif
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            @if ($log->resolved_at)
                                <flux:badge color="green" size="sm" inset="top bottom">Selesai</flux:badge>
                            @else
                                <flux:badge color="red" size="sm" inset="top bottom">Aktif</flux:badge>
                            @endif
                        </x-atoms.table-cell>
                        <x-atoms.table-cell :nowrap="true" class="text-zinc-400 dark:text-primary-dark-500">
                            {{ $log->created_at->diffForHumans() }}
                        </x-atoms.table-cell>
                        <x-atoms.table-cell align="right" action>
                            @unless ($log->resolved_at)
                                <x-atoms.button wire:click="resolve('{{ $log->id }}')" size="sm"
                                    variant="primary" icon="check" />
                            @endunless
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @empty
                    <x-molecules.table-row>
                        <x-atoms.table-cell colspan="7"
                            class="py-12 text-center text-zinc-400 dark:text-primary-dark-500">
                            <flux:icon.shield-check class="w-8 h-8 mx-auto mb-2 opacity-30" />
                            Tidak ada insiden keamanan tercatat
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @endforelse
            </x-organisms.table>

            @if ($logs->hasPages())
                <x-slot:footer>
                    <div class="px-4 py-3">
                        {{ $logs->links() }}
                    </div>
                </x-slot:footer>
            @endif
        </x-organisms.data-panel>
    </div>

    {{-- Chart data ────────────────────────────────────────────────────────── --}}
    <script type="application/json" id="sec-chart-data">
        { "labels": @json($trendLabels), "series": @json($trendSeries) }
    </script>

    @pushOnce('scripts')
        <script>
            const SEC_COLORS = {
                rate_limited: {
                    border: '#f59e0b',
                    bg: 'rgba(245,158,11,0.15)'
                },
                oversized_request: {
                    border: '#f97316',
                    bg: 'rgba(249,115,22,0.15)'
                },
                anomaly_high_failure: {
                    border: '#ef4444',
                    bg: 'rgba(239,68,68,0.15)'
                },
                anomaly_high_volume: {
                    border: '#8b5cf6',
                    bg: 'rgba(139,92,246,0.15)'
                },
                anomaly_brute_force: {
                    border: '#dc2626',
                    bg: 'rgba(220,38,38,0.10)'
                },
            };
            const SEC_LABELS = {
                rate_limited: 'Rate Limited',
                oversized_request: 'Request Terlalu Besar',
                anomaly_high_failure: 'Error Rate Tinggi',
                anomaly_high_volume: 'Volume Anomali',
                anomaly_brute_force: 'Brute Force',
            };

            function initSecurityCharts() {
                const raw = document.getElementById('sec-chart-data');
                if (!raw) return;
                const data = JSON.parse(raw.textContent);
                const ctx = document.getElementById('secTrendChart');
                if (!ctx) return;

                const datasets = Object.entries(data.series).map(([type, values]) => ({
                    label: SEC_LABELS[type] ?? type,
                    data: values,
                    borderColor: SEC_COLORS[type]?.border ?? '#94a3b8',
                    backgroundColor: SEC_COLORS[type]?.bg ?? 'rgba(148,163,184,0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 3,
                }));

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: data.labels,
                        datasets
                    },
                    options: {
                        responsive: true,
                        interaction: {
                            mode: 'index',
                            intersect: false
                        },
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    boxWidth: 10,
                                    font: {
                                        size: 11
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    font: {
                                        size: 11
                                    }
                                }
                            },
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1,
                                    font: {
                                        size: 11
                                    }
                                }
                            },
                        },
                    },
                });
            }

            document.addEventListener('DOMContentLoaded', initSecurityCharts);
            document.addEventListener('livewire:updated', function() {
                const existing = Chart.getChart('secTrendChart');
                if (existing) existing.destroy();
                initSecurityCharts();
            });
        </script>
    @endPushOnce
</div>
