@props(['title', 'subtitle' => null, 'backUrl' => null])

<div {{ $attributes->merge(['class' => 'mb-6']) }}>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            @if ($backUrl)
                <flux:button href="{{ $backUrl }}" icon="arrow-left" variant="ghost" size="sm" wire:navigate />
            @endif

            <div>
                <h1
                    class="text-3xl font-extrabold tracking-tight text-transparent bg-clip-text bg-gradient-to-br from-primary-600 to-primary-dark-900 dark:from-primary-400 dark:to-primary-dark-500">
                    {{ $title }}
                </h1>

                @if ($subtitle)
                    <p class="mt-1 text-sm font-medium text-zinc-500 dark:text-primary-dark-400">
                        {{ $subtitle }}
                    </p>
                @endif
            </div>
        </div>

        @if (isset($actions))
            <div class="flex items-center gap-2">
                {{ $actions }}
            </div>
        @endif
    </div>

    @if (isset($breadcrumbs))
        <flux:breadcrumbs class="mt-2">
            {{ $breadcrumbs }}
        </flux:breadcrumbs>
    @endif
</div>
