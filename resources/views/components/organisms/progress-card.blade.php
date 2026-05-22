@props([
    'title',
    'percentage',
    'valuePrimary',
    'valuePrimarySuffix' => '',
    'valueSecondary' => '',
    'color' => 'default', // blue, sky, teal, amber, violet, emerald, default
])

@php
    $colorMap = [
        'blue' => [
            'text' => 'text-blue-700 dark:text-blue-300',
            'bar' => 'bg-blue-400 dark:bg-blue-500',
        ],
        'sky' => [
            'text' => 'text-sky-700 dark:text-sky-300',
            'bar' => 'bg-sky-400 dark:bg-sky-500',
        ],
        'teal' => [
            'text' => 'text-teal-700 dark:text-teal-300',
            'bar' => 'bg-teal-400 dark:bg-teal-500',
        ],
        'amber' => [
            'text' => 'text-amber-700 dark:text-amber-300',
            'bar' => 'bg-amber-400 dark:bg-amber-500',
        ],
        'violet' => [
            'text' => 'text-violet-700 dark:text-violet-300',
            'bar' => 'bg-violet-400 dark:bg-violet-500',
        ],
        'emerald' => [
            'text' => 'text-emerald-700 dark:text-emerald-300',
            'bar' => 'bg-emerald-400 dark:bg-emerald-500',
        ],
        'default' => [
            'text' => 'text-zinc-700 dark:text-zinc-300',
            'bar' => 'bg-zinc-400 dark:bg-zinc-500',
        ],
    ];

    $c = $colorMap[$color] ?? $colorMap['default'];
    $tag = $attributes->has('href') ? 'a' : 'div';
@endphp

<{{ $tag }} {{ $attributes->merge(['class' => 'group flex flex-col gap-3 p-4 bg-white dark:bg-primary-dark-800 border border-zinc-200/80 dark:border-primary-dark-700/60 rounded-2xl shadow-sm hover:shadow-md transition-all duration-150 relative overflow-hidden']) }}>
    
    <div class="flex items-start justify-between gap-2">
        <span class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-200 leading-tight">
            {{ $title }}
        </span>
        <span class="text-xs font-bold tabular-nums {{ $c['text'] }} shrink-0">
            {{ $percentage }}%
        </span>
    </div>

    <div class="h-1.5 rounded-full bg-zinc-100 dark:bg-primary-dark-700 overflow-hidden relative">
        <div class="h-full rounded-full {{ $c['bar'] }} transition-all duration-700 ease-in-out" style="width: {{ $percentage }}%"></div>
    </div>

    <div class="flex items-center justify-between text-xs text-zinc-400 dark:text-primary-dark-500">
        <span>
            <span class="font-semibold {{ $c['text'] }}">{{ $valuePrimary }}</span>
            @if((string)$valuePrimarySuffix !== '')
                {{ $valuePrimarySuffix }}
            @endif
        </span>
        @if((string)$valueSecondary !== '')
            <span>{{ $valueSecondary }}</span>
        @endif
    </div>

</{{ $tag }}>
