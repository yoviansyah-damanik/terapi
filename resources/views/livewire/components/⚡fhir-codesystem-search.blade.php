<?php

use App\Models\FhirDictionary;
use Livewire\Attributes\Prop;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Komponen pencarian FHIR CodeSystem gabungan (Kamus FHIR).
 *
 * Penggunaan:
 *   <livewire:components.fhir-codesystem-search defaultSource="hl7" defaultType="service-category" />
 *   <livewire:components.fhir-codesystem-search :limitSources="['kemkes']" :limitTypes="['encounter-class', 'service-type']" />
 *
 * Event yang di-dispatch saat item dipilih:
 *   fhir-codesystem-selected → { id, source, type, system_code, system_term, ... }
 */
new class extends Component {
    use WithPagination;

    /** Source default saat komponen pertama kali ditampilkan (kemkes, hl7, dt) */
    #[Prop]
    public ?string $defaultSource = null;

    /** Type default saat komponen pertama kali ditampilkan */
    #[Prop]
    public ?string $defaultType = null;

    /** Batasi pencarian hanya ke source tertentu (array kosong = semua source) */
    #[Prop]
    public array $limitSources = [];

    /** Batasi pencarian hanya ke type tertentu (array kosong = semua type) */
    #[Prop]
    public array $limitTypes = [];

    public string $search = '';
    public string $selectedSource = '';
    public string $selectedType = '';

    public function mount(): void
    {
        $this->selectedSource = $this->defaultSource ?? '';
        $this->selectedType = $this->defaultType ?? '';
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSelectedSource(): void
    {
        $this->resetPage();
    }

    public function updatingSelectedType(): void
    {
        $this->resetPage();
    }

    public function select(string $id): void
    {
        $item = FhirDictionary::findOrFail($id);
        $this->dispatch('fhir-codesystem-selected', item: $item);
    }

    public function with(): array
    {
        $baseQuery = FhirDictionary::query()
            ->when(!empty($this->limitSources), fn($q) => $q->whereIn('source', $this->limitSources));

        $availableSources = !empty($this->limitSources)
            ? collect($this->limitSources)
            : (clone $baseQuery)->distinct()->orderBy('source')->pluck('source')->filter()->values();

        $availableTypes = !empty($this->limitTypes)
            ? collect($this->limitTypes)
            : (clone $baseQuery)->distinct()->orderBy('type')->pluck('type')->filter()->values();

        $results = $baseQuery
            ->when(
                $this->search,
                fn($q) => $q->where(function ($sub) {
                    $sub->where('system_code', 'like', "%{$this->search}%")
                        ->orWhere('system_term', 'like', "%{$this->search}%")
                        ->orWhere('system_defenition', 'like', "%{$this->search}%");
                }),
            )
            ->when($this->selectedSource, fn($q) => $q->where('source', $this->selectedSource))
            ->when(!empty($this->limitTypes), fn($q) => $q->whereIn('type', $this->limitTypes))
            ->when($this->selectedType, fn($q) => $q->where('type', $this->selectedType))
            ->orderBy('source')
            ->orderBy('type')
            ->orderBy('system_code')
            ->paginate(15);

        return ['results' => $results, 'availableSources' => $availableSources, 'availableTypes' => $availableTypes];
    }
};
?>

<div class="space-y-3">
    {{-- Filter --}}
    <div class="flex flex-col gap-2 sm:flex-row">
        <div class="flex-1">
            <flux:input wire:model.live.debounce.400ms="search" placeholder="Cari kode atau nama terminologi..."
                icon="magnifying-glass" clearable />
        </div>

        @if (empty($limitSources) || count($limitSources) > 1)
        <div class="sm:w-40">
            <flux:select wire:model.live="selectedSource" placeholder="Semua source">
                <flux:select.option value="">Semua source</flux:select.option>
                @foreach ($availableSources as $src)
                <flux:select.option value="{{ $src }}">{{ strtoupper($src) }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        @endif

        @if (empty($limitTypes) || count($limitTypes) > 1)
        <div class="sm:w-52">
            <flux:select wire:model.live="selectedType" placeholder="Semua type">
                <flux:select.option value="">Semua type</flux:select.option>
                @foreach ($availableTypes as $type)
                <flux:select.option value="{{ $type }}">{{ $type }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        @endif
    </div>

    {{-- Tabel hasil --}}
    <div class="overflow-auto border rounded-xl border-zinc-200 dark:border-primary-dark-700 bg-white dark:bg-primary-dark-900/40">
        <table class="w-full text-sm border-collapse">
            <thead class="bg-zinc-50 dark:bg-primary-dark-800">
                <tr class="text-left">
                    <th
                        class="px-4 py-2 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-primary-dark-400 whitespace-nowrap">
                        Source</th>
                    <th
                        class="px-4 py-2 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-primary-dark-400 whitespace-nowrap">
                        Type</th>
                    <th
                        class="px-4 py-2 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-primary-dark-400 whitespace-nowrap">
                        System Code</th>
                    <th
                        class="px-4 py-2 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-primary-dark-400">
                        System Term</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-800">
                @forelse ($results as $item)
                @php
                $color = match($item->source) {
                'kemkes' => 'green',
                'hl7' => 'sky',
                'loinc' => 'indigo',
                'snomed' => 'rose',
                default => 'zinc',
                };
                @endphp
                <tr class="group hover:bg-{{ $color }}-50/50 dark:hover:bg-{{ $color }}-900/10 cursor-pointer transition-colors"
                    data-id="{{ $item->id }}" x-on:click="$wire.select($el.dataset.id)">
                    <td class="px-4 py-2.5 whitespace-nowrap">
                        <flux:badge size="sm" color="{{ $color }}" class="uppercase">{{ $item->source }}</flux:badge>
                    </td>
                    <td class="px-4 py-2.5 whitespace-nowrap">
                        <flux:badge size="sm" color="zinc">{{ $item->type }}</flux:badge>
                    </td>
                    <td
                        class="px-4 py-2.5 font-mono text-xs font-bold text-{{ $color }}-600 dark:text-{{ $color }}-400 whitespace-nowrap">
                        {{ $item->system_code }}
                    </td>
                    <td class="px-4 py-2.5">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-zinc-800 dark:text-primary-dark-200 font-medium">{{ $item->system_term }}</span>
                            <flux:icon name="cursor-arrow-rays"
                                class="w-4 h-4 text-zinc-300 dark:text-primary-dark-600 opacity-0 group-hover:opacity-100 transition-opacity" />
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-4 py-12 text-sm text-center text-zinc-400">
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
    <div class="mt-2 text-xs">
        {{ $results->links() }}
    </div>
    @endif
</div>