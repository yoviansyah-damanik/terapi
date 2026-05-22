@props([
    'href' => '#',
    'icon' => null,
    'active' => false,
    'badge' => null,
])

<flux:sidebar.item :href="$href" :icon="$icon" :current="$active" wire:navigate
    {{ $attributes }}>
    {{ $slot }}
    @if ($badge)
        <flux:badge size="sm" color="red" class="ml-auto">{{ $badge }}</flux:badge>
    @endif
</flux:sidebar.item>
