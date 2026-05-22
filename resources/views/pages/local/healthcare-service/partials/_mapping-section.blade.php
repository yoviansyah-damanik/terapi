{{--
    Props:
    - $label    : string — judul section (misal "Service Category")
    - $source   : string — sumber data ("HL7" / "Satu Sehat")
    - $color    : string — warna badge Flux
    - $items    : Collection<HsServiceItem>
    - $addType  : string — item_type yang dikirim ke openAddItemModal()
--}}
<div class="space-y-2">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
            <p class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-200">{{ $label }}</p>
            <flux:badge size="sm" color="{{ $source === 'HL7' ? 'zinc' : 'green' }}">{{ $source }}</flux:badge>
        </div>
        <x-atoms.button size="sm" icon="plus" variant="ghost" class="text-{{ $color }}-600 dark:text-{{ $color }}-400"
            wire:click="openAddItemModal('{{ $addType }}')">Tambah</x-atoms.button>
    </div>

    @forelse ($items as $item)
        <div
            class="flex items-center justify-between px-3 py-2 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/40 border border-zinc-100 dark:border-primary-dark-700">
            <div class="min-w-0 flex-1">
                <span
                    class="font-mono text-xs font-bold text-{{ $color }}-700 dark:text-{{ $color }}-400">{{ $item->system_code }}</span>
                <span
                    class="ml-2 text-xs text-zinc-600 dark:text-primary-dark-300 line-clamp-1">{{ $item->system_term }}</span>
            </div>
            <x-atoms.button size="sm" icon="trash" variant="ghost" class="text-red-500 shrink-0 ml-2"
                wire:click="removeHsItem('{{ $item->id }}')" />
        </div>
    @empty
        <p class="text-xs italic text-zinc-400 dark:text-primary-dark-500 py-1">Belum ada item. Klik Tambah untuk
            menambahkan.</p>
    @endforelse
</div>
