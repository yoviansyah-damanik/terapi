{{--
    Props:
    - $item : row object dengan mapping_categories, mapping_types, mapping_specialties, mapping_programs, physical_type_code, physical_type_term
--}}
@php
    $rows = [
        ['label' => 'Kategori',  'items' => $item->mapping_categories,  'color' => 'emerald'],
        ['label' => 'Tipe',      'items' => $item->mapping_types,        'color' => 'sky'],
        ['label' => 'Spesialis', 'items' => $item->mapping_specialties,  'color' => 'violet'],
        ['label' => 'Program',   'items' => $item->mapping_programs,     'color' => 'yellow'],
    ];
@endphp
<div class="space-y-1 text-xs">
    @foreach ($rows as $row)
        <div class="flex items-start gap-1.5 min-w-0">
            <span class="shrink-0 w-16 text-[10px] font-semibold uppercase tracking-wide text-zinc-400 dark:text-primary-dark-500 pt-0.5">
                {{ $row['label'] }}
            </span>
            @if ($row['items']->isEmpty())
                <span class="text-zinc-300 dark:text-primary-dark-600 italic">—</span>
            @else
                <div class="flex flex-wrap gap-1 min-w-0">
                    @foreach ($row['items'] as $it)
                        <span class="inline-flex items-center gap-1 max-w-full">
                            <span class="font-mono font-bold text-{{ $row['color'] }}-700 dark:text-{{ $row['color'] }}-400 shrink-0">{{ $it->system_code }}</span>
                            <span class="text-zinc-500 dark:text-primary-dark-400 truncate max-w-[120px]" title="{{ $it->system_term }}">{{ $it->system_term }}</span>
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach

    {{-- Physical Type --}}
    <div class="flex items-start gap-1.5 min-w-0">
        <span class="shrink-0 w-16 text-[10px] font-semibold uppercase tracking-wide text-zinc-400 dark:text-primary-dark-500 pt-0.5">
            Phys Type
        </span>
        @if ($item->physical_type_code)
            <span class="inline-flex items-center gap-1">
                <span class="font-mono font-bold text-zinc-600 dark:text-primary-dark-300">{{ $item->physical_type_code }}</span>
                @if ($item->physical_type_term)
                    <span class="text-zinc-500 dark:text-primary-dark-400 truncate max-w-[120px]" title="{{ $item->physical_type_term }}">{{ $item->physical_type_term }}</span>
                @endif
            </span>
        @else
            <span class="text-zinc-300 dark:text-primary-dark-600 italic">—</span>
        @endif
    </div>
</div>
