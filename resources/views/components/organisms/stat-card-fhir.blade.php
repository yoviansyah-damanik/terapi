@props(['title', 'value' => 0, 'total' => null, 'percentage' => null, 'supported' => false])

@php
    $baseClasses =
        'group flex flex-col relative h-full rounded-2xl border bg-white dark:bg-primary-dark-900/50 backdrop-blur-sm shadow-[0_2px_8px_-2px_rgba(0,0,0,0.05)] transition-all duration-300 overflow-hidden text-left focus:outline-none focus:ring-2 focus:ring-primary-500/50 hover:-translate-y-1 hover:shadow-lg ';

    if ($supported) {
        $baseClasses .=
            'border-zinc-200/70 hover:border-sky-300 dark:border-primary-dark-700/60 dark:hover:border-sky-700 hover:shadow-sky-900/10 dark:hover:shadow-sky-900/20';
        $titleColor = 'text-zinc-800 dark:text-primary-dark-100 group-hover:text-sky-700 dark:group-hover:text-sky-400';
        $valueColor =
            $value > 0
                ? 'text-primary-600 dark:text-primary-400'
                : 'text-zinc-300 dark:text-primary-dark-600 font-light';
    } else {
        $baseClasses .=
            'border-zinc-200/70 hover:border-zinc-300 dark:border-primary-dark-700/60 dark:hover:border-primary-dark-600';
        $titleColor =
            'text-zinc-800 dark:text-primary-dark-100 group-hover:text-zinc-700 dark:group-hover:text-primary-dark-200';
        $valueColor =
            $value > 0 ? 'text-zinc-600 dark:text-zinc-400' : 'text-zinc-300 dark:text-primary-dark-600 font-light';
    }

    $waveId = isset($percentage) ? 'wave-' . md5($title . uniqid()) : null;
@endphp

@php
    if ($attributes->has('href')) {
        $tag = 'a';
        $type = '';
    } elseif ($attributes->has('wire:click') || $attributes->has('@click')) {
        $tag = 'button';
        $type = ' type="button"';
    } else {
        $tag = 'div';
        $type = '';
    }
@endphp

<{{ $tag }}{!! $type !!} {{ $attributes->merge(['class' => $baseClasses]) }}>

    <div class="relative z-10 flex-1">
        {{-- Top section: title + value —  wave canvas fill --}}
        <div class="relative overflow-hidden p-5 pb-4">
            @if (isset($percentage))
                {{-- Canvas wave fill --}}
                <canvas id="{{ $waveId }}" class="absolute inset-0 w-full h-full"
                    data-percentage="{{ $percentage }}"
                    data-dark="{{ request()->cookie('dark_mode') === '1' ? '1' : '0' }}" aria-hidden="true">
                </canvas>
            @endif

            {{-- Title row --}}
            <div class="relative z-10 flex items-start justify-between">
                <h3 class="text-sm font-bold transition-colors {{ $titleColor }}">
                    {{ $title }}
                </h3>
                @if ($supported)
                    <div class="flex-shrink-0 ml-2 text-sky-500 dark:text-sky-400"
                        title="Terdata di Platform Satu Sehat">
                        <flux:icon name="check-badge" variant="solid"
                            class="w-5 h-5 transition-transform duration-300 opacity-90 group-hover:scale-110 group-hover:rotate-6" />
                    </div>
                @endif
            </div>

            {{-- Value row --}}
            <div class="relative z-10 mt-3">
                <div class="flex items-baseline gap-2">
                    <span class="block leading-none tracking-tight font-black text-3xl {{ $valueColor }}">
                        {{ $value > 0 ? number_format($value) : '-' }}
                    </span>
                    @if (isset($total))
                        <span class="text-sm font-medium text-zinc-400 dark:text-primary-dark-500">
                            / {{ number_format($total) }}
                        </span>
                        @if (isset($percentage))
                            <span
                                class="px-1.5 py-0.5 ml-1 text-xs font-bold rounded-md bg-white/70 dark:bg-primary-dark-800/80 text-zinc-500 dark:text-primary-dark-400 backdrop-blur-sm">
                                {{ $percentage }}%
                            </span>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        {{-- Sub-items --}}
        @if ($slot->isNotEmpty())
            <div class="px-5 pb-5 pt-3 border-t space-y-2.5 border-zinc-100 dark:border-primary-dark-800/60">
                {{ $slot }}
            </div>
        @endif
    </div>
    </{{ $tag }}>

    @if (isset($percentage) && $waveId)
        <script>
            (function() {
                function initWave(canvasEl) {
                    if (!canvasEl || canvasEl._waveInit) return;
                    canvasEl._waveInit = true;

                    const ctx = canvasEl.getContext('2d');
                    const pct = parseFloat(canvasEl.dataset.percentage) / 100;
                    const isDark = () => document.documentElement.classList.contains('dark');
                    let t = Math.random() * 100; // random phase start
                    let raf;

                    // 2 gelombang: 1 dominan + 1 riak kecil dengan fase random
                    const w1 = { freq: 1.0 + Math.random() * 0.5, speed: 0.5 + Math.random() * 0.4, phase: Math.random() * Math.PI * 2 };
                    const w2 = { freq: 2.5 + Math.random() * 1.0, speed: 1.0 + Math.random() * 0.6, phase: Math.random() * Math.PI * 2 };

                    function resize() {
                        canvasEl.width = canvasEl.offsetWidth;
                        canvasEl.height = canvasEl.offsetHeight;
                    }

                    function draw() {
                        const W = canvasEl.width;
                        const H = canvasEl.height;
                        if (!W || !H) {
                            raf = requestAnimationFrame(draw);
                            return;
                        }

                        ctx.clearRect(0, 0, W, H);

                        const fillH   = H * pct;
                        const waveAmp = Math.min(fillH * 0.28, 20);
                        const fillTop = H - fillH;                        // batas atas fill (Y canvas)
                        const center  = fillTop - waveAmp;                // center ombak, puncak menyentuh fillTop saat 100%

                        // --- Clip ke area fill agar ombak tidak melewati batas ---
                        ctx.save();
                        ctx.beginPath();
                        ctx.rect(0, fillTop, W, fillH);
                        ctx.clip();

                        // --- Gambar path gelombang ---
                        ctx.beginPath();
                        ctx.moveTo(0, H);

                        for (let x = 0; x <= W; x++) {
                            const nx = x / W;
                            const y  = 0.72 * Math.sin(nx * Math.PI * 2 * w1.freq + t * w1.speed + w1.phase)
                                     + 0.28 * Math.sin(nx * Math.PI * 2 * w2.freq + t * w2.speed + w2.phase);
                            ctx.lineTo(x, center + waveAmp + y * waveAmp);
                        }

                        ctx.lineTo(W, H);
                        ctx.lineTo(0, H);
                        ctx.closePath();

                        // --- Gradient fill ---
                        const grad = ctx.createLinearGradient(0, fillTop, 0, H);
                        if (isDark()) {
                            grad.addColorStop(0, 'rgba(14, 165, 233, 0.22)');
                            grad.addColorStop(1, 'rgba(3, 105, 161, 0.35)');
                        } else {
                            grad.addColorStop(0, 'rgba(186, 230, 253, 0.65)');
                            grad.addColorStop(1, 'rgba(125, 211, 252, 0.45)');
                        }
                        ctx.fillStyle = grad;
                        ctx.fill();
                        ctx.restore();   // hapus clip region

                        t += 0.018;
                        raf = requestAnimationFrame(draw);
                    }

                    resize();
                    new ResizeObserver(resize).observe(canvasEl);
                    draw();
                }

                function boot() {
                    const el = document.getElementById('{{ $waveId }}');
                    if (el) initWave(el);
                }

                document.readyState === 'loading' ?
                    document.addEventListener('DOMContentLoaded', boot) :
                    boot();
                document.addEventListener('livewire:navigated', boot);
            })();
        </script>
    @endif
