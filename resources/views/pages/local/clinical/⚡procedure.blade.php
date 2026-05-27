<?php

use App\Jobs\SyncBpjsProceduresJob;
use App\Models\Bpjs\BpjsProcedure;
use App\Models\Mapping\ProcedureMap;
use App\Models\Simrs\JnsPerawatan;
use App\Models\Simrs\JnsPerawatanInap;
use App\Traits\WithSmartMapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('Tindakan — Mapping & UUID')] class extends Component {
    use WithPagination, WithSmartMapping;

    /**
     * Nilai activeTab: jalan, jalan_dr, jalan_pr, jalan_drpr,
     *                   inap, inap_dr, inap_pr, inap_drpr
     */
    #[Url(as: 'tab')]
    public string $activeTab = 'jalan';

    #[Url(as: 'search')]
    public string $search = '';

    // BPJS UUID
    public bool $showSyncModal = false;
    public bool $showBpjsDetailModal = false;
    public ?BpjsProcedure $selectedBpjsItem = null;
    public bool $showDeleteBpjsModal = false;
    public ?string $deleteBpjsCode = null;
    public string $deleteBpjsName = '';

    // State modal pencarian SNOMED
    public bool $showSnomedSearchModal = false;
    public ?string $selectedProcedureCode = null;
    public ?string $selectedProcedureName = null;

    // State konfirmasi hapus
    public bool $showDeleteModal = false;
    public ?string $deleteCode = null;
    public ?string $deleteName = null;

    // State modal kategori
    public bool $showCategoryModal = false;
    public ?string $categoryTargetCode = null;
    public string $categoryCode = '';
    public string $categoryDisplay = '';

    public string $snomedSemanticTag = 'procedure';
    public string $snomedInitialSearch = '';

    private function getBpjsType(): string
    {
        return str_starts_with($this->activeTab, 'inap') ? 'ranap' : 'ralan';
    }

    protected function saveSmartMapping(string $sourceCode, string $snomedCode, string $snomedTerm): bool
    {
        ProcedureMap::updateOrCreate(
            ['source_table' => $this->activeTab, 'procedure_code' => $sourceCode],
            [
                'system_code' => $snomedCode,
                'system_term' => $snomedTerm,
                'system_display' => 'http://snomed.info/sct',
            ],
        );
        return true;
    }

    protected function getUnmappedItemsForPage(int $page, bool $forAiQueue = false): array
    {
        $txTable = self::TX_TABLE_MAP[$this->activeTab] ?? null;
        $isInap = str_starts_with($this->activeTab, 'inap');
        $modelClass = $isInap ? JnsPerawatanInap::class : JnsPerawatan::class;

        $items = $modelClass
            ::query()
            ->when($txTable, fn($q) => $q->whereIn('kd_jenis_prw', $this->getCachedCodes($txTable)))
            ->when($this->search, fn($q) => $q->where('kd_jenis_prw', 'like', "%{$this->search}%")->orWhere('nm_perawatan', 'like', "%{$this->search}%"))
            ->orderBy('kd_jenis_prw')
            ->paginate(25, ['*'], 'page', $page);

        $codes = $items->getCollection()->pluck('kd_jenis_prw')->toArray();
        $mappings = ProcedureMap::where('source_table', $this->activeTab)->whereIn('procedure_code', $codes)->get()->keyBy('procedure_code');

        $unmapped = [];
        foreach ($items as $item) {
            $hasMapping = isset($mappings[$item->kd_jenis_prw]);
            if (!$hasMapping) {
                $unmapped[] = ['code' => $item->kd_jenis_prw, 'name' => $item->nm_perawatan];
            }
        }
        return $unmapped;
    }

    public function updatedActiveTab(): void
    {
        $this->resetPage();
        $this->search = '';
        $this->showSnomedSearchModal = false;
    }

    private const TX_TABLE_MAP = [
        'jalan_dr' => 'rawat_jl_dr',
        'jalan_pr' => 'rawat_jl_pr',
        'jalan_drpr' => 'rawat_jl_drpr',
        'inap_dr' => 'rawat_inap_dr',
        'inap_pr' => 'rawat_inap_pr',
        'inap_drpr' => 'rawat_inap_drpr',
    ];

    /** Ambil DISTINCT kd_jenis_prw dari tabel transaksi, di-cache 10 menit. */
    private function getCachedCodes(string $txTable): array
    {
        return Cache::remember("proc_tx_codes_{$txTable}", 600, fn() => DB::connection('simrs')->table($txTable)->distinct()->pluck('kd_jenis_prw')->all());
    }

    public function with(): array
    {
        $txTable = self::TX_TABLE_MAP[$this->activeTab] ?? null;
        $isInap = str_starts_with($this->activeTab, 'inap');
        $modelClass = $isInap ? JnsPerawatanInap::class : JnsPerawatan::class;

        $items = $modelClass::query()->active()->when($txTable, fn($q) => $q->whereIn('kd_jenis_prw', $this->getCachedCodes($txTable)))->when($this->search, fn($q) => $q->where('kd_jenis_prw', 'like', "%{$this->search}%")->orWhere('nm_perawatan', 'like', "%{$this->search}%"))->orderBy('kd_jenis_prw')->paginate(25);

        $codes = $items->getCollection()->pluck('kd_jenis_prw')->toArray();
        $mappings = ProcedureMap::where('source_table', $this->activeTab)->whereIn('procedure_code', $codes)->get()->keyBy('procedure_code');

        $bpjsType = $this->getBpjsType();
        $bpjsRegistered = BpjsProcedure::where('type', $bpjsType)->pluck('id', 'local_code');

        $items->getCollection()->transform(function ($item) use ($mappings, $bpjsRegistered) {
            $mapping = $mappings->get($item->kd_jenis_prw);
            $item->snomed_code = $mapping?->system_code ?? null;
            $item->snomed_term = $mapping?->system_term ?? null;
            $item->category_code = $mapping?->category_code ?? null;
            $item->category_term = $mapping?->category_term ?? null;
            $item->bpjs_uuid = $bpjsRegistered->get($item->kd_jenis_prw);
            return $item;
        });

        $modelClass = str_starts_with($this->activeTab, 'inap') ? JnsPerawatanInap::class : JnsPerawatan::class;
        $txTable = self::TX_TABLE_MAP[$this->activeTab] ?? null;
        $totalSimrs = $modelClass::when($txTable, fn($q) => $q->whereIn('kd_jenis_prw', $this->getCachedCodes($txTable)))->count();
        $totalSnomed = ProcedureMap::where('source_table', $this->activeTab)->count();
        $totalBpjs = BpjsProcedure::where('type', $bpjsType)->count();

        return [
            'items' => $items,
            'bpjsType' => $bpjsType,
            'totalSimrs' => $totalSimrs,
            'totalSnomed' => $totalSnomed,
            'totalBpjs' => $totalBpjs,
            'unsyncedBpjs' => max(0, $totalSimrs - $totalBpjs),
        ];
    }

    // ── BPJS UUID actions ───────────────────────────────────────────────────

    public function generateBpjsUuid(string $code, string $name): void
    {
        $type = $this->getBpjsType();
        if (BpjsProcedure::where('type', $type)->where('local_code', $code)->exists()) {
            $this->toastWarning('Tindakan ini sudah memiliki UUID BPJS.');
            return;
        }
        BpjsProcedure::create(['type' => $type, 'local_code' => $code, 'name' => $name]);
        $this->toastSuccess("UUID BPJS berhasil di-generate untuk {$name}.");
    }

    public function syncAllBpjs(): void
    {
        SyncBpjsProceduresJob::dispatch($this->getBpjsType());
        $this->showSyncModal = false;
        $this->toastSuccess('Sync semua UUID BPJS dijadwalkan. Proses berjalan di background.');
    }

    public function viewBpjsDetail(string $code): void
    {
        $this->selectedBpjsItem = BpjsProcedure::where('type', $this->getBpjsType())->where('local_code', $code)->first();
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
        BpjsProcedure::where('type', $this->getBpjsType())->where('local_code', $this->deleteBpjsCode)->delete();
        $this->showDeleteBpjsModal = false;
        $this->reset(['deleteBpjsCode', 'deleteBpjsName']);
        $this->toastSuccess('UUID BPJS berhasil dihapus.');
    }

    public function openModal(string $code, string $name): void
    {
        $this->selectedProcedureCode = $code;
        $this->selectedProcedureName = $name;
        $this->snomedInitialSearch = ProcedureMap::where('source_table', $this->activeTab)->where('procedure_code', $code)->value('system_term') ?? '';
        $this->showSnomedSearchModal = true;
    }

    #[On('snomed-selected')]
    public function snomedSelected(string $system_code, string $system_term, string $system_display, string $category): void
    {
        ProcedureMap::updateOrCreate(
            ['source_table' => $this->activeTab, 'procedure_code' => $this->selectedProcedureCode],
            [
                'system_code' => $system_code,
                'system_term' => $system_term,
                'system_display' => $system_display,
            ],
        );

        $this->showSnomedSearchModal = false;
        $this->toastSuccess('Mapping berhasil disimpan', 'Sukses');
    }

    public function confirmDelete(string $code, string $name): void
    {
        $this->deleteCode = $code;
        $this->deleteName = $name;
        $this->showDeleteModal = true;
    }

    public function deleteMapping(): void
    {
        ProcedureMap::where('source_table', $this->activeTab)->where('procedure_code', $this->deleteCode)->delete();

        $this->showDeleteModal = false;
        $this->toastSuccess('Mapping berhasil dihapus', 'Sukses');
        $this->reset(['deleteCode', 'deleteName']);
    }

    public function cancelDelete(): void
    {
        $this->showDeleteModal = false;
        $this->reset(['deleteCode', 'deleteName']);
    }

    public function openCategoryModal(string $code): void
    {
        $this->categoryTargetCode = $code;
        $map = ProcedureMap::where('source_table', $this->activeTab)->where('procedure_code', $code)->first();
        $this->categoryCode = $map?->category_code ?? '';
        $this->categoryDisplay = $map?->category_term ?? '';
        $this->showCategoryModal = true;
    }

    public function saveCategory(): void
    {
        if (!$this->categoryCode || !array_key_exists($this->categoryCode, ProcedureMap::PROCEDURE_CATEGORIES)) {
            $this->toastError('Pilih kategori yang valid.');
            return;
        }

        ProcedureMap::where('source_table', $this->activeTab)
            ->where('procedure_code', $this->categoryTargetCode)
            ->update([
                'category_code' => $this->categoryCode,
                'category_term' => ProcedureMap::PROCEDURE_CATEGORIES[$this->categoryCode],
                'category_display' => 'http://snomed.info/sct',
            ]);

        $this->showCategoryModal = false;
        $this->reset(['categoryTargetCode', 'categoryCode', 'categoryDisplay']);
        $this->toastSuccess('Kategori berhasil disimpan.');
    }

    public function clearCategory(string $code): void
    {
        ProcedureMap::where('source_table', $this->activeTab)
            ->where('procedure_code', $code)
            ->update(['category_code' => null, 'category_term' => null, 'category_display' => null]);
        $this->toastSuccess('Kategori berhasil dihapus.');
    }
}; ?>

<div>
    @php
        $primaryTab = str_starts_with($activeTab, 'inap') ? 'inap' : 'jalan';
        $tabSuffix = str_contains($activeTab, '_') ? '_' . Str::after($activeTab, '_') : '';

        $primaryTabs = [
            'jalan' => ['label' => 'Rawat Jalan', 'icon' => 'building-office'],
            'inap' => ['label' => 'Rawat Inap', 'icon' => 'building-office-2'],
        ];

        $subTabs = [
            '' => ['label' => 'Semua', 'icon' => 'squares-2x2'],
            '_dr' => ['label' => 'Dokter', 'icon' => 'academic-cap'],
            '_pr' => ['label' => 'Perawat', 'icon' => 'heart'],
            '_drpr' => ['label' => 'Dokter & Perawat', 'icon' => 'user-group'],
        ];

        $mappedCount = collect($items->items())->filter(fn($i) => $i->snomed_code)->count();
        $pageCount = $items->count();
    @endphp

    {{-- Stats Cards --}}
    <div class="grid grid-cols-3 gap-4 mb-6">
        <x-organisms.stat-card title="Total Tindakan" :value="number_format($totalSimrs)" icon="scissors" color="zinc" />
        <x-organisms.stat-card title="Ter-mapping SNOMED" :value="number_format($totalSnomed)" icon="sparkles" color="violet"
            :subtitle="'dari ' . number_format($totalSimrs) . ' total'" />
        <x-organisms.stat-card title="UUID BPJS" :value="number_format($totalBpjs)" icon="identification" color="blue"
            :subtitle="($bpjsType === 'ranap' ? 'Ranap' : 'Ralan') . ', dari ' . number_format($totalSimrs) . ' total'" />
    </div>

    <x-organisms.data-panel title="Mapping Tindakan → SNOMED CT"
        subtitle="Hubungkan tindakan lokal dengan konsep SNOMED CT">

        <x-slot:actions>
            <x-atoms.button variant="outline" icon="arrow-path" wire:click="$set('showSyncModal', true)">
                Sync UUID BPJS
            </x-atoms.button>
            <flux:dropdown>
                <x-atoms.button variant="primary" icon="sparkles" class="whitespace-nowrap">
                    Smart Bulk Map
                </x-atoms.button>
                <flux:menu>
                    <flux:menu.item icon="bolt" wire:click="smartMapPage('snowstorm')">
                        Via Snowstorm (Per Halaman)
                    </flux:menu.item>
                    <flux:menu.item icon="cpu-chip" wire:click="smartMapPage('ai')">
                        Via AI Provider (Diantrikan)
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </x-slot:actions>

        <x-slot:filter>
            {{-- Level 1: Rawat Jalan / Rawat Inap --}}
            <div class="flex gap-1 p-1 mb-3 rounded-xl bg-zinc-100 dark:bg-primary-dark-900/60 w-full">
                @foreach ($primaryTabs as $primary => $meta)
                    <button wire:click="$set('activeTab', '{{ $primary . $tabSuffix }}')"
                        class="flex flex-1 justify-center items-center gap-2 px-4 py-1.5 text-sm font-semibold rounded-lg transition-all duration-200
                            {{ $primaryTab === $primary
                                ? 'bg-white dark:bg-primary-dark-700 text-primary-700 dark:text-primary-300 shadow-sm ring-1 ring-zinc-200/70 dark:ring-primary-dark-600/50'
                                : 'text-zinc-500 dark:text-primary-dark-400 hover:text-zinc-700 dark:hover:text-primary-dark-200' }}">
                        <flux:icon name="{{ $meta['icon'] }}" class="w-4 h-4" />
                        {{ $meta['label'] }}
                    </button>
                @endforeach
            </div>

            {{-- Level 2: sub-tabs + search + stats --}}
            <div class="flex flex-wrap items-center gap-2">
                @foreach ($subTabs as $suffix => $meta)
                    @php $tabValue = $primaryTab . $suffix; @endphp
                    <button wire:click="$set('activeTab', '{{ $tabValue }}')"
                        class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg border transition-all duration-150
                            {{ $activeTab === $tabValue
                                ? 'bg-primary-600 dark:bg-primary-500 text-white border-primary-600 dark:border-primary-500 shadow-sm shadow-primary-500/25'
                                : 'bg-white dark:bg-primary-dark-800 text-zinc-500 dark:text-primary-dark-400 border-zinc-200 dark:border-primary-dark-700 hover:border-primary-300 dark:hover:border-primary-600 hover:text-primary-600 dark:hover:text-primary-400' }}">
                        <flux:icon name="{{ $meta['icon'] }}" class="w-3.5 h-3.5" />
                        {{ $meta['label'] }}
                    </button>
                @endforeach

                <div class="w-px h-4 bg-zinc-200 dark:bg-primary-dark-700 mx-1"></div>

                <div class="flex-1 min-w-48">
                    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                        placeholder="Cari kode atau nama tindakan..." clearable size="sm" />
                </div>

                @if ($pageCount > 0)
                    <div
                        class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium whitespace-nowrap
                                bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400
                                ring-1 ring-emerald-200 dark:ring-emerald-800/50">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 shrink-0"></span>
                        {{ $mappedCount }}/{{ $pageCount }} ter-mapping
                        ({{ round(($mappedCount / $pageCount) * 100) }}%)
                    </div>
                @endif
            </div>
        </x-slot:filter>

        <x-organisms.table>
            <x-slot:headings>
                <x-atoms.table-heading class="w-36">Kode</x-atoms.table-heading>
                <x-atoms.table-heading>Nama Tindakan</x-atoms.table-heading>
                <x-atoms.table-heading>Mapping SNOMED CT</x-atoms.table-heading>
                <x-atoms.table-heading>Kategori FHIR</x-atoms.table-heading>
                <x-atoms.table-heading align="center">UUID BPJS</x-atoms.table-heading>
                <x-atoms.table-heading align="center" class="w-48">Aksi</x-atoms.table-heading>
            </x-slot:headings>

            @forelse ($items as $item)
                <x-molecules.table-row wire:key="{{ $activeTab }}-{{ $item->kd_jenis_prw }}">
                    <x-atoms.table-cell nowrap>
                        <span
                            class="inline-block font-mono text-xs font-bold px-2 py-1 rounded-md
                            bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300
                            ring-1 ring-primary-100 dark:ring-primary-800/40">
                            {{ $item->kd_jenis_prw }}
                        </span>
                    </x-atoms.table-cell>

                    <x-atoms.table-cell>
                        <p class="text-sm font-medium text-zinc-800 dark:text-primary-dark-100 leading-snug">
                            {{ $item->nm_perawatan }}
                        </p>
                    </x-atoms.table-cell>

                    <x-atoms.table-cell>
                        @if ($item->snomed_code)
                            <div class="flex items-start gap-2.5">
                                <span
                                    class="mt-1 w-2 h-2 rounded-full bg-emerald-400 dark:bg-emerald-500 shrink-0
                                             ring-2 ring-emerald-100 dark:ring-emerald-900/50"></span>
                                <div class="min-w-0">
                                    <p class="font-mono text-xs font-bold text-emerald-700 dark:text-emerald-400">
                                        {{ $item->snomed_code }}
                                    </p>
                                    <p
                                        class="mt-0.5 text-xs text-zinc-500 dark:text-primary-dark-400 leading-snug line-clamp-2">
                                        {{ $item->snomed_term }}
                                    </p>
                                </div>
                            </div>
                        @else
                            <div class="flex items-center gap-2 text-zinc-400 dark:text-primary-dark-500">
                                <span class="w-2 h-2 rounded-full bg-zinc-200 dark:bg-primary-dark-600"></span>
                                <span class="text-xs italic">Belum di-mapping</span>
                            </div>
                        @endif
                    </x-atoms.table-cell>

                    <x-atoms.table-cell>
                        @if ($item->category_code)
                            <div class="flex items-start gap-2">
                                <span
                                    class="mt-0.5 w-2 h-2 rounded-full bg-sky-400 dark:bg-sky-500 shrink-0 ring-2 ring-sky-100 dark:ring-sky-900/50"></span>
                                <div class="min-w-0">
                                    <p class="font-mono text-xs font-bold text-sky-700 dark:text-sky-400">
                                        {{ $item->category_code }}</p>
                                    <p
                                        class="mt-0.5 text-xs text-zinc-500 dark:text-primary-dark-400 leading-snug line-clamp-2">
                                        {{ $item->category_term }}</p>
                                </div>
                            </div>
                        @elseif ($item->snomed_code)
                            <div class="flex items-center gap-2 text-zinc-400 dark:text-primary-dark-500">
                                <span class="w-2 h-2 rounded-full bg-zinc-200 dark:bg-primary-dark-600"></span>
                                <span class="text-xs italic">Belum diset</span>
                            </div>
                        @else
                            <span class="text-xs text-zinc-300 dark:text-primary-dark-700">—</span>
                        @endif
                    </x-atoms.table-cell>

                    <x-atoms.table-cell align="center">
                        <span
                            class="font-mono text-xs font-bold text-emerald-700 dark:text-emerald-400">{{ $item->bpjs_uuid }}</span>
                    </x-atoms.table-cell>

                    <x-atoms.table-cell :action="true" align="center" nowrap>
                        <div class="flex items-center justify-center gap-0.5">
                            {{-- Grup SNOMED --}}
                            <div
                                class="flex items-center gap-0.5 pr-2 border-r border-zinc-200 dark:border-primary-dark-600">
                                <div x-show="$wire.mappingCode === '{{ $item->kd_jenis_prw }}'">
                                    <flux:icon.loading class="w-4 h-4 text-primary-500" />
                                </div>
                                @if (!$item->snomed_code)
                                    <flux:dropdown>
                                        <x-atoms.button variant="ghost" size="sm" icon="sparkles"
                                            class="text-amber-500 hover:bg-amber-50 dark:hover:bg-amber-900/20">Auto</x-atoms.button>
                                        <flux:menu>
                                            <flux:menu.item icon="bolt"
                                                wire:click="smartMap('{{ $item->kd_jenis_prw }}', '{{ addslashes($item->nm_perawatan) }}', 'snowstorm')">
                                                Via Snowstorm (Cepat)
                                            </flux:menu.item>
                                            <flux:menu.item icon="cpu-chip"
                                                wire:click="smartMap('{{ $item->kd_jenis_prw }}', '{{ addslashes($item->nm_perawatan) }}', 'ai')">
                                                Via AI Provider (Akurat)
                                            </flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                @endif
                                <x-atoms.button
                                    wire:click="openModal('{{ $item->kd_jenis_prw }}', '{{ addslashes($item->nm_perawatan) }}')"
                                    size="sm"
                                    icon="{{ $item->snomed_code ? 'pencil-square' : 'magnifying-glass' }}"
                                    variant="ghost"
                                    tooltip="{{ $item->snomed_code ? 'Edit SNOMED' : 'Cari SNOMED' }}" />
                                @if ($item->snomed_code)
                                    <x-atoms.button
                                        wire:click="confirmDelete('{{ $item->kd_jenis_prw }}', '{{ addslashes($item->nm_perawatan) }}')"
                                        size="sm" icon="trash" variant="ghost" tooltip="Hapus SNOMED"
                                        class="text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20" />
                                @endif
                            </div>
                            {{-- Grup Kategori --}}
                            @if ($item->snomed_code)
                                <div
                                    class="flex items-center gap-0.5 px-2 border-r border-zinc-200 dark:border-primary-dark-600">
                                    <x-atoms.button wire:click="openCategoryModal('{{ $item->kd_jenis_prw }}')"
                                        size="sm" icon="{{ $item->category_code ? 'tag' : 'tag' }}"
                                        variant="ghost"
                                        tooltip="{{ $item->category_code ? 'Edit Kategori' : 'Set Kategori' }}"
                                        class="{{ $item->category_code ? '' : 'text-amber-500 hover:bg-amber-50 dark:hover:bg-amber-900/20' }}" />
                                    @if ($item->category_code)
                                        <x-atoms.button wire:click="clearCategory('{{ $item->kd_jenis_prw }}')"
                                            size="sm" icon="x-mark" variant="ghost" tooltip="Hapus Kategori"
                                            class="text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20" />
                                    @endif
                                </div>
                            @endif
                            {{-- Grup BPJS UUID --}}
                            <div class="flex items-center gap-0.5 pl-2">
                                @if ($item->bpjs_uuid)
                                    <x-atoms.button wire:click="viewBpjsDetail('{{ $item->kd_jenis_prw }}')"
                                        size="sm" icon="eye" variant="ghost" tooltip="Detail UUID BPJS" />
                                    <x-atoms.button
                                        wire:click="confirmDeleteBpjs('{{ $item->kd_jenis_prw }}', '{{ addslashes($item->nm_perawatan) }}')"
                                        size="sm" icon="trash" variant="ghost" tooltip="Hapus UUID BPJS"
                                        class="text-red-500" />
                                @else
                                    <x-atoms.button
                                        wire:click="generateBpjsUuid('{{ $item->kd_jenis_prw }}', '{{ addslashes($item->nm_perawatan) }}')"
                                        size="sm" icon="sparkles" variant="ghost"
                                        tooltip="Generate UUID BPJS" />
                                @endif
                            </div>
                        </div>
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @empty
                <x-molecules.table-row>
                    <x-atoms.table-cell colspan="5" align="center" class="py-16">
                        <div class="flex flex-col items-center gap-3">
                            <div
                                class="flex items-center justify-center w-14 h-14 rounded-2xl bg-zinc-100 dark:bg-primary-dark-700">
                                <flux:icon name="clipboard-document-list"
                                    class="w-7 h-7 text-zinc-300 dark:text-primary-dark-500" />
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-zinc-500 dark:text-primary-dark-400">Tidak ada
                                    data
                                    tindakan</p>
                                <p class="mt-0.5 text-xs text-zinc-400 dark:text-primary-dark-500">Coba ganti filter
                                    atau kata kunci pencarian</p>
                            </div>
                        </div>
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @endforelse
        </x-organisms.table>

        <x-slot:footer>
            {{ $items->links() }}
        </x-slot:footer>
    </x-organisms.data-panel>

    {{-- Modal Pencarian SNOMED CT --}}
    <x-organisms.modal wire:model="showSnomedSearchModal" maxWidth="4xl" title="Pilih Kode SNOMED CT">
        <div class="space-y-4">
            <div>

                <flux:text class="mt-0.5">
                    Untuk:
                    <span
                        class="font-semibold text-zinc-700 dark:text-primary-dark-200">{{ $selectedProcedureName }}</span>
                    <span class="font-mono text-xs text-zinc-400 ml-1">({{ $selectedProcedureCode }})</span>
                </flux:text>
            </div>
            <livewire:components.snomed-search defaultTag="procedure" :initialSearch="$snomedInitialSearch" :key="'snomed-proc-' . $activeTab . '-' . ($selectedProcedureCode ?? '')" />

            <x-slot:footer>
                <div class="flex justify-end">
                    <x-atoms.button wire:click="$set('showSnomedSearchModal', false)"
                        variant="ghost">Tutup</x-atoms.button>
                </div>
            </x-slot:footer>
        </div>
    </x-organisms.modal>

    {{-- Modal Kategori FHIR --}}
    <x-organisms.modal wire:model="showCategoryModal" maxWidth="sm" title="Set Kategori Procedure FHIR">
        <div class="space-y-4">
            <flux:text class="text-sm text-zinc-500 dark:text-primary-dark-400">
                Pilih kategori SNOMED CT untuk tindakan
                <span class="font-semibold text-zinc-700 dark:text-primary-dark-200">{{ $categoryTargetCode }}</span>.
            </flux:text>
            <flux:select wire:model="categoryCode" label="Kategori">
                <flux:select.option value="">— Pilih kategori —</flux:select.option>
                @foreach (ProcedureMap::PROCEDURE_CATEGORIES as $code => $label)
                    <flux:select.option value="{{ $code }}">{{ $code }} — {{ $label }}
                    </flux:select.option>
                @endforeach
            </flux:select>
        </div>
        <x-slot:footer>
            <div class="flex justify-end gap-2 w-full">
                <x-atoms.button wire:click="$set('showCategoryModal', false)" variant="ghost">Batal</x-atoms.button>
                <x-atoms.button wire:click="saveCategory" variant="primary">Simpan</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal Konfirmasi Hapus --}}
    <x-organisms.modal wire:model="showDeleteModal" maxWidth="sm" title="Hapus Mapping"
        description="Tindakan ini tidak dapat dibatalkan.">
        <div
            class="p-4 space-y-2 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/40
                    border border-zinc-200 dark:border-primary-dark-700">
            <div class="flex items-center gap-3">
                <span class="text-xs font-medium text-zinc-400 w-10 shrink-0">Kode</span>
                <span
                    class="font-mono text-sm font-bold text-primary-600 dark:text-primary-400">{{ $deleteCode }}</span>
            </div>
            <div class="flex items-start gap-3">
                <span class="text-xs font-medium text-zinc-400 w-10 shrink-0 mt-0.5">Nama</span>
                <span class="text-sm text-zinc-700 dark:text-primary-dark-300">{{ $deleteName }}</span>
            </div>
        </div>
        <x-slot:footer>
            <div class="flex justify-end gap-2 w-full">
                <x-atoms.button wire:click="cancelDelete" variant="ghost">Batal</x-atoms.button>
                <x-atoms.button wire:click="deleteMapping" variant="danger">Hapus Mapping</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal Sync UUID BPJS --}}
    <x-organisms.modal wire:model="showSyncModal" title="Sync Semua UUID BPJS Tindakan" maxWidth="md">
        <div class="space-y-4">
            <p class="text-sm text-zinc-600 dark:text-primary-dark-300">
                Proses ini akan men-generate UUID BPJS untuk semua tindakan
                <strong>{{ $bpjsType === 'ranap' ? 'Rawat Inap' : 'Rawat Jalan' }}</strong>
                yang belum terdaftar.
                Saat ini terdapat <strong>{{ number_format($unsyncedBpjs) }}</strong> tindakan belum memiliki UUID
                BPJS.
            </p>
            <p class="text-xs text-zinc-500 dark:text-primary-dark-400">
                Proses berjalan di background queue dan tidak akan mengganggu aktivitas lain.
            </p>
        </div>
        <x-slot:footer>
            <x-atoms.button variant="ghost" wire:click="$set('showSyncModal', false)">Batal</x-atoms.button>
            <x-atoms.button variant="primary" icon="arrow-path" wire:click="syncAllBpjs">Mulai Sync</x-atoms.button>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal Detail UUID BPJS --}}
    <x-organisms.modal wire:model="showBpjsDetailModal" title="Detail UUID BPJS Tindakan" maxWidth="md">
        @if ($selectedBpjsItem)
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500 dark:text-primary-dark-400 shrink-0">Nama Tindakan</dt>
                    <dd class="font-medium text-zinc-800 dark:text-primary-dark-100 text-right">
                        {{ $selectedBpjsItem->name }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500 dark:text-primary-dark-400 shrink-0">Kode Lokal</dt>
                    <dd class="font-mono text-xs text-zinc-700 dark:text-primary-dark-200">
                        {{ $selectedBpjsItem->local_code }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500 dark:text-primary-dark-400 shrink-0">Tipe</dt>
                    <dd class="text-zinc-700 dark:text-primary-dark-200">{{ $selectedBpjsItem->type }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500 dark:text-primary-dark-400 shrink-0">UUID BPJS</dt>
                    <dd class="font-mono text-xs break-all text-blue-700 dark:text-blue-400">
                        {{ $selectedBpjsItem->id }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500 dark:text-primary-dark-400 shrink-0">Dibuat</dt>
                    <dd class="text-zinc-600 dark:text-primary-dark-300">
                        {{ $selectedBpjsItem->created_at?->format('d M Y H:i') }}</dd>
                </div>
            </dl>
        @endif
        <x-slot:footer>
            <x-atoms.button variant="ghost" wire:click="$set('showBpjsDetailModal', false)">Tutup</x-atoms.button>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal Hapus UUID BPJS --}}
    <x-organisms.modal wire:model="showDeleteBpjsModal" title="Hapus UUID BPJS?" maxWidth="md">
        <p class="text-sm text-zinc-600 dark:text-primary-dark-300">
            UUID BPJS untuk tindakan <strong>{{ $deleteBpjsName }}</strong> akan dihapus secara permanen.
            Data ini tidak dapat dipulihkan.
        </p>
        <x-slot:footer>
            <x-atoms.button variant="ghost" wire:click="$set('showDeleteBpjsModal', false)">Batal</x-atoms.button>
            <x-atoms.button variant="danger" wire:click="deleteBpjs" icon="trash">Hapus UUID</x-atoms.button>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Load modal external --}}
    @include('pages.local.clinical.partials.ai-mapping-modal')
</div>
