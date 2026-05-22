<?php

use App\Models\FhirDictionary;
use App\Models\Mapping\EpisodeOfCareMap;
use App\Models\Terminology\Icd10;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new #[Layout('layouts::app')] #[Title('Episode of Care — Mapping ICD-10')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $filterSource = '';
    public int $perPage = 25;

    // ── State modal mapping ICD-10 ───────────────────────────────────────────
    public bool $showMappingModal = false;
    public ?string $selectedEocCode = null;
    public ?string $selectedEocName = null;
    public ?string $selectedEocSource = null;
    public string $icdSearch = '';

    // ── State modal hapus ────────────────────────────────────────────────────
    public bool $showDeleteModal = false;
    public ?string $deleteMapId = null;
    public string $deleteMapLabel = '';

    // ── State notes edit ─────────────────────────────────────────────────────
    public bool $showNotesModal = false;
    public ?string $notesMapId = null;
    public string $notesValue = '';

    // ── State konfirmasi hapus semua mapping episode ─────────────────────────
    public bool $showClearModal = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }
    public function updatedFilterSource(): void
    {
        $this->resetPage();
    }

    // ── Kelola mapping ICD-10 ────────────────────────────────────────────────

    public function openMappingModal(string $code, string $name, string $source): void
    {
        $this->selectedEocCode = $code;
        $this->selectedEocName = $name;
        $this->selectedEocSource = $source;
        $this->icdSearch = '';
        $this->showMappingModal = true;
    }

    public function addIcd10(string $icd10Code): void
    {
        if (!$this->selectedEocCode) {
            return;
        }

        $exists = EpisodeOfCareMap::where('eoc_code', $this->selectedEocCode)->where('icd10_code', $icd10Code)->exists();

        if ($exists) {
            $this->toastWarning("ICD-10 {$icd10Code} sudah ada di mapping ini.");
            return;
        }

        EpisodeOfCareMap::create([
            'eoc_code' => $this->selectedEocCode,
            'icd10_code' => $icd10Code,
        ]);
        EpisodeOfCareMap::clearCache();
        $this->icdSearch = '';
        $this->toastSuccess("ICD-10 {$icd10Code} berhasil ditambahkan.");
    }

    public function confirmDeleteMap(string $id, string $label): void
    {
        $this->deleteMapId = $id;
        $this->deleteMapLabel = $label;
        $this->showDeleteModal = true;
    }

    public function deleteMap(): void
    {
        EpisodeOfCareMap::where('id', $this->deleteMapId)->delete();
        EpisodeOfCareMap::clearCache();
        $this->showDeleteModal = false;
        $this->reset(['deleteMapId', 'deleteMapLabel']);
        $this->toastSuccess('Mapping ICD-10 berhasil dihapus.');
    }

    public function openNotesModal(string $id, string $currentNotes): void
    {
        $this->notesMapId = $id;
        $this->notesValue = $currentNotes;
        $this->showNotesModal = true;
    }

    public function saveNotes(): void
    {
        EpisodeOfCareMap::where('id', $this->notesMapId)->update(['notes' => $this->notesValue]);
        EpisodeOfCareMap::clearCache();
        $this->showNotesModal = false;
        $this->reset(['notesMapId', 'notesValue']);
        $this->toastSuccess('Catatan berhasil disimpan.');
    }

    public function confirmClearMappings(): void
    {
        $this->showClearModal = true;
    }

    public function clearMappings(): void
    {
        if (!$this->selectedEocCode) {
            return;
        }
        EpisodeOfCareMap::where('eoc_code', $this->selectedEocCode)->delete();
        EpisodeOfCareMap::clearCache();
        $this->showClearModal = false;
        $this->toastSuccess('Semua mapping ICD-10 untuk episode ini berhasil dihapus.');
    }

    // ── Data ─────────────────────────────────────────────────────────────────

    public function with(): array
    {
        $episodeTypes = FhirDictionary::where('type', 'episode-of-care-type')
            ->when($this->filterSource, fn($q) => $q->where('source', $this->filterSource))
            ->when(
                $this->search,
                fn($q) => $q->where(
                    fn($q2) => $q2
                        ->where('system_code', 'like', "%{$this->search}%")
                        ->orWhere('system_term', 'like', "%{$this->search}%")
                        ->orWhere('system_defenition', 'like', "%{$this->search}%"),
                ),
            )
            ->orderByRaw("FIELD(source,'kemkes','hl7') DESC")
            ->orderBy('system_term')
            ->paginate($this->perPage);

        // Ambil mapping untuk episode yang tampil saat ini
        $codes = $episodeTypes->pluck('system_code')->toArray();
        $mappings = EpisodeOfCareMap::whereIn('eoc_code', $codes)->get()->groupBy('eoc_code');

        $episodeTypes->getCollection()->transform(function ($item) use ($mappings) {
            $item->mappings = $mappings->get($item->system_code, collect());
            return $item;
        });

        // Stat
        $totalTypes = FhirDictionary::where('type', 'episode-of-care-type')->count();
        $totalHl7 = FhirDictionary::where('type', 'episode-of-care-type')->where('source', 'hl7')->count();
        $totalKemkes = FhirDictionary::where('type', 'episode-of-care-type')->where('source', 'kemkes')->count();
        $totalMapped = EpisodeOfCareMap::distinct('eoc_code')->count('eoc_code');
        $totalUnmapped = $totalTypes - $totalMapped;

        // Data untuk modal mapping
        $icdResults = collect();
        if ($this->showMappingModal && $this->selectedEocCode) {
            $existingCodes = EpisodeOfCareMap::where('eoc_code', $this->selectedEocCode)->pluck('icd10_code')->toArray();

            if ($this->icdSearch) {
                $icdResults = Icd10::where(fn($q) => $q->where('code', 'like', "%{$this->icdSearch}%")->orWhere('display', 'like', "%{$this->icdSearch}%"))->whereNotIn('code', $existingCodes)->orderBy('code')->limit(20)->get();
            }
        }

        // ICD-10 yang sudah di-map (untuk modal)
        $currentMappings = $this->selectedEocCode ? EpisodeOfCareMap::where('eoc_code', $this->selectedEocCode)->orderBy('icd10_code')->get() : collect();

        return [
            'episodeTypes' => $episodeTypes,
            'totalTypes' => $totalTypes,
            'totalHl7' => $totalHl7,
            'totalKemkes' => $totalKemkes,
            'totalMapped' => $totalMapped,
            'totalUnmapped' => $totalUnmapped,
            'icdResults' => $icdResults,
            'currentMappings' => $currentMappings,
        ];
    }
}; ?>

<div>
    <x-ui.page-header title="Episode of Care — Mapping ICD-10"
        subtitle="Kelola mapping kode ICD-10 untuk setiap tipe Episode of Care (HL7 & Kemkes)">
    </x-ui.page-header>

    {{-- Stat Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <x-organisms.stat-card title="Total Tipe" :value="number_format($totalTypes)" icon="clipboard-document-check" color="zinc" />
        <x-organisms.stat-card title="Sumber HL7" :value="number_format($totalHl7)" icon="globe-alt" color="sky" />
        <x-organisms.stat-card title="Sumber Kemkes" :value="number_format($totalKemkes)" icon="heart" color="rose" />
        <x-organisms.stat-card title="Ter-mapping ICD-10" :value="number_format($totalMapped)" icon="hashtag" color="violet"
            :subtitle="$totalUnmapped > 0 ? $totalUnmapped . ' belum dipetakan' : 'Semua terpetakan'" />
    </div>

    <x-organisms.data-panel>
        <x-slot:filter>
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-48">
                    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                        placeholder="Cari kode atau nama tipe episode..." clearable />
                </div>
                <flux:select wire:model.live="filterSource" class="w-44">
                    <flux:select.option value="">Semua Sumber</flux:select.option>
                    <flux:select.option value="hl7">HL7</flux:select.option>
                    <flux:select.option value="kemkes">Kemkes</flux:select.option>
                </flux:select>
                <flux:select wire:model.live="perPage" class="w-40">
                    <flux:select.option value="25">25 / halaman</flux:select.option>
                    <flux:select.option value="50">50 / halaman</flux:select.option>
                </flux:select>
            </div>
        </x-slot:filter>

        <x-organisms.table>
            <x-slot:headings>
                <x-atoms.table-heading class="w-28">Kode</x-atoms.table-heading>
                <x-atoms.table-heading>Nama / Definisi</x-atoms.table-heading>
                <x-atoms.table-heading class="w-24">Sumber</x-atoms.table-heading>
                <x-atoms.table-heading>ICD-10 di-mapping</x-atoms.table-heading>
                <x-atoms.table-heading align="center" class="w-28">Aksi</x-atoms.table-heading>
            </x-slot:headings>

            @forelse ($episodeTypes as $episode)
                <x-molecules.table-row wire:key="eoc-{{ $episode->system_code }}">

                    {{-- Kode --}}
                    <x-atoms.table-cell nowrap>
                        <span
                            class="inline-block font-mono text-xs font-bold px-2 py-1 rounded-md
                            bg-zinc-100 dark:bg-primary-dark-700
                            text-zinc-700 dark:text-primary-dark-200
                            ring-1 ring-zinc-200 dark:ring-primary-dark-600">
                            {{ $episode->system_code }}
                        </span>
                    </x-atoms.table-cell>

                    {{-- Nama / Definisi --}}
                    <x-atoms.table-cell>
                        <p class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">
                            {{ $episode->system_term }}
                        </p>
                        @if ($episode->system_defenition)
                            <p
                                class="mt-0.5 text-xs text-zinc-400 dark:text-primary-dark-500 leading-snug line-clamp-2">
                                {{ $episode->system_defenition }}
                            </p>
                        @endif
                        <p class="mt-1 text-[10px] font-mono text-zinc-300 dark:text-primary-dark-600 line-clamp-1">
                            {{ $episode->system_display }}
                        </p>
                    </x-atoms.table-cell>

                    {{-- Sumber --}}
                    <x-atoms.table-cell>
                        @if ($episode->source === 'kemkes')
                            <flux:badge color="rose" size="sm">Kemkes</flux:badge>
                        @elseif ($episode->source === 'hl7')
                            <flux:badge color="sky" size="sm">HL7</flux:badge>
                        @else
                            <flux:badge color="zinc" size="sm">{{ $episode->source }}</flux:badge>
                        @endif
                    </x-atoms.table-cell>

                    {{-- ICD-10 --}}
                    <x-atoms.table-cell>
                        @if ($episode->mappings->isNotEmpty())
                            <div class="flex flex-wrap gap-1">
                                @foreach ($episode->mappings->take(10) as $map)
                                    <span
                                        class="inline-flex items-center gap-1 font-mono text-[11px] font-bold px-1.5 py-0.5 rounded
                                        bg-violet-50 dark:bg-violet-900/20
                                        text-violet-700 dark:text-violet-400
                                        ring-1 ring-violet-200 dark:ring-violet-700/40">
                                        {{ $map->icd10_code }}
                                    </span>
                                @endforeach
                                @if ($episode->mappings->count() > 10)
                                    <span
                                        class="inline-flex items-center text-[11px] text-zinc-400 dark:text-primary-dark-500 px-1">
                                        +{{ $episode->mappings->count() - 10 }} lainnya
                                    </span>
                                @endif
                            </div>
                            <p class="mt-1 text-[10px] text-zinc-400 dark:text-primary-dark-500">
                                {{ $episode->mappings->count() }} kode ICD-10
                            </p>
                        @else
                            <div class="flex items-center gap-2 text-zinc-400 dark:text-primary-dark-500">
                                <span class="w-2 h-2 rounded-full bg-zinc-200 dark:bg-primary-dark-600"></span>
                                <span class="text-xs italic">Belum ada mapping</span>
                            </div>
                        @endif
                    </x-atoms.table-cell>

                    {{-- Aksi --}}
                    <x-atoms.table-cell align="center" action nowrap>
                        <x-atoms.button
                            wire:click="openMappingModal('{{ $episode->system_code }}', '{{ addslashes($episode->system_term) }}', '{{ $episode->source }}')"
                            size="sm" icon="{{ $episode->mappings->isNotEmpty() ? 'pencil-square' : 'plus' }}"
                            variant="ghost"
                            tooltip="{{ $episode->mappings->isNotEmpty() ? 'Kelola Mapping ICD-10' : 'Tambah Mapping ICD-10' }}" />
                    </x-atoms.table-cell>

                </x-molecules.table-row>
            @empty
                <x-molecules.table-row>
                    <x-atoms.table-cell colspan="5" align="center" class="py-16">
                        <div class="flex flex-col items-center gap-3">
                            <div
                                class="flex items-center justify-center w-14 h-14 rounded-2xl bg-zinc-100 dark:bg-primary-dark-700">
                                <flux:icon name="clipboard-document-check"
                                    class="w-7 h-7 text-zinc-300 dark:text-primary-dark-500" />
                            </div>
                            <p class="text-sm font-semibold text-zinc-500 dark:text-primary-dark-400">Tidak ada tipe
                                episode ditemukan</p>
                            <p class="text-xs text-zinc-400 dark:text-primary-dark-500">Coba ubah kata kunci pencarian
                                atau filter sumber</p>
                        </div>
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @endforelse
        </x-organisms.table>

        @if ($episodeTypes->hasPages())
            <x-slot:footer>{{ $episodeTypes->links() }}</x-slot:footer>
        @endif
    </x-organisms.data-panel>


    {{-- ═══════════════════════════════════════════════════════════════
         Modal: Kelola Mapping ICD-10
    ═══════════════════════════════════════════════════════════════ --}}
    <x-organisms.modal wire:model="showMappingModal" maxWidth="3xl" title="Mapping ICD-10">
        <div class="space-y-5">

            {{-- Header --}}
            <div class="flex items-start justify-between gap-4">
                <div>
                    
                    <flux:text class="mt-0.5">
                        Episode type:
                        <span
                            class="font-mono font-bold text-primary-600 dark:text-primary-400">{{ $selectedEocCode }}</span>
                        — <span
                            class="font-semibold text-zinc-700 dark:text-primary-dark-200">{{ $selectedEocName }}</span>
                        @if ($selectedEocSource === 'kemkes')
                            <flux:badge color="rose" size="sm" class="ml-1.5">Kemkes</flux:badge>
                        @elseif ($selectedEocSource === 'hl7')
                            <flux:badge color="sky" size="sm" class="ml-1.5">HL7</flux:badge>
                        @endif
                    </flux:text>
                </div>
            </div>

            {{-- Search ICD-10 --}}
            <div>
                <flux:input wire:model.live.debounce.400ms="icdSearch" icon="magnifying-glass"
                    placeholder="Cari kode atau nama ICD-10 untuk ditambahkan..." clearable />

                @if ($icdSearch && $icdResults->isNotEmpty())
                    <div class="mt-2 rounded-xl border border-zinc-200 dark:border-primary-dark-600 overflow-hidden">
                        @foreach ($icdResults as $icd)
                            <button wire:click="addIcd10('{{ $icd->code }}')"
                                class="flex items-center gap-3 w-full px-4 py-2.5 text-left hover:bg-primary-50 dark:hover:bg-primary-900/20 transition-colors border-b border-zinc-100 dark:border-primary-dark-700 last:border-0">
                                <span
                                    class="font-mono text-xs font-bold text-primary-600 dark:text-primary-400 w-16 shrink-0">
                                    {{ $icd->code }}
                                </span>
                                <span class="text-sm text-zinc-700 dark:text-primary-dark-200 line-clamp-1">
                                    {{ $icd->display }}
                                </span>
                                <flux:icon name="plus-circle"
                                    class="w-4 h-4 text-zinc-300 dark:text-primary-dark-500 shrink-0 ml-auto" />
                            </button>
                        @endforeach
                    </div>
                @elseif ($icdSearch && $icdResults->isEmpty())
                    <p class="mt-2 text-xs text-zinc-400 dark:text-primary-dark-500 px-1">
                        Tidak ada hasil untuk "<span class="font-semibold">{{ $icdSearch }}</span>"
                    </p>
                @endif
            </div>

            {{-- Mapping yang sudah ada --}}
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500 mb-2">
                    ICD-10 ter-mapping ({{ $currentMappings->count() }})
                </p>

                @if ($currentMappings->isEmpty())
                    <div
                        class="flex items-center gap-2 px-4 py-6 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/40 border border-dashed border-zinc-200 dark:border-primary-dark-700">
                        <flux:icon name="hashtag" class="w-5 h-5 text-zinc-300 dark:text-primary-dark-600" />
                        <span class="text-sm text-zinc-400 dark:text-primary-dark-500 italic">Belum ada ICD-10 yang
                            di-mapping</span>
                    </div>
                @else
                    <div
                        class="rounded-xl border border-zinc-200 dark:border-primary-dark-600 overflow-hidden max-h-72 overflow-y-auto">
                        @foreach ($currentMappings as $map)
                            <div wire:key="mapitem-{{ $map->id }}"
                                class="flex items-center gap-3 px-4 py-2.5 border-b border-zinc-100 dark:border-primary-dark-700 last:border-0 group hover:bg-zinc-50 dark:hover:bg-primary-dark-800/50">
                                <span
                                    class="font-mono text-xs font-bold text-violet-700 dark:text-violet-400 w-16 shrink-0">
                                    {{ $map->icd10_code }}
                                </span>
                                @if ($map->notes)
                                    <span
                                        class="text-xs text-zinc-400 dark:text-primary-dark-500 line-clamp-1 flex-1 italic">
                                        {{ $map->notes }}
                                    </span>
                                @else
                                    <span class="flex-1"></span>
                                @endif
                                <div
                                    class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <x-atoms.button
                                        wire:click="openNotesModal('{{ $map->id }}', '{{ addslashes($map->notes ?? '') }}')"
                                        size="sm" variant="ghost" icon="pencil-square"
                                        tooltip="Edit catatan" />
                                    <x-atoms.button
                                        wire:click="confirmDeleteMap('{{ $map->id }}', '{{ $map->icd10_code }}')"
                                        size="sm" variant="ghost" icon="trash" tooltip="Hapus"
                                        class="text-red-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20" />
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="flex justify-end">
                <x-atoms.button wire:click="$set('showMappingModal', false)" variant="ghost">Tutup</x-atoms.button>
            </div>
        </div>

        <x-slot:footer>
            @if ($currentMappings->isNotEmpty())
                <div class="flex justify-end w-full">
                    <x-atoms.button wire:click="confirmClearMappings" size="sm" variant="ghost" icon="trash"
                        class="text-red-400 hover:text-red-600">
                        Hapus Semua Mapping
                    </x-atoms.button>
                </div>
            @endif
        </x-slot:footer>
    </x-organisms.modal>


    {{-- ═══════════════════════════════════════════════════════════════
         Modal: Hapus mapping tunggal
    ═══════════════════════════════════════════════════════════════ --}}
    <x-organisms.modal wire:model="showDeleteModal" maxWidth="sm" title="Hapus Mapping ICD-10">
        <div class="space-y-6">
            <div class="flex items-center gap-4">
                <div
                    class="flex items-center justify-center w-12 h-12 rounded-2xl bg-red-100 dark:bg-red-900/30 shrink-0">
                    <flux:icon name="exclamation-triangle" class="w-6 h-6 text-red-600 dark:text-red-400" />
                </div>
                <div>
                    <flux:text class="mt-0.5">
                        Hapus kode <span class="font-mono font-bold">{{ $deleteMapLabel }}</span> dari episode ini?
                    </flux:text>
                </div>
            </div>
        </div>

        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <x-atoms.button wire:click="$set('showDeleteModal', false)" variant="ghost">Batal</x-atoms.button>
                <x-atoms.button wire:click="deleteMap" variant="danger">Hapus</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>


    {{-- ═══════════════════════════════════════════════════════════════
         Modal: Edit notes
    ═══════════════════════════════════════════════════════════════ --}}
    <x-organisms.modal wire:model="showNotesModal" maxWidth="md" title="Edit Catatan">
        <div class="space-y-5">
            <div>
                
                <flux:text class="mt-0.5">Catatan untuk mapping ICD-10 ini.</flux:text>
            </div>
            <flux:textarea wire:model="notesValue" rows="3" placeholder="Tulis catatan..." />
        </div>

        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <x-atoms.button wire:click="$set('showNotesModal', false)" variant="ghost">Batal</x-atoms.button>
                <x-atoms.button wire:click="saveNotes" variant="primary">Simpan</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>


    {{-- ═══════════════════════════════════════════════════════════════
         Modal: Hapus semua mapping episode
    ═══════════════════════════════════════════════════════════════ --}}
    <x-organisms.modal wire:model="showClearModal" maxWidth="sm" title="Hapus Semua Mapping?">
        <div class="space-y-6">
            <div class="flex items-center gap-4">
                <div
                    class="flex items-center justify-center w-12 h-12 rounded-2xl bg-red-100 dark:bg-red-900/30 shrink-0">
                    <flux:icon name="trash" class="w-6 h-6 text-red-600 dark:text-red-400" />
                </div>
                <div>
                    <flux:text class="mt-0.5">
                        Semua ICD-10 yang dipetakan ke
                        <span class="font-mono font-bold">{{ $selectedEocCode }}</span>
                        akan dihapus. Tindakan ini tidak dapat dibatalkan.
                    </flux:text>
                </div>
            </div>
        </div>

        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <x-atoms.button wire:click="$set('showClearModal', false)" variant="ghost">Batal</x-atoms.button>
                <x-atoms.button wire:click="clearMappings" variant="danger" icon="trash">Hapus
                    Semua</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

</div>
