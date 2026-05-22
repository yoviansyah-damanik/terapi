@props([
    'patientName'  => null,
    'patientNoRm'  => null,
    'birthDate'    => null,
    'patientSex'   => null,
    'onPickClick'  => null,   // wire:click value
    'onChangeClick'=> null,   // wire:click value saat sudah ada pasien
    'errorKey'     => 'patientId',
])

@php
    $selected = !empty($patientName);
    $sexLabel  = match ($patientSex) { 'M' => 'L', 'F' => 'P', default => '-' };
@endphp

<div {{ $attributes }}>
    @if ($selected)
        {{-- State: pasien sudah dipilih --}}
        <div class="flex items-center gap-3 px-4 py-3 rounded-xl border-2
                    border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20">
            <div class="w-9 h-9 rounded-full bg-blue-200 dark:bg-blue-800
                        flex items-center justify-center shrink-0
                        text-sm font-bold text-blue-700 dark:text-blue-300">
                {{ strtoupper(substr($patientName, 0, 1)) }}
            </div>

            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-zinc-900 dark:text-primary-dark-100 truncate">
                    {{ $patientName }}
                </p>
                <p class="text-xs text-zinc-500 dark:text-primary-dark-400 font-mono mt-0.5">
                    {{ $patientNoRm }}
                    @if ($birthDate) · {{ \Carbon\Carbon::parse($birthDate)->format('d/m/Y') }} @endif
                    @if ($patientSex) · {{ $sexLabel }} @endif
                </p>
            </div>

            @if ($onChangeClick)
                <flux:button size="xs" icon="pencil" wire:click="{{ $onChangeClick }}">Ganti</flux:button>
            @endif
        </div>
    @else
        {{-- State: belum ada pasien --}}
        <button type="button"
            @if ($onPickClick) wire:click="{{ $onPickClick }}" @endif
            class="w-full flex items-center justify-center gap-2 py-4 rounded-xl
                   border-2 border-dashed border-zinc-300 dark:border-primary-dark-600
                   text-zinc-500 dark:text-primary-dark-400
                   hover:border-blue-400 hover:text-blue-600
                   dark:hover:border-blue-500 dark:hover:text-blue-400
                   transition-colors text-sm font-medium group">
            <flux:icon name="user-plus"
                class="size-4 group-hover:text-blue-500 transition-colors" />
            Pilih Pasien dari SIMRS
        </button>
        @error($errorKey)
            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
        @enderror
    @endif
</div>
