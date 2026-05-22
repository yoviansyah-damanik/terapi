@props([
    'title'    => null,  // judul panel
    'subtitle' => null,  // subtitle opsional
    'icon'     => null,  // icon opsional di samping judul (nama icon flux)
    'action'   => null,  // slot: tombol/aksi di pojok kanan header
    'filter'   => null,  // kontrol pencarian/filter (bg abu)
    'footer'   => null,  // catatan / paginasi (bg abu)
])

{{-- Urutan: header → filter → slot → footer --}}
<div
    {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-zinc-200/60 dark:bg-primary-dark-800 dark:ring-primary-dark-700/60']) }}>

    {{-- 1. Header: judul, subtitle & action --}}
    @if ($title || !empty($action))
        <div class="flex items-center justify-between gap-4 px-5 py-4 border-b border-zinc-100 dark:border-primary-dark-700/60">
            {{-- Kiri: icon + judul + subtitle --}}
            <div class="min-w-0">
                <div class="{{ $icon ? 'flex items-center gap-2' : '' }}">
                    @if ($icon)
                        <flux:icon :name="$icon" class="w-4 h-4 text-zinc-400 dark:text-primary-dark-500 shrink-0" />
                    @endif
                    @if ($title)
                        <h3 class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-300">{{ $title }}</h3>
                    @endif
                </div>
                @if ($subtitle)
                    <p class="mt-0.5 text-xs text-zinc-400 dark:text-primary-dark-500">{{ $subtitle }}</p>
                @endif
            </div>
            {{-- Kanan: action slot --}}
            @if (!empty($action))
                <div class="shrink-0 flex items-center gap-2">
                    {{ $action }}
                </div>
            @endif
        </div>
    @endif

    {{-- 2. Filter: input, select, toggle, dll --}}
    @if (!empty($filter))
        <div class="px-5 py-3.5 border-b border-zinc-100 dark:border-primary-dark-700/60 bg-zinc-50/60 dark:bg-primary-dark-900/30">
            {{ $filter }}
        </div>
    @endif

    {{-- 3. Konten utama --}}
    {{ $slot }}

    {{-- 4. Footer: paginasi / keterangan --}}
    @if (isset($footer) && $footer->isNotEmpty())
        <div class="border-t border-zinc-100 dark:border-primary-dark-700/60 bg-zinc-50 dark:bg-primary-dark-900/50">
            {{ $footer }}
        </div>
    @endif
</div>
