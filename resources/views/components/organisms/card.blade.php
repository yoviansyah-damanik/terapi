@props([
    'title' => null,
    'subtitle' => null,
    'padding' => true,
    'footer' => null,
])

<div {{ $attributes->merge(['class' => 'bg-white dark:bg-primary-dark-800 rounded-xl shadow-md overflow-hidden text-zinc-900 dark:text-zinc-100']) }}>
    
    @if($title || $subtitle)
        <div class="px-5 py-4">
            @if($title)
                <h3 class="text-base sm:text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">{{ $title }}</h3>
            @endif
            @if($subtitle)
                <p class="mt-0.5 text-sm text-zinc-500 dark:text-primary-dark-300">{{ $subtitle }}</p>
            @endif
        </div>
    @endif

    <div @class(['p-5' => $padding])>
        {{ $slot }}
    </div>

    @if($footer)
        <div class="px-5 py-4 bg-zinc-50 dark:bg-primary-dark-900/50 flex items-center justify-end gap-2">
            {{ $footer }}
        </div>
    @endif
</div>
