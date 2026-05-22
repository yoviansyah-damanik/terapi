<?php

use App\Jobs\SyncBpjsIcd10Job;
use App\Models\Bpjs\BpjsIcd10;
use App\Models\Mapping\Icd10Map;
use App\Models\Terminology\Icd10;
use App\Traits\WithSmartMapping;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('ICD-10 — Mapping & UUID')] class extends Component {
    use WithPagination, WithSmartMapping;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterVersion = '';

    #[Url]
    public string $filterStatus = ''; // '', 'snomed_only', 'bpjs_only', 'complete', 'empty'

    public int $perPage = 25;

    // State modal SNOMED
    public bool $showSnomedSearchModal = false;
    public ?string $selectedCode = null;
    public ?string $selectedName = null;
    public string $snomedSemanticTag = 'disorder';

    // State modal BPJS Sync
    public bool $showSyncModal = false;

    // State modal BPJS Detail
    public bool $showBpjsDetailModal = false;
    public ?BpjsIcd10 $selectedBpjsIcd = null;

    // State konfirmasi hapus SNOMED
    public bool $showDeleteSnomedModal = false;
    public ?string $deleteSnomedCode = null;
    public ?string $deleteSnomedName = null;

    // State konfirmasi hapus UUID BPJS
    public bool $showDeleteBpjsModal = false;
    public ?string $deleteBpjsCode = null;
    public string $deleteBpjsName = '';

    public string $snomedInitialSearch = '';

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedFilterVersion(): void { $this->resetPage(); }
    public function updatedFilterStatus(): void { $this->resetPage(); }

    // ── SNOMED actions ──────────────────────────────────────────────────────

    protected function saveSmartMapping(string $sourceCode, string $snomedCode, string $snomedTerm): bool
    {
        Icd10Map::updateOrCreate(
            ['icd10_code' => $sourceCode],
            ['system_code' => $snomedCode, 'system_term' => $snomedTerm, 'system_display' => 'http://snomed.info/sct'],
        );
        return true;
    }

    protected function getUnmappedItemsForPage(int $page, bool $forAiQueue = false): array
    {
        $items = Icd10::query()
            ->select('icd10.*', 'map_icd10.system_code as snomed_code')
            ->leftJoin('map_icd10', 'icd10.code', '=', 'map_icd10.icd10_code')
            ->when($this->search, fn($q) => $q->where('icd10.code', 'like', "%{$this->search}%")->orWhere('icd10.display', 'like', "%{$this->search}%"))
            ->when($this->filterVersion, fn($q) => $q->where('icd10.version', $this->filterVersion))
            ->orderBy('icd10.code')
            ->paginate($this->perPage, ['*'], 'page', $page);

        $unmapped = [];
        foreach ($items as $item) {
            if (!$item->snomed_code) {
                $unmapped[] = ['code' => $item->code, 'name' => $item->display];
            }
        }
        return $unmapped;
    }

    public function openSnomedModal(string $code, string $name): void
    {
        $this->selectedCode = $code;
        $this->selectedName = $name;
        $this->snomedInitialSearch = Icd10Map::where('icd10_code', $code)->value('system_term') ?? '';
        $this->showSnomedSearchModal = true;
    }

    #[On('snomed-selected')]
    public function snomedSelected(string $system_code, string $system_term, string $system_display, string $category): void
    {
        Icd10Map::updateOrCreate(
            ['icd10_code' => $this->selectedCode],
            ['system_code' => $system_code, 'system_term' => $system_term, 'system_display' => $system_display],
        );
        Cache::flush();
        $this->showSnomedSearchModal = false;
        $this->toastSuccess('Mapping SNOMED berhasil disimpan.');
    }

    public function confirmDeleteSnomed(string $code, string $name): void
    {
        $this->deleteSnomedCode = $code;
        $this->deleteSnomedName = $name;
        $this->showDeleteSnomedModal = true;
    }

    public function deleteSnomed(): void
    {
        Icd10Map::where('icd10_code', $this->deleteSnomedCode)->delete();
        Cache::flush();
        $this->showDeleteSnomedModal = false;
        $this->reset(['deleteSnomedCode', 'deleteSnomedName']);
        $this->toastSuccess('Mapping SNOMED berhasil dihapus.');
    }

    // ── BPJS UUID actions ───────────────────────────────────────────────────

    public function generateBpjsUuid(string $code, string $display): void
    {
        if (BpjsIcd10::where('code', $code)->exists()) {
            $this->toastWarning('Kode ICD-10 ini sudah memiliki UUID BPJS.');
            return;
        }

        BpjsIcd10::create(['code' => $code, 'display' => $display]);
        $this->toastSuccess("UUID BPJS berhasil di-generate untuk {$code}.");
    }

    public function syncAllBpjs(): void
    {
        SyncBpjsIcd10Job::dispatch();
        $this->showSyncModal = false;
        $this->toastSuccess('Sync semua UUID BPJS dijadwalkan. Proses berjalan di background.');
    }

    public function viewBpjsDetail(string $code): void
    {
        $this->selectedBpjsIcd = BpjsIcd10::where('code', $code)->first();
        $this->showBpjsDetailModal = true;
    }

    public function confirmDeleteBpjs(string $code, string $display): void
    {
        $this->deleteBpjsCode = $code;
        $this->deleteBpjsName = $display;
        $this->showDeleteBpjsModal = true;
    }

    public function deleteBpjs(): void
    {
        BpjsIcd10::where('code', $this->deleteBpjsCode)->delete();
        $this->showDeleteBpjsModal = false;
        $this->reset(['deleteBpjsCode', 'deleteBpjsName']);
        $this->toastSuccess('UUID BPJS ICD-10 berhasil dihapus.');
    }

    // ── Data ─────────────────────────────────────────────────────────────────

    public function with(): array
    {
        $halaman = LengthAwarePaginator::resolveCurrentPage();
        $kunciData = 'icd10.merged.' . md5("{$this->search}|{$this->filterVersion}|{$this->filterStatus}|{$this->perPage}|{$halaman}");

        $bpjsRegistered = BpjsIcd10::pluck('id', 'code');

        $items = Cache::remember($kunciData, 300, function () use ($bpjsRegistered) {
            return Icd10::query()
                ->select(
                    'icd10.code',
                    \DB::raw('MAX(icd10.display) as display'),
                    \DB::raw('MAX(icd10.version) as version'),
                    \DB::raw('MAX(map_icd10.system_code) as snomed_code'),
                    \DB::raw('MAX(map_icd10.system_term) as snomed_term'),
                )
                ->leftJoin('map_icd10', 'icd10.code', '=', 'map_icd10.icd10_code')
                ->when($this->search, fn($q) => $q
                    ->where('icd10.code', 'like', "%{$this->search}%")
                    ->orWhere('icd10.display', 'like', "%{$this->search}%")
                )
                ->when($this->filterVersion, fn($q) => $q->where('icd10.version', $this->filterVersion))
                ->when($this->filterStatus === 'snomed_only',  fn($q) => $q->whereNotNull('map_icd10.system_code')->whereNotIn('icd10.code', $bpjsRegistered->keys()->toArray()))
                ->when($this->filterStatus === 'bpjs_only',    fn($q) => $q->whereNull('map_icd10.system_code')->whereIn('icd10.code', $bpjsRegistered->keys()->toArray()))
                ->when($this->filterStatus === 'complete',     fn($q) => $q->whereNotNull('map_icd10.system_code')->whereIn('icd10.code', $bpjsRegistered->keys()->toArray()))
                ->when($this->filterStatus === 'empty',        fn($q) => $q->whereNull('map_icd10.system_code')->whereNotIn('icd10.code', $bpjsRegistered->keys()->toArray()))
                ->groupBy('icd10.code')
                ->orderBy('icd10.code')
                ->paginate($this->perPage);
        });

        $totalBpjs    = BpjsIcd10::count();
        $totalSnomed  = Icd10Map::count();
        $total        = Icd10::distinct('code')->count('code');

        return [
            'items'          => $items,
            'bpjsRegistered' => $bpjsRegistered,
            'versions'       => Icd10::getVersions(),
            'total'          => $total,
            'totalBpjs'      => $totalBpjs,
            'totalSnomed'    => $totalSnomed,
            'unsyncedBpjs'   => max(0, $total - $totalBpjs),
        ];
    }
}; ?>

<div>
    <x-ui.page-header title="ICD-10 — Mapping & UUID" subtitle="Kelola mapping SNOMED CT dan UUID BPJS untuk kode diagnosa ICD-10 dalam satu tampilan">
        <x-slot name="actions">
            <flux:dropdown>
                <x-atoms.button variant="primary" icon="sparkles" class="whitespace-nowrap">Smart Bulk Map</x-atoms.button>
                <flux:menu>
                    <flux:menu.item icon="bolt" wire:click="smartMapPage('snowstorm')">Snowstorm (Per Halaman)</flux:menu.item>
                    <flux:menu.item icon="cpu-chip" wire:click="smartMapPage('ai')">AI Provider (Diantrikan)</flux:menu.item>
                </flux:menu>
            </flux:dropdown>
            <x-atoms.button wire:click="$set('showSyncModal', true)" variant="secondary" icon="arrow-path">
                Sync UUID BPJS
            </x-atoms.button>
        </x-slot>
    </x-ui.page-header>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-3 gap-4 mb-6">
        <x-organisms.stat-card title="Total ICD-10" :value="number_format($total)" icon="hashtag" color="zinc" />
        <x-organisms.stat-card title="Ter-mapping SNOMED" :value="number_format($totalSnomed)" icon="sparkles" color="violet" :subtitle="'dari ' . number_format($total) . ' total'" />
        <x-organisms.stat-card title="UUID BPJS" :value="number_format($totalBpjs)" icon="identification" color="blue" :subtitle="'dari ' . number_format($total) . ' total'" />
    </div>

    <x-organisms.data-panel :padding="false">
        <x-slot:filter>
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-48">
                    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                        placeholder="Cari kode atau nama ICD-10..." clearable />
                </div>
                @if (count($versions) > 0)
                    <flux:select wire:model.live="filterVersion" class="w-40">
                        <flux:select.option value="">Semua Versi</flux:select.option>
                        @foreach ($versions as $v)
                            <flux:select.option value="{{ $v }}">{{ $v }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif
                <flux:select wire:model.live="filterStatus" class="w-48">
                    <flux:select.option value="">Semua Status</flux:select.option>
                    <flux:select.option value="complete">Lengkap (SNOMED + UUID)</flux:select.option>
                    <flux:select.option value="snomed_only">SNOMED saja</flux:select.option>
                    <flux:select.option value="bpjs_only">UUID BPJS saja</flux:select.option>
                    <flux:select.option value="empty">Belum keduanya</flux:select.option>
                </flux:select>
                <flux:select wire:model.live="perPage" class="w-36">
                    <flux:select.option value="25">25 / halaman</flux:select.option>
                    <flux:select.option value="50">50 / halaman</flux:select.option>
                    <flux:select.option value="100">100 / halaman</flux:select.option>
                </flux:select>
            </div>
        </x-slot:filter>

        <x-organisms.table>
            <x-slot:headings>
                <x-atoms.table-heading class="w-28">Kode</x-atoms.table-heading>
                <x-atoms.table-heading>Deskripsi ICD-10</x-atoms.table-heading>
                <x-atoms.table-heading>
                    <div class="flex items-center gap-1.5">
                        <flux:icon name="sparkles" class="w-3.5 h-3.5 text-violet-400" />
                        SNOMED CT
                    </div>
                </x-atoms.table-heading>
                <x-atoms.table-heading>
                    <div class="flex items-center gap-1.5">
                        <flux:icon name="identification" class="w-3.5 h-3.5 text-emerald-400" />
                        UUID BPJS
                    </div>
                </x-atoms.table-heading>
                <x-atoms.table-heading align="center" class="w-44">Aksi</x-atoms.table-heading>
            </x-slot:headings>

            @forelse ($items as $item)
                @php $bpjsUuid = $bpjsRegistered[$item->code] ?? null; @endphp
                <x-molecules.table-row wire:key="icd10-{{ $item->code }}">

                    <x-atoms.table-cell nowrap>
                        <span class="inline-block font-mono text-xs font-bold px-2 py-1 rounded-md
                            bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300
                            ring-1 ring-primary-100 dark:ring-primary-800/40">
                            {{ $item->code }}
                        </span>
                    </x-atoms.table-cell>

                    <x-atoms.table-cell>
                        <p class="text-sm font-medium text-zinc-800 dark:text-primary-dark-100 leading-snug">{{ $item->display }}</p>
                    </x-atoms.table-cell>

                    <x-atoms.table-cell>
                        @if ($item->snomed_code)
                            <div class="flex items-start gap-2">
                                <span class="mt-1 w-2 h-2 rounded-full bg-violet-400 shrink-0 ring-2 ring-violet-100 dark:ring-violet-900/50"></span>
                                <div class="min-w-0">
                                    <p class="font-mono text-xs font-bold text-violet-700 dark:text-violet-400">{{ $item->snomed_code }}</p>
                                    <p class="mt-0.5 text-xs text-zinc-500 dark:text-primary-dark-400 leading-snug line-clamp-2">{{ $item->snomed_term }}</p>
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
                        @if ($bpjsUuid)
                            <span class="font-mono text-xs font-bold text-emerald-700 dark:text-emerald-400">{{ $bpjsUuid }}</span>
                        @else
                            <span class="text-xs italic text-zinc-400 dark:text-primary-dark-500">Belum terdaftar</span>
                        @endif
                    </x-atoms.table-cell>

                    <x-atoms.table-cell align="center" action nowrap>
                        {{-- Grup aksi SNOMED --}}
                        <div class="flex items-center gap-0.5 border-r border-zinc-200 dark:border-primary-dark-600 pr-2 mr-2">
                            @if (!$item->snomed_code)
                                <flux:dropdown>
                                    <x-atoms.button variant="ghost" size="sm" icon="sparkles"
                                        class="text-violet-500 hover:bg-violet-50 dark:hover:bg-violet-900/20"
                                        tooltip="Auto Map SNOMED" />
                                    <flux:menu>
                                        <flux:menu.item icon="bolt" wire:click="smartMap('{{ $item->code }}', '{{ addslashes($item->display) }}', 'snowstorm')">Via Snowstorm</flux:menu.item>
                                        <flux:menu.item icon="cpu-chip" wire:click="smartMap('{{ $item->code }}', '{{ addslashes($item->display) }}', 'ai')">Via AI Provider</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            @endif
                            <x-atoms.button
                                wire:click="openSnomedModal('{{ $item->code }}', '{{ addslashes($item->display) }}')"
                                size="sm" icon="{{ $item->snomed_code ? 'pencil-square' : 'magnifying-glass' }}"
                                variant="ghost" tooltip="{{ $item->snomed_code ? 'Edit SNOMED' : 'Cari SNOMED' }}" />
                            @if ($item->snomed_code)
                                <x-atoms.button
                                    wire:click="confirmDeleteSnomed('{{ $item->code }}', '{{ addslashes($item->display) }}')"
                                    size="sm" icon="trash" variant="ghost" tooltip="Hapus SNOMED"
                                    class="text-red-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20" />
                            @endif
                        </div>

                        {{-- Grup aksi UUID BPJS --}}
                        <div class="flex items-center gap-0.5">
                            @if (!$bpjsUuid)
                                <x-atoms.button
                                    wire:click="generateBpjsUuid('{{ addslashes($item->code) }}', '{{ addslashes($item->display) }}')"
                                    wire:target="generateBpjsUuid('{{ addslashes($item->code) }}', '{{ addslashes($item->display) }}')"
                                    size="sm" variant="ghost" icon="plus-circle"
                                    class="text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/20"
                                    tooltip="Generate UUID BPJS" />
                            @else
                                <x-atoms.button variant="ghost" wire:click="viewBpjsDetail('{{ addslashes($item->code) }}')" size="sm" icon="eye" tooltip="Lihat UUID BPJS" />
                                <x-atoms.button variant="ghost"
                                    wire:click="confirmDeleteBpjs('{{ addslashes($item->code) }}', '{{ addslashes($item->display) }}')"
                                    size="sm" icon="trash" tooltip="Hapus UUID BPJS"
                                    class="text-red-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20" />
                            @endif
                        </div>
                    </x-atoms.table-cell>

                </x-molecules.table-row>
            @empty
                <x-molecules.table-row>
                    <x-atoms.table-cell colspan="5" align="center" class="py-16">
                        <div class="flex flex-col items-center gap-3">
                            <div class="flex items-center justify-center w-14 h-14 rounded-2xl bg-zinc-100 dark:bg-primary-dark-700">
                                <flux:icon name="document-text" class="w-7 h-7 text-zinc-300 dark:text-primary-dark-500" />
                            </div>
                            <p class="text-sm font-semibold text-zinc-500 dark:text-primary-dark-400">Tidak ada data ICD-10 ditemukan</p>
                        </div>
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @endforelse
        </x-organisms.table>

        @if ($items->hasPages())
            <x-slot:footer>{{ $items->links() }}</x-slot:footer>
        @endif
    </x-organisms.data-panel>

    {{-- Modal SNOMED Search --}}
    <x-organisms.modal wire:model="showSnomedSearchModal" maxWidth="4xl" title="Pilih Kode SNOMED CT">
        <div class="space-y-4">
            <div>
                
                <flux:text class="mt-0.5">
                    Untuk ICD-10: <span class="font-semibold text-zinc-700 dark:text-primary-dark-200">{{ $selectedName }}</span>
                    <span class="font-mono text-xs text-zinc-400 ml-1">({{ $selectedCode }})</span>
                </flux:text>
            </div>
            <livewire:components.snomed-search defaultTag="disorder" :initialSearch="$snomedInitialSearch" :key="'snomed-icd10-' . ($selectedCode ?? '')" />
            
        <x-slot:footer>
            <div class="flex justify-end">
                <x-atoms.button wire:click="$set('showSnomedSearchModal', false)" variant="ghost">Tutup</x-atoms.button>
            </div>
        </x-slot:footer>
    </div>
    </x-organisms.modal>

    {{-- Modal Sync UUID BPJS --}}
    <x-organisms.modal wire:model="showSyncModal" title="Sync Semua UUID BPJS ICD-10" maxWidth="md">
        <div class="space-y-5">
            <div class="flex items-center gap-4">
                <div class="flex items-center justify-center w-12 h-12 rounded-full bg-primary-100 dark:bg-primary-900/30">
                    <flux:icon name="arrow-path" class="w-6 h-6 text-primary-600 dark:text-primary-400" />
                </div>
                <p class="text-sm text-zinc-500 dark:text-primary-dark-400">
                    {{ number_format($unsyncedBpjs) }} kode ICD-10 belum memiliki UUID BPJS
                </p>
            </div>
            <div class="p-3.5 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-[11px] text-amber-800 dark:text-amber-200 leading-relaxed italic">
                <flux:icon name="exclamation-triangle" class="inline w-3.5 h-3.5 mr-1 text-amber-500" />
                UUID baru akan di-generate untuk semua kode yang belum terdaftar. Proses berjalan di background (queue worker).
            </div>
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-atoms.button variant="ghost" wire:click="$set('showSyncModal', false)">Batal</x-atoms.button>
                <x-atoms.button variant="primary" icon="arrow-path" wire:click="syncAllBpjs" wire:target="syncAllBpjs">Mulai Sync</x-atoms.button>
            </div>
        </x-slot>
    </x-organisms.modal>

    {{-- Modal Detail UUID BPJS --}}
    <x-organisms.modal wire:model="showBpjsDetailModal" title="Detail UUID BPJS ICD-10" maxWidth="md">
        @if ($selectedBpjsIcd)
            <dl class="space-y-4">
                <div>
                    <dt class="text-[10px] uppercase font-bold text-zinc-400 dark:text-primary-dark-500 mb-1">Kode ICD-10</dt>
                    <dd class="font-mono text-sm font-bold text-primary-600 dark:text-primary-400">{{ $selectedBpjsIcd->code }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] uppercase font-bold text-zinc-400 dark:text-primary-dark-500 mb-1">Resource ID (UUID)</dt>
                    <dd class="font-mono text-sm font-bold text-emerald-600 dark:text-emerald-400 break-all">{{ $selectedBpjsIcd->id }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] uppercase font-bold text-zinc-400 dark:text-primary-dark-500 mb-1">Dibuat</dt>
                    <dd class="text-sm text-zinc-600 dark:text-primary-dark-300">{{ $selectedBpjsIcd->created_at?->format('d M Y, H:i') }}</dd>
                </div>
            </dl>
            <x-slot name="footer">
                <div class="flex justify-end">
                    <x-atoms.button variant="ghost" wire:click="$set('showBpjsDetailModal', false)">Tutup</x-atoms.button>
                </div>
            </x-slot>
        @endif
    </x-organisms.modal>

    {{-- Modal Hapus SNOMED --}}
    <x-organisms.modal wire:model="showDeleteSnomedModal" maxWidth="sm" title="Hapus Mapping SNOMED">
        <div class="space-y-6">
            <div class="flex items-center gap-4">
                <div class="flex items-center justify-center w-12 h-12 rounded-2xl bg-red-100 dark:bg-red-900/30 shrink-0">
                    <flux:icon name="exclamation-triangle" class="w-6 h-6 text-red-600 dark:text-red-400" />
                </div>
                <div>
                    
                    <flux:text class="mt-0.5">Tindakan ini tidak dapat dibatalkan.</flux:text>
                </div>
            </div>
            <div class="p-4 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/40 border border-zinc-200 dark:border-primary-dark-700 space-y-2">
                <div class="flex items-center gap-3">
                    <span class="text-xs font-medium text-zinc-400 w-10 shrink-0">Kode</span>
                    <span class="font-mono text-sm font-bold text-primary-600 dark:text-primary-400">{{ $deleteSnomedCode }}</span>
                </div>
                <div class="flex items-start gap-3">
                    <span class="text-xs font-medium text-zinc-400 w-10 shrink-0 mt-0.5">Nama</span>
                    <span class="text-sm text-zinc-700 dark:text-primary-dark-300">{{ $deleteSnomedName }}</span>
                </div>
            </div>
            
        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <x-atoms.button wire:click="$set('showDeleteSnomedModal', false)" variant="ghost">Batal</x-atoms.button>
                <x-atoms.button wire:click="deleteSnomed" variant="danger">Hapus Mapping</x-atoms.button>
            </div>
        </x-slot:footer>
    </div>
    </x-organisms.modal>

    {{-- Modal Hapus UUID BPJS --}}
    <x-organisms.modal wire:model="showDeleteBpjsModal" title="Hapus UUID BPJS?" maxWidth="md">
        <div class="space-y-5">
            <div class="flex items-center gap-4">
                <div class="flex items-center justify-center w-12 h-12 rounded-2xl bg-red-50 dark:bg-red-900/20 shrink-0">
                    <flux:icon name="trash" class="w-6 h-6 text-red-600 dark:text-red-400" />
                </div>
                <p class="text-sm text-zinc-500 dark:text-primary-dark-400">
                    UUID BPJS untuk <strong class="text-zinc-800 dark:text-white">{{ $deleteBpjsCode }}</strong> akan dihapus.
                </p>
            </div>
            <div class="p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-[11px] text-amber-700 dark:text-amber-300 leading-relaxed italic">
                <flux:icon name="exclamation-triangle" class="inline w-3 h-3 mr-1" />
                UUID yang sudah digunakan di bundle BPJS tidak boleh dihapus untuk menjaga konsistensi data.
            </div>
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-atoms.button variant="ghost" wire:click="$set('showDeleteBpjsModal', false)">Batal</x-atoms.button>
                <x-atoms.button variant="danger" wire:click="deleteBpjs" icon="trash">Hapus UUID</x-atoms.button>
            </div>
        </x-slot>
    </x-organisms.modal>

    @include('pages.local.clinical.partials.ai-mapping-modal')
</div>
