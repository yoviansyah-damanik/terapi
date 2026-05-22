@props(['stats'])

<div
    class="grid grid-cols-4 gap-2 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/40 border border-zinc-200 dark:border-primary-dark-700 p-3">
    @foreach ([['label' => 'Kapasitas', 'value' => $stats['kapasitas'] ?? 0, 'color' => 'zinc'], ['label' => 'Tersedia', 'value' => $stats['tersedia'] ?? 0, 'color' => 'emerald'], ['label' => 'P', 'value' => $stats['tersediapria'] ?? 0, 'color' => 'blue'], ['label' => 'W', 'value' => $stats['tersediawanita'] ?? 0, 'color' => 'pink']] as $item)
        <div class="flex flex-col items-center gap-0.5">
            <span
                class="text-lg font-bold
                @if ($item['color'] === 'emerald') text-emerald-600 dark:text-emerald-400
                @elseif ($item['color'] === 'blue') text-blue-600 dark:text-blue-400
                @elseif ($item['color'] === 'pink') text-pink-600 dark:text-pink-400
                @else text-zinc-700 dark:text-primary-dark-300 @endif">
                {{ $item['value'] }}
            </span>
            <span class="text-xs text-zinc-400 dark:text-primary-dark-500">{{ $item['label'] }}</span>
        </div>
    @endforeach
</div>
