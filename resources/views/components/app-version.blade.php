@props([
    'class' => '',
])
@php
    use App\Helpers\ConfigurationHelper;

    $ms = round((microtime(true) - LARAVEL_START) * 1000, 2);
    [$perfBg, $perfText, $perfDot] = match (true) {
        $ms < 300 => [
            'bg-emerald-500/10 dark:bg-emerald-500/20',
            'text-emerald-600 dark:text-emerald-400',
            'bg-emerald-500',
        ],
        $ms < 700 => ['bg-amber-500/10 dark:bg-amber-500/20', 'text-amber-600 dark:text-amber-400', 'bg-amber-500'],
        default => ['bg-rose-500/10 dark:bg-rose-500/20', 'text-rose-600 dark:text-rose-400', 'bg-rose-500'],
    };

    // Client Info
    $userIp = request()->ip();
    $rawUa = request()->userAgent() ?? '';
    $agent = new \Jenssegers\Agent\Agent();
    $agent->setUserAgent($rawUa);
    $browser = $agent->browser() ?: 'Unknown';
    $browserVersion = $agent->version($browser) ?: '—';
    $platform = $agent->platform() ?: 'Unknown';
    $platformVersion = $agent->version($platform) ?: '—';
    $deviceType = $agent->isMobile()
        ? 'Mobile'
        : ($agent->isTablet()
            ? 'Tablet'
            : ($agent->isDesktop()
                ? 'Desktop'
                : 'Unknown'));
    $isRobot = $agent->isRobot();
    $robotName = $isRobot ? ($agent->robot() ?: 'Robot') : null;

    $envColor = match (app()->environment()) {
        'production'
            => 'bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400 ring-emerald-500/20',
        'staging' => 'bg-amber-500/10 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400 ring-amber-500/20',
        default => 'bg-blue-500/10 text-blue-600 dark:bg-blue-500/20 dark:text-blue-400 ring-blue-500/20',
    };
@endphp

<div @class([$class, 'z-50']) x-data="{ infoOpen: false }">
    {{-- Trigger Button (Pill Design) --}}
    <button @click="infoOpen = !infoOpen"
        class="group flex items-center gap-2 pl-1.5 pr-3 py-1.5 rounded-full bg-white/80 dark:bg-zinc-900/80 backdrop-blur-md border border-zinc-200/80 dark:border-zinc-800/80 shadow-lg shadow-zinc-200/50 dark:shadow-black/50 hover:bg-white dark:hover:bg-zinc-900 transition-all duration-300 ring-1 ring-black/5 dark:ring-white/5">

        <div class="relative flex items-center justify-center w-6 h-6 rounded-full bg-zinc-100 dark:bg-zinc-800">
            <img src="{{ Vite::image('logo-icon.png') }}"
                class="w-3.5 h-3.5 opacity-80 group-hover:opacity-100 transition-opacity" alt="logo" />
        </div>

        <div
            class="flex flex-col items-start leading-none group-hover:-translate-y-0.5 transition-transform duration-300">
            <span class="text-[10px] font-bold text-zinc-700 dark:text-zinc-200 tracking-wide">
                v.{{ ConfigurationHelper::get('app.version', config('app.version')) }}
            </span>
            <span id="page-benchmark-text" class="text-[9px] font-medium text-zinc-400 dark:text-zinc-500 mt-0.5">
                {{ $ms }}ms
            </span>
        </div>

        <div class="ml-1 flex items-center justify-center">
            <span class="relative flex h-2 w-2">
                <span
                    class="animate-ping absolute inline-flex h-full w-full rounded-full {{ $perfDot }} opacity-60"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 {{ $perfDot }}"></span>
            </span>
        </div>
    </button>

    {{-- Popover Overlay & Content --}}
    <div x-show="infoOpen" style="display: none;">
        {{-- Invisible backdrop to catch clicks --}}
        <div class="fixed inset-0 z-40" @click="infoOpen = false"></div>

        {{-- Glassmorphism Card --}}
        <div x-show="infoOpen" x-transition:enter="transition cubic-bezier(0.4, 0, 0.2, 1) duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition cubic-bezier(0.4, 0, 0.2, 1) duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 scale-95"
            class="absolute bottom-full right-0 mb-4 z-50 w-72 md:w-80 overflow-hidden rounded-2xl border border-white/20 dark:border-zinc-800 bg-white/60 dark:bg-zinc-950/60 backdrop-blur-xl shadow-2xl shadow-indigo-500/10 dark:shadow-black/60 origin-bottom-right ring-1 ring-black/5 dark:ring-white/10">

            {{-- Aesthetic Gradient Header --}}
            <div class="relative h-24 bg-gradient-to-br from-primary-600 to-indigo-600 p-5 overflow-hidden">
                <div
                    class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')] opacity-20">
                </div>

                {{-- Floating spheres for depth --}}
                <div class="absolute -top-4 -right-4 w-16 h-16 bg-white/10 rounded-full blur-md"></div>
                <div class="absolute bottom-2 left-6 w-10 h-10 bg-black/10 rounded-full blur-md"></div>

                <div class="relative flex justify-between items-start z-10">
                    <div class="flex gap-3 items-center">
                        <div class="p-1.5 bg-white/20 backdrop-blur-sm rounded-xl border border-white/30">
                            <img src="{{ Vite::image('logo-icon.png') }}" class="w-12" alt="logo" />
                        </div>
                        <div class="text-white">
                            <h3 class="font-bold tracking-wide leading-tight shadow-sm">
                                {{ ConfigurationHelper::get('app.alias_name', config('app.alias_name')) }}</h3>
                            <p class="text-xs text-indigo-100 font-medium">
                                {{ ConfigurationHelper::get('app.name', config('app.name')) }}</p>
                        </div>
                    </div>
                    <button @click="infoOpen = false"
                        class="text-white/60 hover:text-white bg-black/10 hover:bg-black/20 rounded-full p-1 transition-colors">
                        <flux:icon name="x-mark" class="w-4 h-4" />
                    </button>
                </div>
            </div>

            <div class="p-1">
                {{-- System Stats Grid --}}
                <div class="grid grid-cols-2 gap-1 p-2">
                    <div
                        class="bg-white/50 dark:bg-zinc-900/50 rounded-xl p-3 border border-white/50 dark:border-zinc-800/50">
                        <p
                            class="text-[10px] text-zinc-500 dark:text-zinc-400 font-medium tracking-wide uppercase mb-1">
                            Environment</p>
                        <span
                            class="inline-flex text-xs font-semibold px-2 py-0.5 rounded-md ring-1 {{ $envColor }}">
                            {{ app()->environment() }}
                        </span>
                    </div>
                    <div
                        class="bg-white/50 dark:bg-zinc-900/50 rounded-xl p-3 border border-white/50 dark:border-zinc-800/50">
                        <p
                            class="text-[10px] text-zinc-500 dark:text-zinc-400 font-medium tracking-wide uppercase mb-1">
                            Response</p>
                        <span
                            class="inline-flex text-xs font-mono font-semibold px-2 py-0.5 rounded-md {{ $perfBg }} {{ $perfText }}">
                            {{ $ms }}ms
                        </span>
                    </div>
                </div>

                {{-- Stack Info --}}
                <div class="px-3 py-2">
                    <div
                        class="bg-zinc-100/50 dark:bg-zinc-900/30 rounded-lg p-3 space-y-2 border border-zinc-200/50 dark:border-zinc-800/50">
                        @foreach ([['key' => 'Versi Aplikasi', 'value' => 'v' . ConfigurationHelper::get('app.version', config('app.version'))], ['key' => 'PHP Runtime', 'value' => PHP_VERSION], ['key' => 'Laravel Framework', 'value' => app()->version()]] as $item)
                            <div class="flex justify-between items-center whitespace-nowrap">
                                <span
                                    class="text-[10px] sm:text-xs text-zinc-500 dark:text-zinc-400">{{ $item['key'] }}</span>
                                <span
                                    class="text-[10px] sm:text-xs font-medium text-zinc-700 dark:text-zinc-300 font-mono">{{ $item['value'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="h-px bg-zinc-200/60 dark:bg-zinc-800/60 mx-4 my-1"></div>

                {{-- Client Info --}}
                <div class="px-3 py-2 mb-2">
                    <h4
                        class="text-[10px] font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-widest pl-1 mb-2">
                        Device Info</h4>
                    <div class="space-y-1.5 px-1">
                        @foreach ([['icon' => 'globe-alt', 'value' => $userIp, 'mono' => true], ['icon' => 'computer-desktop', 'value' => ($robotName ? '🤖 ' . $robotName : $deviceType) . ' / ' . $platform . ' ' . $platformVersion, 'mono' => false], ['icon' => 'window', 'value' => $browser . ' ' . $browserVersion, 'mono' => false]] as $item)
                            <div class="flex items-center gap-2">
                                <flux:icon name="{{ $item['icon'] }}" class="w-3.5 h-3.5 text-zinc-400" />
                                <span
                                    class="text-xs text-zinc-600 dark:text-zinc-400 truncate {{ $item['mono'] ? 'font-mono' : '' }}">
                                    {{ $item['value'] }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>

@push('scripts')
    <script>
        (() => {
            const getEl = () => document.getElementById('page-benchmark-text');
            const getBenchmark = () => document.getElementById('page-benchmark');

            /** Format angka ms menjadi string yang ringkas */
            const fmt = ms => ms < 1000 ? `${Math.round(ms)}ms` : `${(ms / 1000).toFixed(2)}s`;

            /** Perbarui tampilan teks benchmark di footer */
            const update = (serverMs, totalMs = null) => {
                const el = getEl();
                if (!el) return;
                el.textContent = totalMs !== null ?
                    `PHP ${fmt(serverMs)} · Load ${fmt(totalMs)}` :
                    `PHP ${fmt(serverMs)}`;
            };

            // Full page load — tunggu event 'load' agar loadEventEnd sudah terisi
            window.addEventListener('load', () => {
                const bench = getBenchmark();
                if (!bench) return;
                const serverMs = parseFloat(bench.dataset.serverTime) || 0;
                const nav = performance.getEntriesByType('navigation')[0];
                const totalMs = nav ? nav.loadEventEnd - nav.startTime : null;
                update(serverMs, totalMs);
            });

            // wire:navigate — ukur dari event start hingga halaman baru selesai di-swap
            let navStart = 0;
            document.addEventListener('livewire:navigate', () => {
                navStart = performance.now();
            });
            document.addEventListener('livewire:navigated', () => {
                if (!navStart) return;
                const navMs = performance.now() - navStart;
                navStart = 0;

                // Beri waktu sebentar agar DOM baru ter-render sebelum membaca data-server-time
                requestAnimationFrame(() => {
                    const bench = getBenchmark();
                    if (!bench) return;
                    const serverMs = parseFloat(bench.dataset.serverTime) || 0;
                    update(serverMs, navMs);
                });
            });
        })();
    </script>
@endpush
