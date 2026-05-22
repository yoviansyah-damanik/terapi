@props(['title', 'value', 'icon' => null, 'trend' => null, 'trendUp' => true, 'color' => 'primary'])

@php
    $colorClasses = match ($color) {
        'primary' => 'bg-primary-500 text-white',
        'secondary' => 'bg-secondary-500 text-black',
        'success' => 'bg-primary-500 text-white',
        'warning' => 'bg-secondary-600 text-white',
        'danger' => 'bg-red-500 text-white',
        'info' => 'bg-primary-300 text-primary-900',
        default => 'bg-zinc-500 text-white',
    };

    $trendColor = $trendUp ? 'text-primary-500' : 'text-red-500';
    $trendIcon = $trendUp ? 'arrow-trending-up' : 'arrow-trending-down';
@endphp

<flux:card {{ $attributes }}>
    <div class="flex items-start justify-between">
        <div class="flex-1">
            <flux:subheading>{{ $title }}</flux:subheading>
            <div class="mt-2 text-3xl font-bold text-zinc-900 dark:text-white">
                {{ $value }}
            </div>

            @if ($trend)
                <div class="flex items-center gap-1 mt-2 {{ $trendColor }}">
                    <flux:icon :name="$trendIcon" class="size-4" />
                    <span class="text-sm font-medium">{{ $trend }}</span>
                </div>
            @endif
        </div>

        @if ($icon)
            <div class="p-3 rounded-lg {{ $colorClasses }}">
                <flux:icon :name="$icon" class="size-6" />
            </div>
        @endif
    </div>

    @if (isset($footer))
        <div class="pt-3 mt-4 border-t border-zinc-200 dark:border-primary-dark-700">
            {{ $footer }}
        </div>
    @endif
</flux:card>
