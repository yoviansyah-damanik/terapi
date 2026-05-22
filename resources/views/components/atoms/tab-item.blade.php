@props([
    'active' => false,
    'icon' => null,
])

<button
    {{ $attributes->merge([
        'class' => implode(' ', [
            'px-4 py-2.5 text-sm font-medium border-b-2 transition-colors whitespace-nowrap',
            $active
                ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                : 'border-transparent text-zinc-500 dark:text-primary-dark-400 hover:text-zinc-700 dark:hover:text-primary-dark-200 hover:border-zinc-300 dark:hover:border-primary-dark-600',
            $icon ? 'flex items-center gap-1' : '',
        ]),
    ]) }}>
    @if ($icon)
        <flux:icon name="{{ $icon }}" class="size-4 mr-1.5 shrink-0" />
    @endif
    {{ $slot }}
</button>
