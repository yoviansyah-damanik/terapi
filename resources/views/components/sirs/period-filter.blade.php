@props([
    'tahun' => now()->year,
    'bulan' => now()->month,
    'showBulan' => true,
])

<div
    class="flex flex-wrap items-end gap-3 p-4 bg-zinc-50 dark:bg-primary-dark-800 rounded-lg border border-zinc-200 dark:border-primary-dark-700 print:hidden">
    <div>
        <label class="block text-xs font-semibold text-zinc-600 dark:text-primary-dark-400 mb-1">Tahun</label>
        <flux:select wire:model.live="tahun" class="w-28">
            @for ($y = now()->year; $y >= now()->year - 5; $y--)
                <flux:select.option value="{{ $y }}">{{ $y }}</flux:select.option>
            @endfor
        </flux:select>
    </div>

    @if ($showBulan)
        <div>
            <label class="block text-xs font-semibold text-zinc-600 dark:text-primary-dark-400 mb-1">Bulan</label>
            <flux:select wire:model.live="bulan" class="w-36">
                @foreach ([1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'] as $m => $nama)
                    <flux:select.option value="{{ $m }}">{{ $nama }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    @endif

    <div wire:loading.flex class="flex items-center gap-2 text-sm text-primary-600 dark:text-primary-400">
        <flux:icon.arrow-path class="w-4 h-4 animate-spin" />
        <span>Memuat data...</span>
    </div>
</div>
