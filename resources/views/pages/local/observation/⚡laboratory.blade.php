<?php

use App\Jobs\SyncBpjsObservationLabsJob;
use App\Models\Bpjs\BpjsObservationLab;
use App\Models\Mapping\LabItemMap;
use App\Models\Mapping\LabMap;
use App\Models\Mapping\LabSpecimenMap;
use App\Models\Simrs\JnsPerawatanLab;
use App\Models\Simrs\TemplateLaboratorium;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('Lab — Mapping & UUID')] class extends Component {
    use WithPagination;

    #[Url(as: 'tab')]
    public string $activeTab = 'jenis';

    // Tab: Jenis Pemeriksaan
    public string $search = '';
    public int $perPage = 25;
    // LOINC modal
    public bool $showModal = false;
    public ?string $selectedCode = null;
    public ?string $selectedName = null;
    public bool $showDeleteModal = false;
    public ?string $deleteCode = null;
    public ?string $deleteCodeName = null;
    // SNOMED Specimen modal
    public bool $showSnomedModal = false;
    public ?string $selectedSnomedCode = null;
    public ?string $selectedSnomedName = null;
    public bool $showDeleteSnomedModal = false;
    public ?string $deleteSnomedCode = null;
    public ?string $deleteSnomedName = null;

    // BPJS UUID (hanya untuk tab Jenis Pemeriksaan)
    public bool $showSyncModal = false;
    public bool $showBpjsDetailModal = false;
    public ?BpjsObservationLab $selectedBpjsItem = null;
    public bool $showDeleteBpjsModal = false;
    public ?string $deleteBpjsCode = null;
    public string $deleteBpjsName = '';

    // Diagnostic Category modal
    public bool $showCategoryModal = false;
    public ?string $editCategoryCode = null;
    public ?string $editCategoryName = null;
    public bool $showDeleteCategoryModal = false;
    public ?string $deleteCategoryCode = null;
    public ?string $deleteCategoryName = null;

    // Tab: Per Item Pemeriksaan
    public string $searchItem = '';
    public string $filterType = '';
    public int $perPageItem = 25;
    public bool $showItemLoincModal = false;
    public ?string $selectedType = null;
    public ?string $selectedTemplate = null;
    public string $selectedItemName = '';
    public bool $showDeleteItemModal = false;
    public ?string $deleteType = null;
    public ?string $deleteTemplate = null;
    public ?string $deleteItemName = null;

    public string $loincInitialSearch = '';
    public string $snomedInitialSearch = '';
    public string $loincItemInitialSearch = '';

    public function updatedActiveTab(): void
    {
        $this->resetPage();
        $this->search = '';
        $this->searchItem = '';
        $this->filterType = '';
        $this->showModal = false;
        $this->showItemLoincModal = false;
        $this->showSnomedModal = false;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSearchItem(): void
    {
        $this->resetPage();
    }

    public function updatedFilterJenis(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        if ($this->activeTab === 'item') {
            // Hanya tampilkan item milik jenis pemeriksaan yang sudah di-mapping ke LOINC
            $mappedJenis = LabMap::pluck('local_code')->toArray();

            $mappedJenisList = JnsPerawatanLab::active()
                ->whereIn('kd_jenis_prw', $mappedJenis)
                ->orderBy('nm_perawatan')
                ->get(['kd_jenis_prw', 'nm_perawatan']);

            $labItems = TemplateLaboratorium::query()
                ->with('jenisPerawatan')
                ->whereIn('kd_jenis_prw', $mappedJenis)
                ->when($this->filterType, fn($q) => $q->where('kd_jenis_prw', $this->filterType))
                ->when($this->searchItem, fn($q) => $q->where('Pemeriksaan', 'like', "%{$this->searchItem}%"))
                ->orderBy('kd_jenis_prw')
                ->orderBy('urut')
                ->paginate($this->perPageItem, ['*'], 'itemPage');

            $allMappings = LabItemMap::get()->keyBy(fn($m) => $m->kd_jenis_prw . '|' . $m->id_template);

            $labItems->getCollection()->transform(function ($item) use ($allMappings) {
                $map = $allMappings->get($item->kd_jenis_prw . '|' . $item->id_template);
                $item->system_code = $map->system_code ?? null;
                $item->system_term = $map->system_term ?? null;
                return $item;
            });

            return [
                'items' => collect(),
                'labItems' => $labItems,
                'mappedJenisList' => $mappedJenisList,
                'bpjsRegistered' => collect(),
                'totalSimrs' => JnsPerawatanLab::active()->count(),
                'totalLoinc' => LabMap::count(),
                'totalBpjs' => BpjsObservationLab::count(),
                'unsyncedBpjs' => 0,
            ];
        }

        $items = JnsPerawatanLab::active()->when($this->search, fn($q) => $q->where('kd_jenis_prw', 'like', "%{$this->search}%")->orWhere('nm_perawatan', 'like', "%{$this->search}%"))->orderBy('kd_jenis_prw')->paginate($this->perPage);

        $codes = $items->pluck('kd_jenis_prw')->toArray();
        $mappings = LabMap::whereIn('local_code', $codes)->get()->keyBy('local_code');
        $specimenMappings = LabSpecimenMap::whereIn('local_code', $codes)->get()->keyBy('local_code');
        $categoryMappings = \App\Models\Mapping\DiagnosticCategoryMap::whereIn('local_code', $codes)->get()->keyBy('local_code');

        $items->getCollection()->transform(function ($item) use ($mappings, $specimenMappings, $categoryMappings) {
            $map = $mappings->get($item->kd_jenis_prw);
            $item->system_code = $map->system_code ?? null;
            $item->system_term = $map->system_term ?? null;
            $catMap = $categoryMappings->get($item->kd_jenis_prw);
            $item->diagnostic_category = $catMap->diagnostic_category ?? null;
            $item->diagnostic_category_term = $catMap->diagnostic_category_term ?? null;
            $smap = $specimenMappings->get($item->kd_jenis_prw);
            $item->specimen_code = $smap->system_code ?? null;
            $item->specimen_term = $smap->system_term ?? null;
            return $item;
        });

        $bpjsRegistered = BpjsObservationLab::pluck('id', 'local_code');
        $items->getCollection()->transform(function ($item) use ($bpjsRegistered) {
            $item->bpjs_uuid = $bpjsRegistered->get($item->kd_jenis_prw);
            return $item;
        });

        $totalBpjs = BpjsObservationLab::count();
        $totalSimrs = JnsPerawatanLab::active()->count();
        $totalLoinc = LabMap::count();

        return [
            'items' => $items,
            'labItems' => collect(),
            'mappedJenisList' => collect(),
            'bpjsRegistered' => $bpjsRegistered,
            'totalSimrs' => $totalSimrs,
            'totalLoinc' => $totalLoinc,
            'totalBpjs' => $totalBpjs,
            'unsyncedBpjs' => max(0, $totalSimrs - $totalBpjs),
        ];
    }

    // ── BPJS UUID actions ───────────────────────────────────────────────────

    public function generateBpjsUuid(string $code, string $name): void
    {
        if (BpjsObservationLab::where('local_code', $code)->exists()) {
            $this->toastWarning('Kode ini sudah memiliki UUID BPJS.');
            return;
        }
        BpjsObservationLab::create(['local_code' => $code, 'name' => $name]);
        $this->toastSuccess("UUID BPJS berhasil di-generate untuk {$code}.");
    }

    public function syncAllBpjs(): void
    {
        SyncBpjsObservationLabsJob::dispatch();
        $this->showSyncModal = false;
        $this->toastSuccess('Sync semua UUID BPJS dijadwalkan. Proses berjalan di background.');
    }

    public function viewBpjsDetail(string $code): void
    {
        $this->selectedBpjsItem = BpjsObservationLab::where('local_code', $code)->first();
        $this->showBpjsDetailModal = true;
    }

    public function confirmDeleteBpjs(string $code, string $name): void
    {
        $this->deleteBpjsCode = $code;
        $this->deleteBpjsName = $name;
        $this->showDeleteBpjsModal = true;
    }

    public function deleteBpjs(): void
    {
        BpjsObservationLab::where('local_code', $this->deleteBpjsCode)->delete();
        $this->showDeleteBpjsModal = false;
        $this->reset(['deleteBpjsCode', 'deleteBpjsName']);
        $this->toastSuccess('UUID BPJS berhasil dihapus.');
    }

    // ── Tab Jenis Pemeriksaan ──────────────────────────────────────────

    public function openModal(string $code, string $name): void
    {
        $this->selectedCode = $code;
        $this->selectedName = $name;
        $this->loincInitialSearch = LabMap::where('local_code', $code)->value('system_term') ?? '';
        $this->showModal = true;
    }

    public function openSnomedModal(string $code, string $name): void
    {
        $this->selectedSnomedCode = $code;
        $this->selectedSnomedName = $name;
        $this->snomedInitialSearch = LabSpecimenMap::where('local_code', $code)->value('system_term') ?? '';
        $this->showSnomedModal = true;
    }

    #[On('loinc-selected')]
    public function loincSelected(array $loinc): void
    {
        if ($this->showItemLoincModal) {
            LabItemMap::updateOrCreate(
                ['kd_jenis_prw' => $this->selectedType, 'id_template' => $this->selectedTemplate],
                [
                    'system_code' => $loinc['loinc_num'],
                    'system_term' => $loinc['long_common_name'] ?: $loinc['component'],
                    'system_display' => 'http://loinc.org',
                ],
            );
            $this->showItemLoincModal = false;
        } else {
            LabMap::updateOrCreate(
                ['local_code' => $this->selectedCode],
                [
                    'system_code' => $loinc['loinc_num'],
                    'system_term' => $loinc['long_common_name'] ?: $loinc['component'],
                    'system_display' => 'http://loinc.org',
                ],
            );
            $this->showModal = false;
        }

        $this->toastSuccess('Mapping LOINC berhasil disimpan', 'Sukses');
    }

    #[On('snomed-selected')]
    public function snomedSelected(string $system_code, string $system_term, string $system_display, string $category): void
    {
        LabSpecimenMap::updateOrCreate(
            ['local_code' => $this->selectedSnomedCode],
            [
                'system_code' => $system_code,
                'system_term' => $system_term,
                'system_display' => 'http://snomed.info/sct',
            ],
        );
        $this->showSnomedModal = false;
        $this->toastSuccess('Mapping SNOMED CT spesimen berhasil disimpan', 'Sukses');
    }

    public function confirmDelete(string $code, string $name): void
    {
        $this->deleteCode = $code;
        $this->deleteCodeName = $name;
        $this->showDeleteModal = true;
    }

    public function confirmDeleteSnomed(string $code, string $name): void
    {
        $this->deleteSnomedCode = $code;
        $this->deleteSnomedName = $name;
        $this->showDeleteSnomedModal = true;
    }

    public function deleteMapping(): void
    {
        LabMap::where('local_code', $this->deleteCode)->delete();
        $this->showDeleteModal = false;
        $this->toastSuccess('Mapping LOINC berhasil dihapus', 'Sukses');
        $this->reset(['deleteCode', 'deleteCodeName']);
    }

    public function deleteSnomedMapping(): void
    {
        LabSpecimenMap::where('local_code', $this->deleteSnomedCode)->delete();
        $this->showDeleteSnomedModal = false;
        $this->toastSuccess('Mapping SNOMED CT berhasil dihapus', 'Sukses');
        $this->reset(['deleteSnomedCode', 'deleteSnomedName']);
    }

    public function cancelDelete(): void
    {
        $this->showDeleteModal = false;
        $this->reset(['deleteCode', 'deleteCodeName']);
    }

    public function cancelDeleteSnomed(): void
    {
        $this->showDeleteSnomedModal = false;
        $this->reset(['deleteSnomedCode', 'deleteSnomedName']);
    }

    // ── Tab Per Item Pemeriksaan ──────────────────────────────────────────

    public function openItemLoincModal(string $kdJenisPrw, string $idTemplate, string $name): void
    {
        $this->selectedType = $kdJenisPrw;
        $this->selectedTemplate = $idTemplate;
        $this->selectedItemName = $name;
        $this->loincItemInitialSearch = LabItemMap::where('kd_jenis_prw', $kdJenisPrw)->where('id_template', $idTemplate)->value('system_term') ?? '';
        $this->showItemLoincModal = true;
    }

    public function confirmDeleteItem(string $kdJenisPrw, string $idTemplate, string $name): void
    {
        $this->deleteType = $kdJenisPrw;
        $this->deleteTemplate = $idTemplate;
        $this->deleteItemName = $name;
        $this->showDeleteItemModal = true;
    }

    public function deleteItemMapping(): void
    {
        LabItemMap::where('kd_jenis_prw', $this->deleteType)->where('id_template', $this->deleteTemplate)->delete();
        $this->showDeleteItemModal = false;
        $this->toastSuccess('Mapping berhasil dihapus', 'Sukses');
        $this->reset(['deleteType', 'deleteTemplate', 'deleteItemName']);
    }

    public function cancelDeleteItem(): void
    {
        $this->showDeleteItemModal = false;
        $this->reset(['deleteType', 'deleteTemplate', 'deleteItemName']);
    }

    public function openCategoryModal(string $code, string $name): void
    {
        $this->editCategoryCode = $code;
        $this->editCategoryName = $name;
        $this->showCategoryModal = true;
    }

    #[On('fhir-dictionary-selected')]
    public function fhirDictionarySelected(array $item): void
    {
        if (!$this->editCategoryCode) {
            return;
        }

        \App\Models\Mapping\DiagnosticCategoryMap::updateOrCreate(
            ['local_code' => $this->editCategoryCode],
            [
                'diagnostic_category' => $item['system_code'],
                'diagnostic_category_term' => $item['system_term'],
                'source' => 'lab',
            ],
        );
        $this->showCategoryModal = false;
        $this->toastSuccess("Kategori diagnostik diperbarui: {$item['system_code']} — {$item['system_term']}");
    }

    public function confirmDeleteCategory(string $code, string $name): void
    {
        $this->deleteCategoryCode = $code;
        $this->deleteCategoryName = $name;
        $this->showDeleteCategoryModal = true;
    }

    public function deleteCategoryMapping(): void
    {
        \App\Models\Mapping\DiagnosticCategoryMap::where('local_code', $this->deleteCategoryCode)->delete();
        $this->showDeleteCategoryModal = false;
        $this->toastSuccess('Mapping Diagnostic Category berhasil dihapus', 'Sukses');
        $this->reset(['deleteCategoryCode', 'deleteCategoryName']);
    }

    public function cancelDeleteCategory(): void
    {
        $this->showDeleteCategoryModal = false;
        $this->reset(['deleteCategoryCode', 'deleteCategoryName']);
    }
}; ?>

<div>
    <x-ui.page-header title="Lab — Mapping & UUID"
        subtitle="Kelola mapping LOINC/SNOMED dan UUID BPJS untuk pemeriksaan laboratorium">
        <x-slot:actions>
            <x-atoms.button wire:click="$set('showSyncModal', true)" variant="outline" icon="arrow-path">
                Sync UUID BPJS
            </x-atoms.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-3 gap-4 mb-6">
        <x-organisms.stat-card title="Total Jenis Lab" :value="number_format($totalSimrs)" icon="beaker" color="zinc" />
        <x-organisms.stat-card title="LOINC Ter-mapping" :value="number_format($totalLoinc)" icon="sparkles" color="violet"
            :subtitle="'dari ' . number_format($totalSimrs) . ' total'" />
        <x-organisms.stat-card title="UUID BPJS" :value="number_format($totalBpjs)" icon="identification" color="blue"
            :subtitle="'dari ' . number_format($totalSimrs) . ' total'" />
    </div>

    {{-- Tab Navigation --}}
    <div
        class="mb-5 overflow-hidden bg-white border rounded-2xl dark:bg-primary-dark-800/60 border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="p-3">
            <div class="flex gap-1 p-1 rounded-xl bg-zinc-100 dark:bg-primary-dark-900/60">
                <button wire:click="$set('activeTab', 'jenis')"
                    class="flex flex-1 items-center justify-center gap-2 px-4 py-2 text-sm font-semibold rounded-lg transition-all duration-200
                        {{ $activeTab === 'jenis'
                            ? 'bg-white dark:bg-primary-dark-700 text-primary-700 dark:text-primary-300 shadow-sm ring-1 ring-zinc-200/70 dark:ring-primary-dark-600/50'
                            : 'text-zinc-500 dark:text-primary-dark-400 hover:text-zinc-700 dark:hover:text-primary-dark-200' }}">
                    <flux:icon name="beaker" class="w-4 h-4" />
                    Jenis Pemeriksaan
                </button>
                <button wire:click="$set('activeTab', 'item')"
                    class="flex flex-1 items-center justify-center gap-2 px-4 py-2 text-sm font-semibold rounded-lg transition-all duration-200
                        {{ $activeTab === 'item'
                            ? 'bg-white dark:bg-primary-dark-700 text-primary-700 dark:text-primary-300 shadow-sm ring-1 ring-zinc-200/70 dark:ring-primary-dark-600/50'
                            : 'text-zinc-500 dark:text-primary-dark-400 hover:text-zinc-700 dark:hover:text-primary-dark-200' }}">
                    <flux:icon name="list-bullet" class="w-4 h-4" />
                    Per Item Pemeriksaan
                </button>
            </div>
        </div>
    </div>

    {{-- Tab: Jenis Pemeriksaan --}}
    @if ($activeTab === 'jenis')
        @php
            $mappedCount = collect($items->items())->filter(fn($i) => $i->system_code)->count();
        @endphp

        <x-organisms.data-panel>
            <x-slot:filter>
                <div class="mb-4 flex flex-wrap items-center gap-3">
                    <div class="flex-1 min-w-[200px]">
                        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                            placeholder="Cari kode atau nama pemeriksaan lab..." clearable />
                    </div>
                    <flux:select wire:model.live="perPage" class="w-40 shrink-0">
                        <flux:select.option value="25">25 / halaman</flux:select.option>
                        <flux:select.option value="50">50 / halaman</flux:select.option>
                        <flux:select.option value="100">100 / halaman</flux:select.option>
                    </flux:select>
                    <div
                        class="hidden sm:flex items-center gap-3 px-3.5 py-2 text-xs font-medium bg-white dark:bg-primary-dark-800 rounded-xl border border-zinc-200 dark:border-primary-dark-700 whitespace-nowrap shadow-sm">
                        <span class="flex items-center gap-1.5 text-teal-600 dark:text-teal-400">
                            <span class="inline-block w-2 h-2 rounded-full bg-teal-400 dark:bg-teal-500"></span>
                            {{ $mappedCount }} LOINC
                        </span>
                        <span class="text-zinc-300 dark:text-primary-dark-600">/</span>
                        <span class="text-zinc-500 dark:text-primary-dark-400">{{ $items->count() }} di halaman
                            ini</span>
                    </div>
                </div>
            </x-slot:filter>

            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading class="w-36">Kode Lokal</x-atoms.table-heading>
                    <x-atoms.table-heading>Nama Pemeriksaan</x-atoms.table-heading>
                    <x-atoms.table-heading>
                        <span class="inline-flex items-center gap-1">
                            <span class="w-2 h-2 rounded-full bg-teal-400"></span>
                            Mapping LOINC
                            <span
                                class="text-zinc-300 dark:text-primary-dark-600 font-normal normal-case italic">panel</span>
                        </span>
                    </x-atoms.table-heading>
                    <x-atoms.table-heading>
                        <span class="inline-flex items-center gap-1">
                            <span class="w-2 h-2 rounded-full bg-violet-400"></span>
                            SNOMED CT
                            <span
                                class="text-zinc-300 dark:text-primary-dark-600 font-normal normal-case italic">spesimen</span>
                        </span>
                    </x-atoms.table-heading>
                    <x-atoms.table-heading class="w-44">
                        <span class="inline-flex items-center gap-1">
                            <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                            Diagnostic Category
                        </span>
                    </x-atoms.table-heading>
                    <x-atoms.table-heading>
                        <span class="inline-flex items-center gap-1">
                            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                            UUID BPJS
                        </span>
                    </x-atoms.table-heading>
                    <x-atoms.table-heading align="center" class="w-44">Aksi</x-atoms.table-heading>
                </x-slot:headings>

                @forelse ($items as $item)
                    <x-molecules.table-row wire:key="lab-{{ $item->kd_jenis_prw }}">
                        <x-atoms.table-cell>
                            <span
                                class="inline-block font-mono text-xs font-bold px-2 py-1 rounded-md bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300 ring-1 ring-primary-100 dark:ring-primary-800/40">
                                {{ $item->kd_jenis_prw }}
                            </span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            <p class="text-sm font-medium text-zinc-800 dark:text-primary-dark-100 leading-snug">
                                {{ $item->nm_perawatan }}
                            </p>
                        </x-atoms.table-cell>
                        {{-- LOINC --}}
                        <x-atoms.table-cell>
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 group/loinc">
                                @if ($item->system_code)
                                    <div class="flex items-start gap-2.5 min-w-0">
                                        <span
                                            class="mt-1 w-2 h-2 rounded-full bg-teal-400 dark:bg-teal-500 shrink-0 ring-2 ring-teal-100 dark:ring-teal-900/50"></span>
                                        <div class="min-w-0">
                                            <p class="font-mono text-xs font-bold text-teal-700 dark:text-teal-400">
                                                {{ $item->system_code }}</p>
                                            <p
                                                class="mt-0.5 text-xs text-zinc-500 dark:text-primary-dark-400 leading-snug line-clamp-2">
                                                {{ $item->system_term }}</p>
                                        </div>
                                    </div>
                                @else
                                    <div class="flex items-center gap-2 text-zinc-400 dark:text-primary-dark-500">
                                        <span class="w-2 h-2 rounded-full bg-zinc-200 dark:bg-primary-dark-600"></span>
                                        <span class="text-xs italic">Belum di-mapping</span>
                                    </div>
                                @endif
                                <div class="flex items-center gap-1 shrink-0">
                                    <x-atoms.button
                                        wire:click="openModal('{{ $item->kd_jenis_prw }}', '{{ addslashes($item->nm_perawatan) }}')"
                                        size="sm" icon="{{ $item->system_code ? 'pencil-square' : 'plus' }}"
                                        variant="ghost"
                                        class="opacity-0 group-hover/loinc:opacity-100 transition-all duration-150"
                                        tooltip="{{ $item->system_code ? 'Ubah LOINC' : 'Petakan LOINC' }}" />
                                </div>
                            </div>
                        </x-atoms.table-cell>
                        {{-- SNOMED Spesimen --}}
                        <x-atoms.table-cell>
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 group/snomed">
                                @if ($item->specimen_code)
                                    <div class="flex items-start gap-2.5">
                                        <span
                                            class="mt-1 w-2 h-2 rounded-full bg-violet-400 dark:bg-violet-500 shrink-0 ring-2 ring-violet-100 dark:ring-violet-900/50"></span>
                                        <div class="min-w-0">
                                            <p class="font-mono text-xs font-bold text-violet-700 dark:text-violet-400">
                                                {{ $item->specimen_code }}</p>
                                            <p
                                                class="mt-0.5 text-xs text-zinc-500 dark:text-primary-dark-400 leading-snug line-clamp-2">
                                                {{ $item->specimen_term }}</p>
                                        </div>
                                    </div>
                                @else
                                    <div class="flex items-center gap-2 text-zinc-400 dark:text-primary-dark-500">
                                        <span class="w-2 h-2 rounded-full bg-zinc-200 dark:bg-primary-dark-600"></span>
                                        <span class="text-xs italic">Belum di-mapping</span>
                                    </div>
                                @endif
                                <div class="flex items-center gap-1 shrink-0">
                                    <x-atoms.button
                                        wire:click="openSnomedModal('{{ $item->kd_jenis_prw }}', '{{ addslashes($item->nm_perawatan) }}')"
                                        size="sm" icon="{{ $item->specimen_code ? 'pencil-square' : 'plus' }}"
                                        variant="ghost"
                                        class="opacity-0 group-hover/snomed:opacity-100 transition-all duration-150"
                                        tooltip="{{ $item->specimen_code ? 'Ubah SNOMED' : 'Petakan SNOMED' }}" />
                                </div>
                            </div>
                        </x-atoms.table-cell>
                        {{-- Diagnostic Category --}}
                        <x-atoms.table-cell>
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 group/cat">
                                @if ($item->diagnostic_category)
                                    <div class="flex items-start gap-2.5">
                                        <span
                                            class="mt-1 w-2 h-2 rounded-full bg-amber-400 dark:bg-amber-500 shrink-0 ring-2 ring-amber-100 dark:ring-amber-900/50"></span>
                                        <div class="min-w-0">
                                            <p class="font-mono text-xs font-bold text-amber-700 dark:text-amber-400">
                                                {{ $item->diagnostic_category }}</p>
                                            <p
                                                class="mt-0.5 text-xs text-zinc-500 dark:text-primary-dark-400 leading-snug line-clamp-2">
                                                {{ $item->diagnostic_category_term }}</p>
                                        </div>
                                    </div>
                                @else
                                    <div class="flex items-center gap-2 text-zinc-400 dark:text-primary-dark-500">
                                        <span class="w-2 h-2 rounded-full bg-zinc-200 dark:bg-primary-dark-600"></span>
                                        <span class="text-xs italic">Belum di-mapping</span>
                                    </div>
                                @endif
                                <div class="flex items-center gap-1 shrink-0">
                                    <x-atoms.button
                                        wire:click="openCategoryModal('{{ $item->kd_jenis_prw }}', '{{ addslashes($item->nm_perawatan) }}')"
                                        size="sm"
                                        icon="{{ $item->diagnostic_category ? 'pencil-square' : 'plus' }}"
                                        variant="ghost"
                                        class="opacity-0 group-hover/cat:opacity-100 transition-all duration-150"
                                        tooltip="{{ $item->diagnostic_category ? 'Ubah Diagnostic Category' : 'Petakan Diagnostic Category' }}" />
                                </div>
                            </div>
                        </x-atoms.table-cell>
                        {{-- UUID BPJS --}}
                        <x-atoms.table-cell>
                            @if ($item->bpjs_uuid)
                                <span
                                    class="font-mono text-xs font-bold text-emerald-700 dark:text-emerald-400">{{ $item->bpjs_uuid }}</span>
                            @else
                                <span class="text-xs italic text-zinc-400 dark:text-primary-dark-500">Belum
                                    terdaftar</span>
                            @endif
                        </x-atoms.table-cell>
                        {{-- Aksi --}}
                        <x-atoms.table-cell :action="true" align="center">
                            <div class="flex items-center justify-center gap-1">
                                {{-- Grup LOINC/SNOMED --}}
                                <div
                                    class="flex items-center gap-0.5 border-r border-zinc-200 dark:border-primary-dark-600 pr-2 mr-1">
                                    @if ($item->system_code || $item->specimen_code || $item->diagnostic_category)
                                        <flux:dropdown position="bottom right">
                                            <x-atoms.button variant="ghost" icon="trash" size="sm"
                                                class="text-red-500" tooltip="Hapus mapping" />
                                            <flux:navmenu>
                                                @if ($item->system_code)
                                                    <flux:navmenu.item
                                                        wire:click="confirmDelete('{{ $item->kd_jenis_prw }}', '{{ addslashes($item->nm_perawatan) }}')"
                                                        class="text-red-500 hover:bg-red-50 dark:hover:bg-red-900/40">
                                                        Hapus LOINC</flux:navmenu.item>
                                                @endif
                                                @if ($item->specimen_code)
                                                    <flux:navmenu.item
                                                        wire:click="confirmDeleteSnomed('{{ $item->kd_jenis_prw }}', '{{ addslashes($item->nm_perawatan) }}')"
                                                        class="text-red-500 hover:bg-red-50 dark:hover:bg-red-900/40">
                                                        Hapus SNOMED</flux:navmenu.item>
                                                @endif
                                                @if ($item->diagnostic_category)
                                                    <flux:navmenu.item
                                                        wire:click="confirmDeleteCategory('{{ $item->kd_jenis_prw }}', '{{ addslashes($item->nm_perawatan) }}')"
                                                        class="text-red-500 hover:bg-red-50 dark:hover:bg-red-900/40">
                                                        Hapus Category</flux:navmenu.item>
                                                @endif
                                            </flux:navmenu>
                                        </flux:dropdown>
                                    @endif
                                </div>
                                {{-- Grup BPJS UUID --}}
                                <div class="flex items-center gap-0.5">
                                    @if (!$item->bpjs_uuid)
                                        <x-atoms.button
                                            wire:click="generateBpjsUuid('{{ $item->kd_jenis_prw }}', '{{ addslashes($item->nm_perawatan) }}')"
                                            size="sm" variant="ghost" icon="plus-circle"
                                            class="text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/20"
                                            tooltip="Generate UUID BPJS" />
                                    @else
                                        <x-atoms.button variant="ghost"
                                            wire:click="viewBpjsDetail('{{ $item->kd_jenis_prw }}')" size="sm"
                                            icon="eye" tooltip="Lihat UUID BPJS" />
                                        <x-atoms.button variant="ghost"
                                            wire:click="confirmDeleteBpjs('{{ $item->kd_jenis_prw }}', '{{ addslashes($item->nm_perawatan) }}')"
                                            size="sm" icon="trash" tooltip="Hapus UUID BPJS"
                                            class="text-red-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20" />
                                    @endif
                                </div>
                            </div>
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-16 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <div
                                    class="flex items-center justify-center w-14 h-14 rounded-2xl bg-zinc-100 dark:bg-primary-dark-700">
                                    <flux:icon name="beaker"
                                        class="w-7 h-7 text-zinc-300 dark:text-primary-dark-500" />
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-zinc-500 dark:text-primary-dark-400">Tidak ada
                                        data pemeriksaan lab</p>
                                    <p class="mt-0.5 text-xs text-zinc-400 dark:text-primary-dark-500">Coba ubah kata
                                        kunci pencarian</p>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </x-organisms.table>

            @if ($items->hasPages())
                <x-slot:footer>{{ $items->links() }}</x-slot:footer>
            @endif
        </x-organisms.data-panel>
    @endif

    {{-- Tab: Per Item Pemeriksaan --}}
    @if ($activeTab === 'item')
        @php
            $mappedItemCount = collect($labItems->items())->filter(fn($i) => $i->system_code)->count();
        @endphp

        <x-organisms.data-panel>
            <x-slot:filter>
                <div class="mb-4 flex flex-wrap items-center gap-3">
                    <div class="w-64 shrink-0">
                        <flux:select wire:model.live="filterType" placeholder="Semua Jenis Pemeriksaan">
                            <flux:select.option value="">Semua Jenis Pemeriksaan</flux:select.option>
                            @foreach ($mappedJenisList as $jenis)
                                <flux:select.option value="{{ $jenis->kd_jenis_prw }}">{{ $jenis->nm_perawatan }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <div class="flex-1 min-w-[200px]">
                        <flux:input wire:model.live.debounce.300ms="searchItem" icon="magnifying-glass"
                            placeholder="Cari nama item..." clearable />
                    </div>
                    <flux:select wire:model.live="perPageItem" class="w-40 shrink-0">
                        <flux:select.option value="25">25 / halaman</flux:select.option>
                        <flux:select.option value="50">50 / halaman</flux:select.option>
                        <flux:select.option value="100">100 / halaman</flux:select.option>
                    </flux:select>
                    <div
                        class="hidden sm:flex items-center gap-3 px-3.5 py-2 text-xs font-medium bg-white dark:bg-primary-dark-800 rounded-xl border border-zinc-200 dark:border-primary-dark-700 whitespace-nowrap shadow-sm">
                        <span class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400">
                            <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 dark:bg-emerald-500"></span>
                            {{ $mappedItemCount }} ter-mapping
                        </span>
                        <span class="text-zinc-300 dark:text-primary-dark-600">/</span>
                        <span class="text-zinc-500 dark:text-primary-dark-400">{{ $labItems->count() }} di halaman
                            ini</span>
                    </div>
                </div>
            </x-slot:filter>

            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading class="w-32">Kode Jenis</x-atoms.table-heading>
                    <x-atoms.table-heading>Jenis Pemeriksaan</x-atoms.table-heading>
                    <x-atoms.table-heading>Item Pemeriksaan</x-atoms.table-heading>
                    <x-atoms.table-heading>Mapping LOINC</x-atoms.table-heading>
                    <x-atoms.table-heading align="center" class="w-28">Aksi</x-atoms.table-heading>
                </x-slot:headings>

                @forelse ($labItems as $item)
                    <x-molecules.table-row wire:key="{{ $item->kd_jenis_prw }}-{{ $item->id_template }}">
                        <x-atoms.table-cell>
                            <span
                                class="inline-block font-mono text-xs font-bold px-2 py-1 rounded-md bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300 ring-1 ring-primary-100 dark:ring-primary-800/40">
                                {{ $item->kd_jenis_prw }}
                            </span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            <p class="text-xs text-zinc-500 dark:text-primary-dark-400">
                                {{ $item->jenisPerawatan?->nm_perawatan ?? $item->kd_jenis_prw }}
                            </p>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            <p class="text-sm font-medium text-zinc-800 dark:text-primary-dark-100 leading-snug">
                                {{ $item->Pemeriksaan }}
                            </p>
                            <div
                                class="flex flex-wrap items-center gap-1.5 mt-1 text-[11px] text-zinc-400 dark:text-primary-dark-500 font-medium">
                                <span
                                    class="inline-flex items-center px-1.5 py-0.5 rounded-md bg-zinc-100 dark:bg-primary-dark-700 text-zinc-600 dark:text-primary-dark-300 font-mono text-[10px]">
                                    ID: {{ $item->id_template }}
                                </span>
                                @if ($item->satuan)
                                    <span class="text-zinc-300 dark:text-primary-dark-600">•</span>
                                    <span>{{ $item->satuan }}</span>
                                @endif
                            </div>
                        </x-atoms.table-cell>
                        {{-- Mapping LOINC --}}
                        <x-atoms.table-cell>
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 group/loinc">
                                @if ($item->system_code)
                                    <div class="flex items-start gap-2.5">
                                        <span
                                            class="mt-1 w-2 h-2 rounded-full bg-emerald-400 dark:bg-emerald-500 shrink-0 ring-2 ring-emerald-100 dark:ring-emerald-900/50"></span>
                                        <div class="min-w-0">
                                            <p
                                                class="font-mono text-xs font-bold text-emerald-700 dark:text-emerald-400">
                                                {{ $item->system_code }}</p>
                                            <p
                                                class="mt-0.5 text-xs text-zinc-500 dark:text-primary-dark-400 leading-snug line-clamp-2">
                                                {{ $item->system_term }}</p>
                                        </div>
                                    </div>
                                @else
                                    <div class="flex items-center gap-2 text-zinc-400 dark:text-primary-dark-500">
                                        <span class="w-2 h-2 rounded-full bg-zinc-200 dark:bg-primary-dark-600"></span>
                                        <span class="text-xs italic">Belum di-mapping</span>
                                    </div>
                                @endif
                                <div class="flex items-center gap-1 shrink-0">
                                    <x-atoms.button
                                        wire:click="openItemLoincModal('{{ $item->kd_jenis_prw }}', '{{ $item->id_template }}', '{{ addslashes($item->Pemeriksaan) }}')"
                                        size="sm" icon="{{ $item->system_code ? 'pencil-square' : 'plus' }}"
                                        variant="ghost"
                                        class="opacity-0 group-hover/loinc:opacity-100 transition-all duration-150"
                                        tooltip="{{ $item->system_code ? 'Ubah LOINC' : 'Petakan LOINC' }}" />
                                </div>
                            </div>
                        </x-atoms.table-cell>
                        {{-- Aksi --}}
                        <x-atoms.table-cell :action="true" align="center">
                            @if ($item->system_code)
                                <x-atoms.button
                                    wire:click="confirmDeleteItem('{{ $item->kd_jenis_prw }}', '{{ $item->id_template }}', '{{ addslashes($item->Pemeriksaan) }}')"
                                    size="sm" icon="trash" variant="ghost"
                                    class="text-red-500 opacity-0 group-hover:opacity-100 transition-opacity duration-150"
                                    tooltip="Hapus" />
                            @else
                                <span class="text-xs text-zinc-300 dark:text-primary-dark-600">—</span>
                            @endif
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-16 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <div
                                    class="flex items-center justify-center w-14 h-14 rounded-2xl bg-zinc-100 dark:bg-primary-dark-700">
                                    <flux:icon name="list-bullet"
                                        class="w-7 h-7 text-zinc-300 dark:text-primary-dark-500" />
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-zinc-500 dark:text-primary-dark-400">Tidak ada
                                        item pemeriksaan</p>
                                    <p class="mt-0.5 text-xs text-zinc-400 dark:text-primary-dark-500">Coba ubah kata
                                        kunci pencarian</p>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </x-organisms.table>

            @if ($labItems->hasPages())
                <x-slot:footer>{{ $labItems->links() }}</x-slot:footer>
            @endif
        </x-organisms.data-panel>
    @endif

    {{-- Modal Pencarian LOINC --}}
    <x-organisms.modal wire:model="showModal" maxWidth="6xl" title="Pilih Kode LOINC" :description="'Untuk: ' . ($selectedName ?? '') . ' (' . ($selectedCode ?? '') . ')'">
        <livewire:components.loinc-search :limitClasses="['CHEM', 'HEM/BC', 'MICRO', 'SERO', 'COAG', 'UA', 'DRUG/TOX', 'PATH']" :initialSearch="$loincInitialSearch" :key="'loinc-lab-' . ($selectedCode ?? '')" />
        <x-slot:footer>
            <div class="flex justify-end w-full">
                <x-atoms.button wire:click="$set('showModal', false)" variant="ghost">Tutup</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal Pencarian SNOMED CT (Spesimen Jenis Pemeriksaan) --}}
    <x-organisms.modal wire:model="showSnomedModal" maxWidth="6xl" title="Pilih Kode SNOMED CT — Jenis Spesimen"
        :description="'Untuk: ' . ($selectedSnomedName ?? '') . ' (' . ($selectedSnomedCode ?? '') . ')'">
        <div class="mb-4 flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full bg-violet-400"></span>
            <span class="text-xs text-violet-600 dark:text-violet-400">Pilih jenis spesimen (contoh: Blood specimen,
                Urine specimen, Serum specimen)</span>
        </div>
        <livewire:components.snomed-search defaultTag="specimen" :initialSearch="$snomedInitialSearch" :key="'snomed-lab-' . ($selectedSnomedCode ?? '')" />
        <x-slot:footer>
            <div class="flex justify-end w-full">
                <x-atoms.button wire:click="$set('showSnomedModal', false)" variant="ghost">Tutup</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal Pencarian LOINC (Per Item Pemeriksaan) --}}
    <x-organisms.modal wire:model="showItemLoincModal" maxWidth="6xl" title="Pilih Kode LOINC" :description="'Untuk: ' .
        ($selectedItemName ?? '') .
        ' (' .
        ($selectedType ?? '') .
        ' / ' .
        ($selectedTemplate ?? '') .
        ')'">
        <livewire:components.loinc-search :limitClasses="['CHEM', 'HEM/BC', 'MICRO', 'SERO', 'COAG', 'UA', 'DRUG/TOX', 'PATH']" :initialSearch="$loincItemInitialSearch" :key="'loinc-item-' . ($selectedType ?? '') . '-' . ($selectedTemplate ?? '')" />
        <x-slot:footer>
            <div class="flex justify-end w-full">
                <x-atoms.button wire:click="$set('showItemLoincModal', false)" variant="ghost">Tutup</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal Konfirmasi Hapus (Jenis Pemeriksaan) --}}
    <x-organisms.modal wire:model="showDeleteModal" maxWidth="sm" title="Hapus Mapping LOINC"
        description="Tindakan ini tidak dapat dibatalkan.">
        <div class="flex items-center gap-4 mb-6">
            <div class="flex items-center justify-center w-12 h-12 rounded-2xl bg-red-100 dark:bg-red-900/30 shrink-0">
                <flux:icon name="exclamation-triangle" class="w-6 h-6 text-red-600 dark:text-red-400" />
            </div>
            <div
                class="p-4 space-y-2 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/40 border border-zinc-200 dark:border-primary-dark-700 w-full">
                <div class="flex items-center gap-3">
                    <span class="text-xs font-medium text-zinc-400 w-10 shrink-0">Kode</span>
                    <span
                        class="font-mono text-sm font-bold text-primary-600 dark:text-primary-400">{{ $deleteCode }}</span>
                </div>
                <div class="flex items-start gap-3">
                    <span class="text-xs font-medium text-zinc-400 w-10 shrink-0 mt-0.5">Nama</span>
                    <span class="text-sm text-zinc-700 dark:text-primary-dark-300">{{ $deleteCodeName }}</span>
                </div>
            </div>
        </div>
        <x-slot:footer>
            <div class="flex justify-end gap-2 w-full">
                <x-atoms.button wire:click="cancelDelete" variant="ghost">Batal</x-atoms.button>
                <x-atoms.button wire:click="deleteMapping" variant="danger">Hapus Mapping</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal Konfirmasi Hapus (Item Pemeriksaan) --}}
    <x-organisms.modal wire:model="showDeleteItemModal" maxWidth="sm" title="Hapus Mapping Item LOINC"
        description="Tindakan ini tidak dapat dibatalkan.">
        <div class="flex items-center gap-4 mb-6">
            <div class="flex items-center justify-center w-12 h-12 rounded-2xl bg-red-100 dark:bg-red-900/30 shrink-0">
                <flux:icon name="exclamation-triangle" class="w-6 h-6 text-red-600 dark:text-red-400" />
            </div>
            <div
                class="p-4 space-y-2 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/40 border border-zinc-200 dark:border-primary-dark-700 w-full">
                <div class="flex items-center gap-3">
                    <span class="text-xs font-medium text-zinc-400 w-16 shrink-0">Jenis</span>
                    <span
                        class="font-mono text-sm font-bold text-primary-600 dark:text-primary-400">{{ $deleteType }}</span>
                </div>
                <div class="flex items-start gap-3">
                    <span class="text-xs font-medium text-zinc-400 w-16 shrink-0 mt-0.5">Item</span>
                    <span class="text-sm text-zinc-700 dark:text-primary-dark-300">{{ $deleteItemName }}</span>
                </div>
            </div>
        </div>
        <x-slot:footer>
            <div class="flex justify-end gap-2 w-full">
                <x-atoms.button wire:click="cancelDeleteItem" variant="ghost">Batal</x-atoms.button>
                <x-atoms.button wire:click="deleteItemMapping" variant="danger">Hapus Mapping</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal Konfirmasi Hapus SNOMED CT (Jenis Pemeriksaan) --}}
    <x-organisms.modal wire:model="showDeleteSnomedModal" maxWidth="sm" title="Hapus Mapping SNOMED CT"
        description="Tindakan ini tidak dapat dibatalkan.">
        <div class="flex items-center gap-4 mb-6">
            <div
                class="flex items-center justify-center w-12 h-12 rounded-2xl bg-violet-100 dark:bg-violet-900/30 shrink-0">
                <flux:icon name="exclamation-triangle" class="w-6 h-6 text-violet-600 dark:text-violet-400" />
            </div>
            <div
                class="p-4 space-y-2 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/40 border border-zinc-200 dark:border-primary-dark-700 w-full">
                <div class="flex items-center gap-3">
                    <span class="text-xs font-medium text-zinc-400 w-10 shrink-0">Kode</span>
                    <span
                        class="font-mono text-sm font-bold text-violet-600 dark:text-violet-400">{{ $deleteSnomedCode }}</span>
                </div>
                <div class="flex items-start gap-3">
                    <span class="text-xs font-medium text-zinc-400 w-10 shrink-0 mt-0.5">Nama</span>
                    <span class="text-sm text-zinc-700 dark:text-primary-dark-300">{{ $deleteSnomedName }}</span>
                </div>
            </div>
        </div>
        <x-slot:footer>
            <div class="flex justify-end gap-2 w-full">
                <x-atoms.button wire:click="cancelDeleteSnomed" variant="ghost">Batal</x-atoms.button>
                <x-atoms.button wire:click="deleteSnomedMapping" variant="danger">Hapus Mapping
                    SNOMED</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal Sync UUID BPJS --}}
    <x-organisms.modal wire:model="showSyncModal" maxWidth="sm" title="Sync Semua UUID BPJS Lab"
        description="Proses generate massal untuk pemeriksaan yang belum terdaftar.">
        <div class="space-y-5">
            <div class="flex items-center gap-4">
                <div
                    class="flex items-center justify-center w-12 h-12 rounded-full bg-primary-100 dark:bg-primary-900/30 shrink-0">
                    <flux:icon name="arrow-path" class="w-6 h-6 text-primary-600 dark:text-primary-400" />
                </div>
                <p class="text-sm text-zinc-500 dark:text-primary-dark-400">
                    {{ number_format($unsyncedBpjs) }} pemeriksaan lab belum memiliki UUID BPJS
                </p>
            </div>
            <div
                class="p-3.5 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-[11px] text-amber-800 dark:text-amber-200 leading-relaxed italic">
                <flux:icon name="exclamation-triangle" class="inline w-3.5 h-3.5 mr-1 text-amber-500" />
                UUID baru akan di-generate untuk semua jenis pemeriksaan yang belum terdaftar. Proses berjalan di
                background.
            </div>
        </div>
        <x-slot:footer>
            <div class="flex justify-end gap-3 w-full">
                <x-atoms.button variant="ghost" wire:click="$set('showSyncModal', false)">Batal</x-atoms.button>
                <x-atoms.button variant="primary" icon="arrow-path" wire:click="syncAllBpjs">Mulai
                    Sync</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal Detail UUID BPJS --}}
    <x-organisms.modal wire:model="showBpjsDetailModal" maxWidth="sm" title="Detail UUID BPJS Lab">
        @if ($selectedBpjsItem)
            <dl class="space-y-4">
                <div>
                    <dt class="text-[10px] uppercase font-bold text-zinc-400 dark:text-primary-dark-500 mb-1">Nama
                        Pemeriksaan</dt>
                    <dd class="text-sm font-semibold text-zinc-800 dark:text-white">{{ $selectedBpjsItem->name }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] uppercase font-bold text-zinc-400 dark:text-primary-dark-500 mb-1">Kode
                        Lokal</dt>
                    <dd class="font-mono text-sm font-bold text-primary-600 dark:text-primary-400">
                        {{ $selectedBpjsItem->local_code }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] uppercase font-bold text-zinc-400 dark:text-primary-dark-500 mb-1">Resource
                        ID (UUID)</dt>
                    <dd class="font-mono text-sm font-bold text-emerald-600 dark:text-emerald-400 break-all">
                        {{ $selectedBpjsItem->id }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] uppercase font-bold text-zinc-400 dark:text-primary-dark-500 mb-1">Dibuat
                    </dt>
                    <dd class="text-sm text-zinc-600 dark:text-primary-dark-300">
                        {{ $selectedBpjsItem->created_at?->format('d M Y, H:i') }}</dd>
                </div>
            </dl>
        @endif
        <x-slot:footer>
            <div class="flex justify-end w-full">
                <x-atoms.button variant="ghost" wire:click="$set('showBpjsDetailModal', false)">Tutup</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal Hapus UUID BPJS --}}
    <x-organisms.modal wire:model="showDeleteBpjsModal" maxWidth="sm" title="Hapus UUID BPJS?"
        description="Tindakan ini tidak disarankan jika sudah sinkronisasi.">
        <div class="flex items-center gap-4 mb-6">
            <div class="flex items-center justify-center w-12 h-12 rounded-2xl bg-red-50 dark:bg-red-900/20 shrink-0">
                <flux:icon name="trash" class="w-6 h-6 text-red-600 dark:text-red-400" />
            </div>
            <div>
                <span class="text-sm text-zinc-600 dark:text-zinc-400">UUID BPJS untuk <strong
                        class="text-zinc-800 dark:text-white">{{ $deleteBpjsCode }}</strong> akan dihapus.</span>
            </div>
        </div>
        <div
            class="p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-[11px] text-amber-700 dark:text-amber-300 leading-relaxed italic">
            <flux:icon name="exclamation-triangle" class="inline w-3 h-3 mr-1" />
            UUID yang sudah digunakan di bundle BPJS tidak boleh dihapus untuk menjaga konsistensi data.
        </div>
        <x-slot:footer>
            <div class="flex justify-end gap-3 w-full">
                <x-atoms.button variant="ghost" wire:click="$set('showDeleteBpjsModal', false)">Batal</x-atoms.button>
                <x-atoms.button variant="danger" wire:click="deleteBpjs" icon="trash">Hapus UUID</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal Diagnostic Category --}}
    <x-organisms.modal wire:model="showCategoryModal" maxWidth="4xl" title="Pilih Diagnostic Category"
        :description="'Untuk: ' . ($editCategoryName ?? '') . ' (' . ($editCategoryCode ?? '') . ')'">
        <livewire:components.fhir-dictionaries-search :limitSources="['hl7']" initialSource="hl7" :limitTypes="['diagnostic-category']"
            initialType="diagnostic-category" :key="'cat-lab-' . ($editCategoryCode ?? '')" />
        <x-slot:footer>
            <div class="flex justify-end w-full">
                <x-atoms.button wire:click="$set('showCategoryModal', false)" variant="ghost">Tutup</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal Konfirmasi Hapus Category --}}
    <x-organisms.modal wire:model="showDeleteCategoryModal" maxWidth="sm" title="Hapus Mapping Category"
        description="Tindakan ini tidak dapat dibatalkan.">
        <div class="flex items-center gap-4 mb-6">
            <div
                class="flex items-center justify-center w-12 h-12 rounded-2xl bg-amber-100 dark:bg-amber-900/30 shrink-0">
                <flux:icon name="exclamation-triangle" class="w-6 h-6 text-amber-600 dark:text-amber-400" />
            </div>
            <div
                class="p-4 space-y-2 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/40 border border-zinc-200 dark:border-primary-dark-700 w-full">
                <div class="flex items-center gap-3">
                    <span class="text-xs font-medium text-zinc-400 w-10 shrink-0">Kode</span>
                    <span
                        class="font-mono text-sm font-bold text-amber-600 dark:text-amber-400">{{ $deleteCategoryCode }}</span>
                </div>
                <div class="flex items-start gap-3">
                    <span class="text-xs font-medium text-zinc-400 w-10 shrink-0 mt-0.5">Nama</span>
                    <span class="text-sm text-zinc-700 dark:text-primary-dark-300">{{ $deleteCategoryName }}</span>
                </div>
            </div>
        </div>
        <x-slot:footer>
            <div class="flex justify-end gap-2 w-full">
                <x-atoms.button wire:click="cancelDeleteCategory" variant="ghost">Batal</x-atoms.button>
                <x-atoms.button wire:click="deleteCategoryMapping" variant="danger">Hapus Mapping
                    Category</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>
</div>
