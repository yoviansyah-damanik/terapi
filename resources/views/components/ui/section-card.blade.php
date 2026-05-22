@props([
    'title' => null,
    'subtitle' => null,
    'padding' => true,
])

<div {{ $attributes->class(['overflow-hidden bg-white rounded-lg shadow dark:bg-primary-dark-800']) }}>
    @if ($title || $subtitle || isset($header))
        <div
            class="px-6 py-4 border-b border-zinc-200 dark:border-primary-dark-700 bg-zinc-50 dark:bg-primary-dark-900/50">
            @isset($header)
                {{ $header }}
            @else
                @if ($title)
                    <h3 class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-300">{{ $title }}</h3>
                @endif
                @if ($subtitle)
                    <p class="text-xs text-zinc-500 dark:text-primary-dark-400 mt-0.5">{{ $subtitle }}</p>
                @endif
            @endisset
        </div>
    @endif

    <div @class(['p-6' => $padding, 'p-0' => !$padding])>
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="px-6 py-4 border-t border-zinc-200 dark:border-primary-dark-700 bg-zinc-50 dark:bg-primary-dark-900/30">
            {{ $footer }}
        </div>
    @endisset
</div>
