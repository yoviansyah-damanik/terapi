@props([
    'label' => null,
    'name' => null,
    'description' => null,
    'required' => false,
    'accept' => null,
    'multiple' => false,
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

    <div x-data="{ isDragging: false }" x-on:dragover.prevent="isDragging = true" x-on:dragleave.prevent="isDragging = false"
        x-on:drop.prevent="isDragging = false" class="relative">
        <label
            :class="isDragging ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20' :
                'border-zinc-300 dark:border-primary-dark-600'"
            class="flex flex-col items-center justify-center w-full p-6 transition-colors border-2 border-dashed rounded-lg cursor-pointer hover:border-primary-400">
            <div class="flex flex-col items-center justify-center">
                <flux:icon name="cloud-arrow-up" class="mb-2 size-10 text-zinc-400" />
                <p class="mb-1 text-sm text-zinc-600 dark:text-primary-dark-400">
                    <span class="font-semibold">Klik untuk upload</span> atau drag & drop
                </p>
                @if ($description)
                    <p class="text-xs text-zinc-500">{{ $description }}</p>
                @endif
            </div>

            <input type="file" :name="$name" :accept="$accept" :multiple="$multiple" class="hidden"
                {{ $attributes }} />
        </label>
    </div>

    <flux:error :name="$errorKey" />
</flux:field>
