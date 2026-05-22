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
    <div class="flex items-center justify-between">
        @if($label)
        <flux:label :for="$name">{{ $label }}</flux:label>
        @endif

        <flux:switch
            :name="$name"
            :disabled="$disabled"
            {{ $attributes }}
        />
    </div>

    @if($description)
    <flux:description>{{ $description }}</flux:description>
    @endif

    <flux:error :name="$errorKey" />
</flux:field>
