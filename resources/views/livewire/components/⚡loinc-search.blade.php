<?php

use App\Models\Terminology\Loinc;
use Livewire\Attributes\Prop;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Komponen pencarian LOINC.
 *
 * Penggunaan:
 *   <livewire:components.loinc-search defaultClass="CHEM" />
 *   <livewire:components.loinc-search :limitClasses="['CHEM', 'HEM/BC']" />
 *
 * Event yang di-dispatch saat item dipilih:
 *   loinc-selected → { loinc_num, component, long_common_name, loinc_class, scale_typ, system }
 */
new class extends Component {
    use WithPagination;

    /** Kelas LOINC default saat komponen pertama kali ditampilkan (null = semua) */
    #[Prop]
    public ?string $defaultClass = null;

    /** Batasi pencarian hanya ke kelas-kelas tertentu (array kosong = semua kelas) */
    #[Prop]
    public array $limitClasses = [];

    /** Isi awal field pencarian (misal: system_term yang sudah tersimpan) */
    #[Prop]
    public string $initialSearch = '';

    public string $search = '';
    public string $selectedClass = '';

    public function mount(): void
    {
        $this->selectedClass = $this->defaultClass ?? '';
        $this->search = $this->initialSearch;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSelectedClass(): void
    {
        $this->resetPage();
    }

    /** Dispatch event ke parent saat item dipilih */
    public function select(string $loincNum): void
    {
        $item = Loinc::findOrFail($loincNum);

        $this->dispatch('loinc-selected', loinc: $item->toArray());
    }

    public function with(): array
    {
        $availableClasses = !empty($this->limitClasses) ? collect($this->limitClasses) : Loinc::distinct()->orderBy('class')->pluck('class')->filter()->values();

        $query = Loinc::query()
            ->when(
                $this->search,
                fn($q) => $q->where(function ($q) {
                    // $q->where('loinc_num', 'like', "%{$this->search}%")
                    //     ->orWhere('long_common_name', 'like', "%{$this->search}%")
                    //     ->orWhere('component', 'like', "%{$this->search}%")
                    //     ->orWhere('shortname', 'like', "%{$this->search}%");
                    $q->whereAny(['loinc_num', 'component', 'long_common_name'], 'like', "%{$this->search}%");
                }),
            )
            ->when(!empty($this->limitClasses), fn($q) => $q->whereIn('class', $this->limitClasses))
            ->when($this->selectedClass, fn($q) => $q->where('class', $this->selectedClass))
            ->orderBy('loinc_num');

        return [
            'results' => $query->paginate(15),
            'classes' => $availableClasses,
        ];
    }
};
?>

<div class="space-y-3">
    {{-- Filter --}}
    <div class="flex flex-col gap-2 sm:flex-row">
        <div class="flex-1">
            <flux:input wire:model.live.debounce.400ms="search" placeholder="Cari nomor, nama, atau komponen LOINC..."
                icon="magnifying-glass" clearable />
        </div>
        <div class="sm:w-56">
            <flux:select wire:model.live="selectedClass" placeholder="Semua kelas">
                <flux:select.option value="">Semua kelas</flux:select.option>
                @foreach ($classes as $class)
                    <flux:select.option value="{{ $class }}">{{ $class }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    {{-- Tabel hasil --}}
    <div class="overflow-auto border rounded-lg border-zinc-200 dark:border-primary-dark-700">
        <table class="w-full text-sm border-collapse">
            <thead class="bg-zinc-50 dark:bg-primary-dark-800">
                <tr class="text-left">
                    <th
                        class="px-3 py-2 text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-primary-dark-400 whitespace-nowrap">
                        LOINC Num</th>
                    <th
                        class="px-3 py-2 text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-primary-dark-400">
                        Nama Panjang</th>
                    <th
                        class="px-3 py-2 text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-primary-dark-400">
                        Komponen</th>
                    <th
                        class="px-3 py-2 text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-primary-dark-400">
                        Kelas</th>
                    <th
                        class="px-3 py-2 text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-primary-dark-400">
                        Skala</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-800">
                @forelse ($results as $item)
                    <tr class="hover:bg-zinc-100 dark:hover:bg-primary-dark-700/60 cursor-pointer"
                        data-loinc="{{ $item->loinc_num }}" x-on:click="$wire.select($el.dataset.loinc)">
                        <td
                            class="px-3 py-2 font-mono text-xs text-zinc-600 dark:text-primary-dark-400 whitespace-nowrap">
                            {{ $item->loinc_num }}
                        </td>
                        <td class="px-3 py-2 text-zinc-800 dark:text-primary-dark-200">
                            {{ $item->long_common_name }}
                        </td>
                        <td class="px-3 py-2 text-xs text-zinc-600 dark:text-primary-dark-400">
                            {{ $item->component }}
                        </td>
                        <td class="px-3 py-2">
                            @if ($item->class)
                                <flux:badge size="sm" color="teal">{{ $item->class }}</flux:badge>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-xs text-zinc-500 dark:text-primary-dark-400">
                            {{ $item->scale_typ }}
                        </td>
                        <td class="px-2 py-2 text-right">
                            <flux:icon name="cursor-arrow-rays"
                                class="w-4 h-4 text-zinc-300 dark:text-primary-dark-600" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-8 text-sm text-center text-zinc-400">
                            Tidak ada hasil ditemukan.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Paginasi --}}
    @if ($results->hasPages())
        <div class="mt-2">
            {{ $results->links() }}
        </div>
    @endif
</div>
