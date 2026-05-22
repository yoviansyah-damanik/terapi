<?php

use App\Models\FhirDictionary;
use Livewire\Attributes\Prop;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Komponen pencarian FHIR Dictionaries (tabel fhir_dictionaries).
 *
 * Penggunaan:
 *   <livewire:components.fhir-dictionaries-search :limitTypes="['diagnostic-category']" />
 *
 * Event yang di-dispatch saat item dipilih:
 *   fhir-dictionary-selected → { id, source, type, system_code, system_term, system_display }
 */
new class extends Component {
    use WithPagination;

    /** Batasi pencarian hanya ke type tertentu (array kosong = semua type) */
    #[Prop]
    public array $limitTypes = [];

    /** Batasi pencarian hanya ke source tertentu (array kosong = semua source) */
    #[Prop]
    public array $limitSources = [];

    #[Prop]
    public string $initialSearch = '';

    /** Nilai awal filter source */
    #[Prop]
    public ?string $initialSource = null;

    /** Nilai awal filter type */
    #[Prop]
    public ?string $initialType = null;

    public string $search = '';
    public ?string $filterSource = null;
    public ?string $filterType = null;

    public array $availableSources = [];
    public array $availableTypes = [];

    public function mount(): void
    {
        $this->search = $this->initialSearch;
        $this->filterSource = $this->initialSource;
        $this->filterType = $this->initialType;

        // Ambil daftar source dari static model dan sesuaikan dengan limit
        $allSources = FhirDictionary::getDistinctSources();
        $this->availableSources = !empty($this->limitSources) 
            ? array_values(array_intersect($allSources, $this->limitSources)) 
            : $allSources;

        $queryTypes = FhirDictionary::query();
        if (!empty($this->limitSources)) {
            $queryTypes->whereIn('source', $this->limitSources);
        }
        if (!empty($this->limitTypes)) {
            $queryTypes->whereIn('type', $this->limitTypes);
        }
        $this->availableTypes = $queryTypes->distinct()->pluck('type')->filter()->toArray();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterSource(): void
    {
        $this->resetPage();
    }

    public function updatingFilterType(): void
    {
        $this->resetPage();
    }

    public function select(string $id): void
    {
        $item = FhirDictionary::findOrFail($id);
        $this->dispatch('fhir-dictionary-selected', item: $item->toArray());
    }

    public function with(): array
    {
        $results = FhirDictionary::query()
            ->when(!empty($this->limitTypes), fn($q) => $q->whereIn('type', $this->limitTypes))
            ->when(!empty($this->limitSources), fn($q) => $q->whereIn('source', $this->limitSources))
            ->when($this->filterSource, fn($q) => $q->where('source', $this->filterSource))
            ->when($this->filterType, fn($q) => $q->where('type', $this->filterType))
            ->when(
                $this->search,
                fn($q) => $q->where(function ($query) {
                    $query->where('system_code', 'like', "%{$this->search}%")->orWhere('system_term', 'like', "%{$this->search}%");
                }),
            )
            ->orderBy('system_code')
            ->paginate(15);

        return ['results' => $results];
    }
};
?>

<div class="space-y-3">
    <div class="flex flex-col sm:flex-row gap-2">
        <div class="flex-1">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari kode atau nama..."
                icon="magnifying-glass" clearable />
        </div>

        <div class="w-full sm:w-40 shrink-0">
            <flux:select wire:model.live="filterSource" placeholder="Semua Sumber">
                <flux:select.option value="">Semua Sumber</flux:select.option>
                @foreach ($availableSources as $src)
                <flux:select.option value="{{ $src }}">{{ strtoupper($src) }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="w-full sm:w-48 shrink-0">
            <flux:select wire:model.live="filterType" placeholder="Semua Tipe">
                <flux:select.option value="">Semua Tipe</flux:select.option>
                @foreach ($availableTypes as $typ)
                <flux:select.option value="{{ $typ }}">{{ ucwords(str_replace('-', ' ', $typ)) }}
                </flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <div
        class="overflow-auto rounded-xl border border-zinc-200 dark:border-primary-dark-700 bg-white dark:bg-primary-dark-900/40">
        <table class="w-full text-sm border-collapse">
            <thead class="bg-zinc-50 dark:bg-primary-dark-800">
                <tr class="text-left">
                    <th
                        class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-primary-dark-400 whitespace-nowrap w-28">
                        Kode
                    </th>
                    <th
                        class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-primary-dark-400">
                        Nama
                    </th>
                    <th
                        class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-primary-dark-400 whitespace-nowrap w-32">
                        Konteks
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-800">
                @forelse ($results as $item)
                <tr class="group hover:bg-amber-50/60 dark:hover:bg-amber-900/10 cursor-pointer transition-colors"
                    data-id="{{ $item->id }}" x-on:click="$wire.select($el.dataset.id)">
                    <td class="px-4 py-3 whitespace-nowrap">
                        <span class="font-mono text-xs font-bold text-amber-700 dark:text-amber-400">
                            {{ $item->system_code }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex flex-col gap-0.5">
                            <span
                                class="text-sm text-zinc-700 dark:text-primary-dark-200">{{ $item->system_term }}</span>
                            @if ($item->system_defenition)
                            <p class="text-xs text-zinc-500 dark:text-primary-dark-400 line-clamp-1">
                                {{ $item->system_defenition }}
                            </p>
                            @endif
                            @if ($item->system_display)
                            <p class="text-[10px] italic text-zinc-400 dark:text-primary-dark-500 line-clamp-1">
                                {{ $item->system_display }}
                            </p>
                            @endif
                        </div>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex flex-col gap-0.5">
                                <span
                                    class="text-[10px] font-bold text-zinc-500 dark:text-primary-dark-400 uppercase tracking-wider">{{ $item->source }}</span>
                                <span
                                    class="text-[10px] text-zinc-400 dark:text-primary-dark-500">{{ str_replace('-', ' ', $item->type) }}</span>
                            </div>
                            <flux:icon name="cursor-arrow-rays"
                                class="w-4 h-4 text-zinc-300 dark:text-primary-dark-600 opacity-0 group-hover:opacity-100 transition-opacity shrink-0" />
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="3" class="px-4 py-10 text-sm text-center text-zinc-400">
                        <div class="flex flex-col items-center gap-2">
                            <flux:icon name="magnifying-glass" class="w-8 h-8 opacity-20" />
                            <p>Tidak ada hasil ditemukan.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($results->hasPages())
    <div class="text-xs">{{ $results->links() }}</div>
    @endif
</div>