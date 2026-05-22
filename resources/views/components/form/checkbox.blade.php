@props([
    'label' => null,
    'name' => null,
    'description' => null,
    'disabled' => false,
])

@php
$wireModel = $attributes->wire('model')->value();
$errorKey = $name ?? $wireModel;
@endphp

<flux:field>
    <flux:checkbox
        :name="$name"
        :label="$label"
        :disabled="$disabled"
        {{ $attributes }}
    />

    @if($description)
    <flux:description class="ml-6">{{ $description }}</flux:description>
    @endif

    <flux:error :name="$errorKey" />
</flux:field>
