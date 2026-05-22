@props([
    'label'         => null,   // teks label
    'id'            => null,   // id unik
    'color'         => 'primary', // warna saat aktif
    'labelPosition' => 'right', // 'left' atau 'right'
    'size'          => 'md',    // 'sm', 'md', 'lg'
    'nowrap'        => true,    // agar teks tidak turun ke baris baru
])

@php
    $uid = $id ?? ('toggle-' . uniqid());
    
    // Warna aktif
    $trackColor = match ($color) {
        'emerald' => 'peer-checked:bg-emerald-500 dark:peer-checked:bg-emerald-500',
        'amber'   => 'peer-checked:bg-amber-500 dark:peer-checked:bg-amber-500',
        'red'     => 'peer-checked:bg-red-500 dark:peer-checked:bg-red-500',
        default   => 'peer-checked:bg-primary-500 dark:peer-checked:bg-primary-500',
    };

    // Dimensi berdasarkan ukuran
    $trackSizeCls = match ($size) {
        'sm' => 'w-7 h-4',
        'lg' => 'w-11 h-6',
        default => 'w-9 h-5', // md
    };
    $thumbSizeCls = match ($size) {
        'sm' => 'w-3 h-3',
        'lg' => 'w-5 h-5',
        default => 'w-4 h-4', // md
    };
    $thumbTranslateCls = match ($size) {
        'sm' => 'peer-checked:translate-x-3',
        'lg' => 'peer-checked:translate-x-5',
        default => 'peer-checked:translate-x-4', // md
    };
    $labelSizeCls = match ($size) {
        'sm' => 'text-[10px]',
        'lg' => 'text-sm',
        default => 'text-xs', // md
    };
@endphp

{{-- Toggle switch atom --}}
<label for="{{ $uid }}" {{ $attributes->except(['wire:model', 'wire:model.live', 'x-model', 'x-model.boolean', ':checked', 'checked', 'disabled'])->merge(['class' => 'inline-flex items-center gap-2 cursor-pointer select-none group ' . ($labelPosition === 'left' ? 'flex-row-reverse' : 'flex-row')]) }}>
    <div class="relative inline-flex shrink-0">
        <input
            type="checkbox"
            id="{{ $uid }}"
            {{ $attributes->only(['wire:model', 'wire:model.live', 'x-model', 'x-model.boolean', ':checked', 'checked', 'disabled']) }}
            class="sr-only peer"
        >
        {{-- Track --}}
        <div class="rounded-full transition-colors duration-200 bg-zinc-200 dark:bg-primary-dark-700
            {{ $trackSizeCls }} {{ $trackColor }}
            peer-disabled:opacity-50 peer-disabled:cursor-not-allowed"></div>
        {{-- Thumb --}}
        <div class="absolute top-0.5 left-0.5 bg-white rounded-full shadow transition-transform duration-200
            {{ $thumbSizeCls }} {{ $thumbTranslateCls }}
            peer-disabled:opacity-50"></div>
    </div>
    @if ($label)
        <span class="{{ $labelSizeCls }} font-medium text-zinc-600 dark:text-primary-dark-300 {{ $nowrap ? 'whitespace-nowrap' : '' }}
            group-has-[:disabled]:opacity-50 group-has-[:disabled]:cursor-not-allowed">
            {{ $label }}
        </span>
    @endif
    {{ $slot }}
</label>
