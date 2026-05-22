@props([
    'title',
    'value',
    'icon' => null,
    'subtitle' => null,
    'color' => 'default', // default/zinc, emerald, blue, violet, amber, red, sky, indigo
])

@php
    $styles = [
        'zinc' => [
            'value' => 'text-zinc-800 dark:text-zinc-100',
            'iconWrap' => 'bg-zinc-100 text-zinc-600 dark:bg-primary-dark-700 dark:text-zinc-300',
            'hover' => 'hover:border-zinc-300 dark:hover:border-primary-dark-600'
        ],
        'emerald' => [
            'value' => 'text-emerald-700 dark:text-emerald-400',
            'iconWrap' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400',
            'hover' => 'hover:border-emerald-200 dark:hover:border-emerald-800/80'
        ],
        'blue' => [
            'value' => 'text-blue-700 dark:text-blue-400',
            'iconWrap' => 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400',
            'hover' => 'hover:border-blue-200 dark:hover:border-blue-800/80'
        ],
        'violet' => [
            'value' => 'text-violet-700 dark:text-violet-400',
            'iconWrap' => 'bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-400',
            'hover' => 'hover:border-violet-200 dark:hover:border-violet-800/80'
        ],
        'amber' => [
            'value' => 'text-amber-700 dark:text-amber-400',
            'iconWrap' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400',
            'hover' => 'hover:border-amber-200 dark:hover:border-amber-800/80'
        ],
        'red' => [
            'value' => 'text-red-700 dark:text-red-400',
            'iconWrap' => 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400',
            'hover' => 'hover:border-red-200 dark:hover:border-red-800/80'
        ],
        'sky' => [
            'value' => 'text-sky-700 dark:text-sky-400',
            'iconWrap' => 'bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-400',
            'hover' => 'hover:border-sky-200 dark:hover:border-sky-800/80'
        ],
        'indigo' => [
            'value' => 'text-indigo-700 dark:text-indigo-400',
            'iconWrap' => 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400',
            'hover' => 'hover:border-indigo-200 dark:hover:border-indigo-800/80'
        ],
    ];

    $colorKey = $color === 'default' ? 'zinc' : $color;
    $theme = $styles[$colorKey] ?? $styles['zinc'];
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

<{{ $tag }}{!! $type !!}
    {{ $attributes->merge(['class' => 'group flex flex-col gap-2 rounded-2xl border border-zinc-100 bg-white p-5 shadow-[0_2px_8px_-2px_rgba(0,0,0,0.05)] transition-all duration-300 hover:-translate-y-1 hover:shadow-md dark:border-primary-dark-700/80 dark:bg-primary-dark-800 ' . $theme['hover'] . ($tag !== 'div' ? ' w-full text-left focus:outline-none focus:ring-2 focus:ring-primary-500/50' : '')]) }}>
    
    <div class="flex items-center justify-between">
        <span class="text-xs font-semibold tracking-wide text-zinc-500 uppercase dark:text-primary-dark-400">{{ $title }}</span>
        @if ($icon)
            <div class="{{ $theme['iconWrap'] }} flex h-10 w-10 shrink-0 flex-col items-center justify-center rounded-xl transition-transform duration-300 group-hover:scale-110">
                <flux:icon name="{{ $icon }}" variant="outline" class="size-5" />
            </div>
        @endif
    </div>
    
    <div class="mt-1">
        <span class="block text-2xl font-black tracking-tight leading-none {{ $theme['value'] }}">
            {{ $value }}
        </span>
        @if ($subtitle)
            <p class="mt-2 text-xs font-medium text-zinc-400 dark:text-primary-dark-500">{{ $subtitle }}</p>
        @endif
    </div>
    
</{{ $tag }}>
