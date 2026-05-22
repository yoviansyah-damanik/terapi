@props([
    'name' => null,
    'title' => null,
    'description' => null,
    'maxWidth' => 'md',
])

@php
    $maxWidthClass = match ($maxWidth) {
        'sm' => 'max-w-sm',
        'md' => 'max-w-md',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
        '2xl' => 'max-w-2xl',
        '3xl' => 'max-w-3xl',
        '4xl' => 'max-w-4xl',
        '5xl' => 'max-w-5xl',
        '6xl' => 'max-w-6xl',
        '7xl' => 'max-w-7xl',
        'full' => 'max-w-full',
        default => 'max-w-md',
    };
@endphp

<flux:modal name="{{ $name }}" {{ $attributes->merge(['class' => 'w-full p-0! ' . $maxWidthClass]) }}>
    <div class="flex flex-col max-h-[90vh] bg-white dark:bg-zinc-900 rounded-xl overflow-hidden">
        {{-- Header --}}
        @if ($title)
            <div
                class="flex items-center justify-between flex-shrink-0 px-6 py-4 border-b border-zinc-200 dark:border-zinc-800">
                <div>
                    <h3 class="text-lg font-semibold">{{ $title }}</h3>
                    @if (!empty($description))
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $description }}</p>
                    @endif
                </div>
            </div>
        @endif

        {{-- Body --}}
        <div
            class="flex-1 px-6 py-4 overflow-y-auto scrollbar-thin scrollbar-thumb-zinc-200 dark:scrollbar-thumb-zinc-700">
            {{ $slot }}
        </div>

        {{-- Footer --}}
        @isset($footer)
            <div
                class="flex-shrink-0 px-6 py-4 border-t bg-zinc-50 dark:bg-zinc-800/20 border-zinc-200 dark:border-zinc-800">
                {{ $footer }}
            </div>
        @endisset
    </div>
</flux:modal>
