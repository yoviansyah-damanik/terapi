<?php

use App\Models\Terminology\IcdPm;
use App\Models\Mapping\IcdPmMap;
use App\Traits\WithSmartMapping;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;

new #[Layout('layouts::app')] #[Title('Mapping ICD-PM → SNOMED CT')] class extends Component {
    use WithPagination, WithSmartMapping;

    public string $search = '';
    public string $filterVersion = '';
    public int $perPage = 25;

    // State modal pencarian SNOMED
    public bool $showSnomedSearchModal = false;
    public ?string $selectedCode = null;
    public ?string $selectedName = null;
    public ?string $selectedVersion = null;

    // State konfirmasi hapus
    public bool $showDeleteModal = false;
    public ?string $deleteCode = null;
    public ?string $deleteName = null;
    public ?string $deleteVersion = null;

    public string $snomedSemanticTag = 'disorder';
    public string $snomedInitialSearch = '';

    protected function saveSmartMapping(string $sourceCodeComposite, string $snomedCode, string $snomedTerm): bool
    {
        // sourceCodeComposite dikirim dari format "code|version"
        $parts = explode('|', $sourceCodeComposite);
        $code = $parts[0] ?? $sourceCodeComposite;
        $version = $parts[1] ?? 'WHO'; // default jika tidak ada

        IcdPmMap::updateOrCreate(
            ['code' => $code, 'version' => $version],
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
        $items = IcdPm::query()
            ->select('icd_pm.*', 'map_icd_pm.system_code as snomed_code')
            ->leftJoin('map_icd_pm', function ($join) {
                $join->on('icd_pm.code', '=', 'map_icd_pm.code')->on('icd_pm.version', '=', 'map_icd_pm.version');
            })
            ->when($this->search, fn($q) => $q->where('icd_pm.code', 'like', "%{$this->search}%")->orWhere('icd_pm.display', 'like', "%{$this->search}%"))
            ->when($this->filterVersion, fn($q) => $q->where('icd_pm.version', $this->filterVersion))
            ->orderBy('icd_pm.version')
            ->orderBy('icd_pm.code')
            ->paginate($this->perPage, ['*'], 'page', $page);

        $unmapped = [];
        foreach ($items as $item) {
            if (!$item->snomed_code) {
                $compositeCode = $item->code . '|' . $item->version;
                $unmapped[] = ['code' => $compositeCode, 'name' => $item->display];
            }
        }
        return $unmapped;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }
    public function updatedFilterVersion(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $halaman = LengthAwarePaginator::resolveCurrentPage();
        $kunciData = 'icd_pm.data.' . md5("{$this->search}|{$this->filterVersion}|{$this->perPage}|{$halaman}");

        $items = Cache::remember(
            $kunciData,
            300,
            fn() => IcdPm::query()
                ->select('icd_pm.*', 'map_icd_pm.system_code as snomed_code', 'map_icd_pm.system_term  as snomed_term')
                ->leftJoin('map_icd_pm', function ($join) {
                    $join->on('icd_pm.code', '=', 'map_icd_pm.code')->on('icd_pm.version', '=', 'map_icd_pm.version');
                })
                ->when($this->search, fn($q) => $q->where('icd_pm.code', 'like', "%{$this->search}%")->orWhere('icd_pm.display', 'like', "%{$this->search}%"))
                ->when($this->filterVersion, fn($q) => $q->where('icd_pm.version', $this->filterVersion))
                ->orderBy('icd_pm.version')
                ->orderBy('icd_pm.code')
                ->paginate($this->perPage),
        );

        return [
            'items' => $items,
            'versions' => IcdPm::getVersions(),
        ];
    }

    public function openModal(string $code, string $name, string $version): void
    {
        $this->selectedCode = $code;
        $this->selectedName = $name;
        $this->selectedVersion = $version;
        $this->snomedInitialSearch = IcdPmMap::where('code', $code)->where('version', $version)->value('system_term') ?? '';
        $this->showSnomedSearchModal = true;
    }

    #[On('snomed-selected')]
    public function snomedSelected(string $system_code, string $system_term, string $system_display, string $category): void
    {
        IcdPmMap::updateOrCreate(
            ['code' => $this->selectedCode, 'version' => $this->selectedVersion],
            [
                'system_code' => $system_code,
                'system_term' => $system_term,
                'system_display' => $system_display,
            ],
        );

        IcdPm::clearCache();
        IcdPmMap::clearCache();
        $this->showSnomedSearchModal = false;
        $this->toastSuccess('Mapping berhasil disimpan', 'Sukses');
    }

    public function confirmDelete(string $code, string $name, string $version): void
    {
        $this->deleteCode = $code;
        $this->deleteName = $name;
        $this->deleteVersion = $version;
        $this->showDeleteModal = true;
    }

    public function deleteMapping(): void
    {
        IcdPmMap::where('code', $this->deleteCode)->where('version', $this->deleteVersion)->delete();
        IcdPm::clearCache();
        IcdPmMap::clearCache();
        $this->showDeleteModal = false;
        $this->toastSuccess('Mapping berhasil dihapus', 'Sukses');
        $this->reset(['deleteCode', 'deleteName', 'deleteVersion']);
    }

    public function cancelDelete(): void
    {
        $this->showDeleteModal = false;
        $this->reset(['deleteCode', 'deleteName', 'deleteVersion']);
    }
}; ?>
<div>
    <x-ui.page-header title="Mapping ICD-PM → SNOMED CT"
        subtitle="Hubungkan kode penyebab kematian perinatal ICD-PM dengan konsep SNOMED CT lokal" />

    @php
        $mappedCount = collect($items->items())->filter(fn($i) => $i->snomed_code)->count();
    @endphp {{-- Panel Tabel --}}
    <x-organisms.data-panel>
        <x-slot:filter>
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-40">
                    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                        placeholder="Cari kode atau deskripsi ICD-PM..." clearable />
                </div>
                @if (count($versions) > 0)
                    <flux:select wire:model.live="filterVersion" class="w-48">
                        <flux:select.option value="">Semua Versi</flux:select.option>
                        @foreach ($versions as $v)
                            <flux:select.option value="{{ $v }}">{{ $v }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif
                <flux:select wire:model.live="perPage" class="w-36">
                    <flux:select.option value="25">25 / halaman</flux:select.option>
                    <flux:select.option value="50">50 / halaman</flux:select.option>
                    <flux:select.option value="100">100 / halaman</flux:select.option>
                </flux:select>
                <div
                    class="hidden sm:flex items-center gap-2 text-xs font-medium text-zinc-500 dark:text-primary-dark-400 whitespace-nowrap">
                    <span class="inline-block w-2 h-2 rounded-full bg-emerald-400"></span>
                    {{ $mappedCount }} ter-mapping / {{ $items->count() }} di halaman ini
                </div>
            </div>
        </x-slot:filter>
        <x-slot:action>
            <flux:dropdown>
                <x-atoms.button variant="primary" icon="sparkles" class="whitespace-nowrap">Smart Bulk
                    Map</x-atoms.button>
                <flux:menu>
                    <flux:menu.item icon="bolt" wire:click="smartMapPage('snowstorm')">Gunakan Snowstorm (Per
                        Halaman)</flux:menu.item>
                    <flux:menu.item icon="cpu-chip" wire:click="smartMapPage('ai')">Gunakan AI Provider (Di antrikan)
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </x-slot:action>

        <x-organisms.table>
            <x-slot:headings>
                <x-atoms.table-heading class="w-36">Kode</x-atoms.table-heading>
                <x-atoms.table-heading>Deskripsi</x-atoms.table-heading>
                <x-atoms.table-heading>Mapping SNOMED CT</x-atoms.table-heading>
                <x-atoms.table-heading align="center" class="w-44">Aksi</x-atoms.table-heading>
            </x-slot:headings>

            @forelse ($items as $item)
                <x-molecules.table-row :key="$item->id">
                    <x-atoms.table-cell nowrap>
                        <span
                            class="inline-block font-mono text-xs font-bold px-2 py-1 rounded-md
                            bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300
                            ring-1 ring-primary-100 dark:ring-primary-800/40">
                            {{ $item->code }}
                        </span>
                        <span
                            class="block mt-1 text-xs text-zinc-400 dark:text-primary-dark-500">{{ $item->version }}</span>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        <p class="text-sm font-medium text-zinc-800 dark:text-primary-dark-100 leading-snug">
                            {{ $item->display }}</p>
                        @if ($item->category_display)
                            <p class="mt-0.5 text-xs text-zinc-400 dark:text-primary-dark-500">
                                {{ $item->category_display }}</p>
                        @endif
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        @if ($item->snomed_code)
                            <div class="flex items-start gap-2.5">
                                <span
                                    class="mt-1 w-2 h-2 rounded-full bg-emerald-400 dark:bg-emerald-500 shrink-0 ring-2 ring-emerald-100 dark:ring-emerald-900/50"></span>
                                <div class="min-w-0">
                                    <p class="font-mono text-xs font-bold text-emerald-700 dark:text-emerald-400">
                                        {{ $item->snomed_code }}</p>
                                    <p
                                        class="mt-0.5 text-xs text-zinc-500 dark:text-primary-dark-400 leading-snug line-clamp-2">
                                        {{ $item->snomed_term }}</p>
                                </div>
                            </div>
                        @else
                            <div class="flex items-center gap-2 text-zinc-400 dark:text-primary-dark-500">
                                <span class="w-2 h-2 rounded-full bg-zinc-200 dark:bg-primary-dark-600"></span>
                                <span class="text-xs italic">Belum di-mapping</span>
                            </div>
                        @endif
                    </x-atoms.table-cell>
                    <x-atoms.table-cell :action="true" align="center" nowrap>
                        <div x-show="$wire.mappingCode === '{{ $item->code }}|{{ $item->version }}'">
                            <flux:icon.loading class="w-4 h-4 text-primary-500" />
                        </div>
                        @if (!$item->snomed_code)
                            <flux:dropdown>
                                <x-atoms.button variant="ghost" size="sm" icon="sparkles"
                                    class="text-amber-500 hover:bg-amber-50 dark:hover:bg-amber-900/20">Auto</x-atoms.button>
                                <flux:menu>
                                    <flux:menu.item icon="bolt"
                                        wire:click="smartMap('{{ $item->code }}|{{ $item->version }}', '{{ addslashes($item->display) }}', 'snowstorm')">
                                        Via Snowstorm (Cepat)</flux:menu.item>
                                    <flux:menu.item icon="cpu-chip"
                                        wire:click="smartMap('{{ $item->code }}|{{ $item->version }}', '{{ addslashes($item->display) }}', 'ai')">
                                        Via AI Provider (Akurat)</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        @endif
                        <x-atoms.button
                            wire:click="openModal('{{ $item->code }}', '{{ addslashes($item->display) }}', '{{ $item->version }}')"
                            size="sm" icon="{{ $item->snomed_code ? 'pencil-square' : 'magnifying-glass' }}"
                            variant="ghost" tooltip="{{ $item->snomed_code ? 'Edit' : 'Cari' }}" />
                        @if ($item->snomed_code)
                            <x-atoms.button
                                wire:click="confirmDelete('{{ $item->code }}', '{{ addslashes($item->display) }}', '{{ $item->version }}')"
                                size="sm" icon="trash" variant="ghost" tooltip="Hapus" class="text-red-500" />
                        @endif
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @empty
                <x-molecules.table-row>
                    <x-atoms.table-cell colspan="4" align="center" class="py-16">
                        <div class="flex flex-col items-center gap-3">
                            <div
                                class="flex items-center justify-center w-14 h-14 rounded-2xl bg-zinc-100 dark:bg-primary-dark-700">
                                <flux:icon name="document-text"
                                    class="w-7 h-7 text-zinc-300 dark:text-primary-dark-500" />
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-zinc-500 dark:text-primary-dark-400">Tidak ada data
                                    ICD-PM</p>
                                <p class="mt-0.5 text-xs text-zinc-400 dark:text-primary-dark-500">Coba ubah kata kunci
                                    pencarian atau filter versi</p>
                            </div>
                        </div>
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @endforelse
        </x-organisms.table>

        @if ($items->hasPages())
            <x-slot:footer>
                {{ $items->links() }}
            </x-slot:footer>
        @endif
    </x-organisms.data-panel>

    {{-- Modal Pencarian SNOMED CT --}}
    <x-organisms.modal wire:model="showSnomedSearchModal" maxWidth="4xl" title="Pilih Kode SNOMED CT">
        <div class="space-y-4">
            <div>
                
                <flux:text class="mt-0.5">
                    Untuk ICD-PM: <span
                        class="font-semibold text-zinc-700 dark:text-primary-dark-200">{{ $selectedName }}</span>
                    <span class="font-mono text-xs text-zinc-400 ml-1">({{ $selectedCode }})</span>
                </flux:text>
            </div>

            <livewire:components.snomed-search defaultTag="disorder" :initialSearch="$snomedInitialSearch" :key="'snomed-icd-pm-' . ($selectedCode ?? '') . '-' . ($selectedVersion ?? '')" />

            
        <x-slot:footer>
            <div class="flex justify-end">
                <x-atoms.button wire:click="$set('showSnomedSearchModal', false)" variant="ghost">Tutup</x-atoms.button>
            </div>
        </x-slot:footer>
    </div>
    </x-organisms.modal>

    {{-- Modal Konfirmasi Hapus --}}
    <x-organisms.modal wire:model="showDeleteModal" maxWidth="sm" title="Hapus Mapping">
        <div class="space-y-6">
            <div class="flex items-center gap-4">
                <div
                    class="flex items-center justify-center w-12 h-12 rounded-2xl bg-red-100 dark:bg-red-900/30 shrink-0">
                    <flux:icon name="exclamation-triangle" class="w-6 h-6 text-red-600 dark:text-red-400" />
                </div>
                <div>
                    
                    <flux:text class="mt-0.5">Tindakan ini tidak dapat dibatalkan.</flux:text>
                </div>
            </div>
            <div
                class="p-4 space-y-2 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/40 border border-zinc-200 dark:border-primary-dark-700">
                <div class="flex items-center gap-3">
                    <span class="text-xs font-medium text-zinc-400 w-14 shrink-0">Kode</span>
                    <span
                        class="font-mono text-sm font-bold text-primary-600 dark:text-primary-400">{{ $deleteCode }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs font-medium text-zinc-400 w-14 shrink-0">Versi</span>
                    <span
                        class="font-mono text-sm text-zinc-600 dark:text-primary-dark-400">{{ $deleteVersion }}</span>
                </div>
                <div class="flex items-start gap-3">
                    <span class="text-xs font-medium text-zinc-400 w-14 shrink-0 mt-0.5">Nama</span>
                    <span class="text-sm text-zinc-700 dark:text-primary-dark-300">{{ $deleteName }}</span>
                </div>
            </div>
            
        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <x-atoms.button wire:click="cancelDelete" variant="ghost">Batal</x-atoms.button>
                <x-atoms.button wire:click="deleteMapping" variant="danger">Hapus Mapping</x-atoms.button>
            </div>
        </x-slot:footer>
    </div>
    </x-organisms.modal>

    {{-- Load modal external --}}
    @include('pages.local.clinical.partials.ai-mapping-modal')
</div>
