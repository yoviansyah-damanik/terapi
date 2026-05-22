@props([
    'label' => null,
    'name' => null,
    'placeholder' => null,
    'description' => null,
    'required' => false,
    'disabled' => false,
    'readonly' => false,
    'rows' => 3,
    'resize' => 'vertical',
])

@php
$wireModel = $attributes->wire('model')->value();
$errorKey = $name ?? $wireModel;
@endphp

<flux:field>
    @if($label)
    <flux:label :for="$name">
        {{ $label }}
        @if($required)
        <span class="text-red-500">*</span>
        @endif
    </flux:label>
    @endif

    <flux:textarea
        :name="$name"
        :placeholder="$placeholder"
        :disabled="$disabled"
        :readonly="$readonly"
        :rows="$rows"
        :resize="$resize"
        :required="$required"
        {{ $attributes }}
    />

    @if($description && !$errors->has($errorKey))
    <flux:description>{{ $description }}</flux:description>
    @endif

    <flux:error :name="$errorKey" />
</flux:field>
