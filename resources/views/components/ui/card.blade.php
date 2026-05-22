@props([
    'title' => null,
    'subtitle' => null,
    'padding' => true,
    'footer' => null,
])

<flux:card {{ $attributes }}>
    @if($title || $subtitle)
    <flux:heading size="lg">{{ $title }}</flux:heading>
    @if($subtitle)
    <flux:subheading>{{ $subtitle }}</flux:subheading>
    @endif
    <flux:separator class="my-4" />
    @endif

    <div @class(['p-0' => !$padding])>
        {{ $slot }}
    </div>

    @if($footer)
    <flux:separator class="my-4" />
    <div class="flex items-center justify-end gap-2">
        {{ $footer }}
    </div>
    @endif
</flux:card>
