@props([
    'label' => null,
    'name' => null,
    'placeholder' => 'Pilih...',
    'description' => null,
    'required' => false,
    'disabled' => false,
    'searchable' => false,
    'clearable' => false,
    'options' => [],
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

    <flux:select
        :name="$name"
        :placeholder="$placeholder"
        :disabled="$disabled"
        :searchable="$searchable"
        :clearable="$clearable"
        :required="$required"
        {{ $attributes }}
    >
        @if(count($options) > 0)
            @foreach($options as $value => $optionLabel)
            <flux:select.option :value="$value">{{ $optionLabel }}</flux:select.option>
            @endforeach
        @else
            {{ $slot }}
        @endif
    </flux:select>

    @if($description && !$errors->has($errorKey))
    <flux:description>{{ $description }}</flux:description>
    @endif

    <flux:error :name="$errorKey" />
</flux:field>
