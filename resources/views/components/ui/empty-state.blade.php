@props([
    'icon' => 'inbox',
    'title' => 'Tidak ada data',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center py-12 text-center']) }}>
    <div class="p-4 mb-4 rounded-full bg-zinc-100 dark:bg-primary-dark-800">
        <flux:icon :name="$icon" class="size-12 text-zinc-400" />
    </div>

    <flux:heading size="lg" class="mb-2">{{ $title }}</flux:heading>

    @if ($description)
        <flux:subheading class="max-w-sm">{{ $description }}</flux:subheading>
    @endif

    @if (isset($action))
        <div class="mt-6">
            {{ $action }}
        </div>
    @endif
</div>
