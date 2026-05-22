@props([
    'title' => null,
    'subtitle' => null,
    'padding' => true,
    'footer' => null,
    'variant' => 'default', // default, primary, success, danger, warning
])

@php
    $titleColor = match ($variant) {
        'primary' => 'text-primary-600 dark:text-primary-400',
        'success' => 'text-emerald-600 dark:text-emerald-400',
        'danger' => 'text-red-500 dark:text-red-400',
        'warning' => 'text-amber-500 dark:text-amber-400',
        default => 'text-zinc-800 dark:text-primary-dark-100',
    };
@endphp

<div
    {{ $attributes->merge(['class' => 'bg-white dark:bg-primary-dark-800 rounded-2xl shadow-sm hover:shadow-md transition-shadow overflow-hidden text-zinc-900 dark:text-zinc-100']) }}>

    @if ($title || $subtitle)
        <div class="px-5 pt-5 pb-1">
            @if ($title)
                <h3 class="text-base font-semibold {{ $titleColor }}">{{ $title }}</h3>
            @endif
            @if ($subtitle)
                <p class="mt-0.5 text-xs text-zinc-500 dark:text-primary-dark-400">{{ $subtitle }}</p>
            @endif
        </div>
    @endif

    <div @class(['p-5' => $padding])>
        {{ $slot }}
    </div>

    @if ($footer)
        <div class="px-5 py-3.5 bg-zinc-50/70 dark:bg-primary-dark-900/40 flex items-center justify-end gap-2 text-sm">
            {{ $footer }}
        </div>
    @endif
</div>
