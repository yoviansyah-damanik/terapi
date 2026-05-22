@props([
    'title',
    'description' => null,
    'icon' => null,
    'href' => '#',
    'color' => 'default', // default/zinc, blue, emerald, violet
])

@php
    $hoverMap = [
        'default' => 'hover:bg-zinc-50 dark:hover:bg-primary-dark-700/30 ring-zinc-200/50',
        'zinc'    => 'hover:bg-zinc-50 dark:hover:bg-primary-dark-700/30 ring-zinc-200/50',
        'blue'    => 'hover:bg-blue-50 dark:hover:bg-blue-900/10 ring-blue-200/50',
        'emerald' => 'hover:bg-emerald-50 dark:hover:bg-emerald-900/10 ring-emerald-200/50',
        'violet'  => 'hover:bg-violet-50 dark:hover:bg-violet-900/10 ring-violet-200/50',
    ];

    $iconBoxMap = [
        'default' => 'text-zinc-600 dark:text-primary-dark-300 bg-zinc-100 dark:bg-primary-dark-700',
        'zinc'    => 'text-zinc-600 dark:text-primary-dark-300 bg-zinc-100 dark:bg-primary-dark-700',
        'blue'    => 'text-blue-600 dark:text-blue-400 bg-blue-100 dark:bg-blue-900/30',
        'emerald' => 'text-emerald-600 dark:text-emerald-400 bg-emerald-100 dark:bg-emerald-900/30',
        'violet'  => 'text-violet-600 dark:text-violet-400 bg-violet-100 dark:bg-violet-900/30',
    ];

    $hoverClass = $hoverMap[$color] ?? $hoverMap['default'];
    $iconBoxClass = $iconBoxMap[$color] ?? $iconBoxMap['default'];
@endphp

<a href="{{ $href }}" {{ $attributes->merge(['class' => "flex items-start gap-4 rounded-2xl p-5 bg-white dark:bg-primary-dark-800 shadow-sm hover:shadow-md transition-all hover:-translate-y-0.5 {$hoverClass}"]) }}>
    @if($icon)
        <div class="flex size-10 shrink-0 items-center justify-center rounded-xl {{ $iconBoxClass }}">
            <flux:icon name="{{ $icon }}" variant="outline" class="size-5" />
        </div>
    @endif
    <div class="min-w-0 flex-1">
        <p class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">
            {{ $title }}
        </p>
        @if($description)
            <p class="mt-0.5 text-xs leading-snug text-zinc-500 dark:text-primary-dark-400">
                {{ $description }}
            </p>
        @endif
    </div>
    <flux:icon.arrow-right class="mt-0.5 size-4 shrink-0 text-zinc-300 dark:text-primary-dark-600" />
</a>
