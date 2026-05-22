@props([
    'label' => null,
    'name' => null,
    'type' => 'text',
    'placeholder' => null,
    'description' => null,
    'required' => false,
    'disabled' => false,
    'readonly' => false,
    'icon' => null,
    'iconTrailing' => null,
    'clearable' => false,
])

@php
    $wireModel = $attributes->wire('model')->value();
    $errorKey = $name ?? $wireModel;
@endphp

<flux:field>
    @if ($label)
        <flux:label :for="$name">
            {{ $label }}
            @if ($required)
                <span class="text-red-500">*</span>
            @endif
        </flux:label>
    @endif

    <flux:input :type="$type" :name="$name" :placeholder="$placeholder" :disabled="$disabled"
        :readonly="$readonly" :icon="$icon" :icon-trailing="$iconTrailing" :clearable="$clearable"
        :required="$required" {{ $attributes }} />

    @if ($description && !$errors->has($errorKey))
        <flux:description>{{ $description }}</flux:description>
    @endif

    <flux:error :name="$errorKey" />
</flux:field>
