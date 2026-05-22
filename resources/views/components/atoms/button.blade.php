@props([
    'variant' => 'primary', // primary, secondary, danger, ghost, outline
    'color' => 'primary', // primary, green, red, amber, zinc
    'size' => 'md', // sm, md, lg
    'icon' => null, // optional heroicon name
    'type' => 'button',
    'as' => 'button', // button or 'a' for links
    'href' => null,
    'navigate' => true, // true to enable wire:navigate
    'tooltip' => null, // teks tooltip menggunakan flux:tooltip
])

@php
    $baseClass =
        'inline-flex items-center justify-center font-medium rounded-lg transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-primary-dark-900 disabled:opacity-50 disabled:pointer-events-none';

    $sizeClass = match ($size) {
        'xs' => 'px-2 py-1 text-[10px] gap-1',
        'sm' => 'px-3 py-1.5 text-xs gap-1.5',
        'md' => 'px-4 py-2 text-sm gap-2',
        'lg' => 'px-5 py-2.5 text-base gap-2',
        default => 'px-4 py-2 text-sm gap-2',
    };

    $primaryClass = match ($color) {
        'green',
        'emerald'
            => 'bg-emerald-600 text-white border border-transparent shadow-sm hover:bg-emerald-700 focus:ring-emerald-500 dark:bg-emerald-500 dark:hover:bg-emerald-600 dark:focus:ring-emerald-400',
        'red'
            => 'bg-red-600 text-white border border-transparent shadow-sm hover:bg-red-700 focus:ring-red-500 dark:bg-red-500 dark:hover:bg-red-600 dark:focus:ring-red-400',
        'amber',
        'yellow'
            => 'bg-amber-600 text-white border border-transparent shadow-sm hover:bg-amber-700 focus:ring-amber-500 dark:bg-amber-500 dark:hover:bg-amber-600 dark:focus:ring-amber-400',
        'zinc',
        'gray'
            => 'bg-zinc-600 text-white border border-transparent shadow-sm hover:bg-zinc-700 focus:ring-zinc-500 dark:bg-zinc-500 dark:hover:bg-zinc-600 dark:focus:ring-zinc-400',
        default
            => 'bg-primary-600 text-white border border-transparent shadow-sm hover:bg-primary-700 focus:ring-primary-500 dark:bg-primary-500 dark:hover:bg-primary-600 dark:focus:ring-primary-400',
    };

    $variantClass = match ($variant) {
        'primary' => $primaryClass,
        'secondary'
            => 'bg-white text-zinc-700 border border-zinc-300 shadow-sm hover:bg-zinc-50 focus:ring-primary-500 dark:bg-primary-dark-800 dark:text-primary-dark-200 dark:border-primary-dark-600 dark:hover:bg-primary-dark-700',
        'danger'
            => 'bg-red-600 text-white border border-transparent shadow-sm hover:bg-red-700 focus:ring-red-500 dark:bg-red-500 dark:hover:bg-red-600 dark:focus:ring-red-400',
        'ghost'
            => 'bg-transparent text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100 focus:ring-zinc-500 dark:text-primary-dark-300 dark:hover:text-white dark:hover:bg-primary-dark-700',
        'outline'
            => 'bg-transparent border border-zinc-300 text-zinc-700 hover:bg-zinc-50 focus:ring-zinc-500 dark:border-primary-dark-600 dark:text-primary-dark-300 dark:hover:bg-primary-dark-800',
        default => $primaryClass,
    };

    $iconSize = match ($size) {
        'xs' => 'size-3',
        'sm' => 'size-3.5',
        'md' => 'size-4',
        'lg' => 'size-5',
        default => 'size-4',
    };

    $isLink = $as === 'a' || $href;

    $wireTarget = null;
    foreach ($attributes->getAttributes() as $key => $value) {
        if (str_starts_with($key, 'wire:click')) {
            $wireTarget = $value;
            break;
        }
    }
    if (!$wireTarget && $attributes->has('wire:submit')) {
        $wireTarget = $attributes->get('wire:submit');
    }
    if ($attributes->has('wire:target')) {
        $wireTarget = $attributes->get('wire:target');
    }
@endphp

{{--
    flux:tooltip harus menjadi satu blok utuh — tidak boleh split open/close tag
    di dalam @if terpisah karena Blade mengkompilasi component secara statis
    sehingga konten di antara tag menjadi slot dan tidak dirender tanpa tooltip.
--}}
@if ($tooltip)
    <flux:tooltip content="{{ $tooltip }}">
        @if ($isLink)
            <a href="{{ $href }}" {{ $navigate ? 'wire:navigate' : '' }}
                {{ $attributes->merge(['class' => "{$baseClass} {$sizeClass} {$variantClass}"]) }}>
                @if ($icon)
                    <flux:icon name="{{ $icon }}" variant="outline" class="{{ $iconSize }}" />
                @endif
                {{ $slot }}
            </a>
        @else
            <button type="{{ $type }}"
                {{ $attributes->merge(['class' => "{$baseClass} {$sizeClass} {$variantClass}"]) }}
                wire:loading.attr="disabled" @if ($wireTarget && !str_starts_with($wireTarget, '$')) wire:target="{{ $wireTarget }}" @endif>
                <flux:icon.loading wire:loading class="{{ $iconSize }}" />
                @if ($icon)
                    <flux:icon wire:loading.remove name="{{ $icon }}" variant="outline"
                        class="{{ $iconSize }}" />
                @endif
                {{ $slot }}
            </button>
        @endif
    </flux:tooltip>
@else
    @if ($isLink)
        <a href="{{ $href }}" {{ $navigate ? 'wire:navigate' : '' }}
            {{ $attributes->merge(['class' => "{$baseClass} {$sizeClass} {$variantClass}"]) }}>
            @if ($icon)
                <flux:icon name="{{ $icon }}" variant="outline" class="{{ $iconSize }}" />
            @endif
            {{ $slot }}
        </a>
    @else
        <button type="{{ $type }}"
            {{ $attributes->merge(['class' => "{$baseClass} {$sizeClass} {$variantClass}"]) }}
            wire:loading.attr="disabled" @if ($wireTarget && !str_starts_with($wireTarget, '$')) wire:target="{{ $wireTarget }}" @endif>
            <flux:icon.loading wire:loading class="{{ $iconSize }}" />
            @if ($icon)
                <flux:icon wire:loading.remove name="{{ $icon }}" variant="outline"
                    class="{{ $iconSize }}" />
            @endif
            {{ $slot }}
        </button>
    @endif
@endif
