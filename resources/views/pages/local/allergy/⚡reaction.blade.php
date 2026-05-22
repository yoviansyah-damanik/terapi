<?php

use App\Jobs\SyncBpjsAllergyReactionsJob;
use App\Models\Bpjs\BpjsAllergyReaction;
use App\Models\Mapping\AllergyReactionMap;
use App\Models\Simrs\AlergiReaksi;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;

new #[Layout('layouts::app')] #[Title('Reaksi Alergi — Mapping & UUID')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $filterCategory = '';
    public int $perPage = 25;

    // State modal SNOMED
    public bool $showSnomedSearchModal = false;
    public ?int $selectedId = null;
    public ?string $selectedName = null;

    // State modal BPJS Sync
    public bool $showSyncModal = false;

    // State modal BPJS Detail
    public bool $showBpjsDetailModal = false;
    public ?BpjsAllergyReaction $selectedBpjsItem = null;

    // State konfirmasi hapus SNOMED
    public bool $showDeleteSnomedModal = false;
    public ?int $deleteSnomedId = null;
    public ?string $deleteSnomedName = null;

    // State konfirmasi hapus UUID BPJS
    public bool $showDeleteBpjsModal = false;
    public ?string $deleteBpjsCode = null;
    public string $deleteBpjsName = '';

    public string $snomedInitialSearch = '';

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedFilterCategory(): void { $this->resetPage(); }

    // ── SNOMED actions ──────────────────────────────────────────────────────

    public function openSnomedModal(int $id, string $name): void
    {
        $this->selectedId = $id;
        $this->selectedName = $name;
        $this->snomedInitialSearch = AllergyReactionMap::where('reaction_id', $id)->value('system_term') ?? '';
        $this->showSnomedSearchModal = true;
    }

    #[On('snomed-selected')]
    public function snomedSelected(string $system_code, string $system_term, string $system_display, string $category): void
    {
        AllergyReactionMap::updateOrCreate(
            ['reaction_id' => $this->selectedId],
            ['system_code' => $system_code, 'system_term' => $system_term, 'system_display' => $system_display],
        );
        AllergyReactionMap::clearCache();
        $this->showSnomedSearchModal = false;
        $this->toastSuccess('Mapping SNOMED berhasil disimpan.');
    }

    public function confirmDeleteSnomed(int $id, string $name): void
    {
        $this->deleteSnomedId = $id;
        $this->deleteSnomedName = $name;
        $this->showDeleteSnomedModal = true;
    }

    public function deleteSnomed(): void
    {
        AllergyReactionMap::where('reaction_id', $this->deleteSnomedId)->delete();
        AllergyReactionMap::clearCache();
        $this->showDeleteSnomedModal = false;
        $this->reset(['deleteSnomedId', 'deleteSnomedName']);
        $this->toastSuccess('Mapping SNOMED berhasil dihapus.');
    }

    // ── BPJS UUID actions ───────────────────────────────────────────────────

    public function generateBpjsUuid(int $id, string $name): void
    {
        $localCode = (string) $id;
        if (BpjsAllergyReaction::where('local_code', $localCode)->exists()) {
            $this->toastWarning('Reaksi alergi ini sudah memiliki UUID BPJS.');
            return;
        }

        BpjsAllergyReaction::create(['local_code' => $localCode, 'name' => $name]);
        $this->toastSuccess("UUID BPJS berhasil di-generate untuk {$name}.");
    }

    public function syncAllBpjs(): void
    {
        SyncBpjsAllergyReactionsJob::dispatch();
        $this->showSyncModal = false;
        $this->toastSuccess('Sync semua UUID BPJS dijadwalkan. Proses berjalan di background.');
    }

    public function viewBpjsDetail(string $localCode): void
    {
        $this->selectedBpjsItem = BpjsAllergyReaction::where('local_code', $localCode)->first();
        $this->showBpjsDetailModal = true;
    }

    public function confirmDeleteBpjs(string $localCode, string $name): void
    {
        $this->deleteBpjsCode = $localCode;
        $this->deleteBpjsName = $name;
        $this->showDeleteBpjsModal = true;
    }

    public function deleteBpjs(): void
    {
        BpjsAllergyReaction::where('local_code', $this->deleteBpjsCode)->delete();
        $this->showDeleteBpjsModal = false;
        $this->reset(['deleteBpjsCode', 'deleteBpjsName']);
        $this->toastSuccess('UUID BPJS Reaksi Alergi berhasil dihapus.');
    }

    // ── Data ─────────────────────────────────────────────────────────────────

    public function with(): array
    {
        try {
            $items = AlergiReaksi::query()
                ->when($this->search, fn($q) => $q->where('nama_reaksi', 'like', "%{$this->search}%"))
                ->when($this->filterCategory, fn($q) => $q->where('kategori', $this->filterCategory))
                ->orderBy('nama_reaksi')
                ->paginate($this->perPage);

            $categories = AlergiReaksi::query()->whereNotNull('kategori')->distinct()->orderBy('kategori')->pluck('kategori')->toArray();
        } catch (\Exception) {
            $items = new LengthAwarePaginator([], 0, $this->perPage);
            $categories = [];
        }

        $ids = $items->pluck('id')->toArray();
        $mappings = AllergyReactionMap::whereIn('reaction_id', $ids)->get()->keyBy('reaction_id');

        $bpjsRegistered = BpjsAllergyReaction::whereIn('local_code', array_map('strval', $ids))
            ->pluck('id', 'local_code');

        $items->getCollection()->transform(function ($item) use ($mappings, $bpjsRegistered) {
            $map = $mappings->get($item->id);
            $item->snomed_code = $map?->system_code;
            $item->snomed_term = $map?->system_term;
            $item->bpjs_uuid   = $bpjsRegistered->get((string) $item->id);
            return $item;
        });

        $totalBpjs   = BpjsAllergyReaction::count();
        $totalSimrs  = AlergiReaksi::count();
        $totalSnomed = AllergyReactionMap::count();

        return [
            'items'        => $items,
            'categories'   => $categories,
            'totalSimrs'   => $totalSimrs,
            'totalSnomed'  => $totalSnomed,
            'totalBpjs'    => $totalBpjs,
            'unsyncedBpjs' => max(0, $totalSimrs - $totalBpjs),
        ];
    }
}; ?>

<div>
    <x-ui.page-header title="Reaksi Alergi — Mapping & UUID"
        subtitle="Kelola mapping SNOMED CT dan UUID BPJS untuk data reaksi alergi dalam satu tampilan">
        <x-slot name="actions">
            <x-atoms.button wire:click="$set('showSyncModal', true)" variant="secondary" icon="arrow-path">
                Sync UUID BPJS
            </x-atoms.button>
        </x-slot>
    </x-ui.page-header>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-3 gap-4 mb-6">
        <x-organisms.stat-card title="Total Reaksi Alergi" :value="number_format($totalSimrs)" icon="fire" color="zinc" />
        <x-organisms.stat-card title="Ter-mapping SNOMED" :value="number_format($totalSnomed)" icon="sparkles" color="violet" :subtitle="'dari ' . number_format($totalSimrs) . ' total'" />
        <x-organisms.stat-card title="UUID BPJS" :value="number_format($totalBpjs)" icon="identification" color="blue" :subtitle="'dari ' . number_format($totalSimrs) . ' total'" />
    </div>

    <x-organisms.data-panel>
        <x-slot:filter>
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-40">
                    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                        placeholder="Cari nama reaksi alergi..." clearable />
                </div>
                <flux:select wire:model.live="filterCategory" class="w-44">
                    <flux:select.option value="">Semua Kategori</flux:select.option>
                    @foreach ($categories as $category)
                        <flux:select.option value="{{ $category }}">{{ $category }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="perPage" class="w-40">
                    <flux:select.option value="25">25 / halaman</flux:select.option>
                    <flux:select.option value="50">50 / halaman</flux:select.option>
                    <flux:select.option value="100">100 / halaman</flux:select.option>
                </flux:select>
            </div>
        </x-slot:filter>

        <x-organisms.table>
            <x-slot:headings>
                <x-atoms.table-heading class="w-16">ID</x-atoms.table-heading>
                <x-atoms.table-heading>Nama Reaksi</x-atoms.table-heading>
                <x-atoms.table-heading class="w-36">Kategori</x-atoms.table-heading>
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
                <x-molecules.table-row wire:key="reaksi-{{ $item->id }}">
                    <x-atoms.table-cell nowrap>
                        <span class="inline-block font-mono text-xs font-bold px-2 py-1 rounded-md bg-zinc-100 dark:bg-primary-dark-700 text-zinc-600 dark:text-primary-dark-300 ring-1 ring-zinc-200 dark:ring-primary-dark-600">
                            {{ $item->id }}
                        </span>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        <p class="text-sm font-medium text-zinc-800 dark:text-primary-dark-100">{{ $item->nama_reaksi }}</p>
                        @if ($item->keterangan)
                            <p class="mt-0.5 text-xs text-zinc-400 dark:text-primary-dark-500 leading-snug line-clamp-1">{{ $item->keterangan }}</p>
                        @endif
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        @if ($item->kategori)
                            <flux:badge color="purple" size="sm">{{ $item->kategori }}</flux:badge>
                        @else
                            <span class="text-xs text-zinc-300 dark:text-primary-dark-600">—</span>
                        @endif
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
                        @if ($item->bpjs_uuid)
                            <span class="font-mono text-xs font-bold text-emerald-700 dark:text-emerald-400">{{ $item->bpjs_uuid }}</span>
                        @else
                            <span class="text-xs italic text-zinc-400 dark:text-primary-dark-500">Belum terdaftar</span>
                        @endif
                    </x-atoms.table-cell>
                    <x-atoms.table-cell align="center" action nowrap>
                        {{-- Grup aksi SNOMED --}}
                        <div class="flex items-center gap-0.5 border-r border-zinc-200 dark:border-primary-dark-600 pr-2 mr-2">
                            <x-atoms.button
                                wire:click="openSnomedModal({{ $item->id }}, '{{ addslashes($item->nama_reaksi) }}')"
                                size="sm" icon="{{ $item->snomed_code ? 'pencil-square' : 'plus' }}"
                                variant="{{ $item->snomed_code ? 'ghost' : 'primary' }}"
                                tooltip="{{ $item->snomed_code ? 'Edit SNOMED' : 'Petakan SNOMED' }}" />
                            @if ($item->snomed_code)
                                <x-atoms.button
                                    wire:click="confirmDeleteSnomed({{ $item->id }}, '{{ addslashes($item->nama_reaksi) }}')"
                                    size="sm" icon="trash" variant="ghost" tooltip="Hapus SNOMED"
                                    class="text-red-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20" />
                            @endif
                        </div>

                        {{-- Grup aksi UUID BPJS --}}
                        <div class="flex items-center gap-0.5">
                            @if (!$item->bpjs_uuid)
                                <x-atoms.button
                                    wire:click="generateBpjsUuid({{ $item->id }}, '{{ addslashes($item->nama_reaksi) }}')"
                                    size="sm" variant="ghost" icon="plus-circle"
                                    class="text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/20"
                                    tooltip="Generate UUID BPJS" />
                            @else
                                <x-atoms.button variant="ghost" wire:click="viewBpjsDetail('{{ $item->id }}')" size="sm" icon="eye" tooltip="Lihat UUID BPJS" />
                                <x-atoms.button variant="ghost"
                                    wire:click="confirmDeleteBpjs('{{ $item->id }}', '{{ addslashes($item->nama_reaksi) }}')"
                                    size="sm" icon="trash" tooltip="Hapus UUID BPJS"
                                    class="text-red-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20" />
                            @endif
                        </div>
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @empty
                <x-molecules.table-row>
                    <x-atoms.table-cell colspan="6" align="center" class="py-16">
                        <div class="flex flex-col items-center gap-3">
                            <div class="flex items-center justify-center w-14 h-14 rounded-2xl bg-zinc-100 dark:bg-primary-dark-700">
                                <flux:icon name="exclamation-circle" class="w-7 h-7 text-zinc-300 dark:text-primary-dark-500" />
                            </div>
                            <p class="text-sm font-semibold text-zinc-500 dark:text-primary-dark-400">Tidak ada data reaksi alergi</p>
                            <p class="text-xs text-zinc-400 dark:text-primary-dark-500">Coba ubah kata kunci pencarian atau periksa koneksi SIMRS</p>
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
                    Untuk Reaksi: <span class="font-semibold text-zinc-700 dark:text-primary-dark-200">{{ $selectedName }}</span>
                    <span class="font-mono text-xs text-zinc-400 ml-1">(ID: {{ $selectedId }})</span>
                </flux:text>
            </div>
            <livewire:components.snomed-search defaultTag="finding" :initialSearch="$snomedInitialSearch" :key="'snomed-reaksi-' . ($selectedId ?? '')" />
            
        <x-slot:footer>
            <div class="flex justify-end">
                <x-atoms.button wire:click="$set('showSnomedSearchModal', false)" variant="ghost">Tutup</x-atoms.button>
            </div>
        </x-slot:footer>
    </div>
    </x-organisms.modal>

    {{-- Modal Sync UUID BPJS --}}
    <x-organisms.modal wire:model="showSyncModal" title="Sync Semua UUID BPJS Reaksi Alergi" maxWidth="md">
        <div class="space-y-5">
            <div class="flex items-center gap-4">
                <div class="flex items-center justify-center w-12 h-12 rounded-full bg-primary-100 dark:bg-primary-900/30">
                    <flux:icon name="arrow-path" class="w-6 h-6 text-primary-600 dark:text-primary-400" />
                </div>
                <p class="text-sm text-zinc-500 dark:text-primary-dark-400">
                    {{ number_format($unsyncedBpjs) }} data reaksi alergi belum memiliki UUID BPJS
                </p>
            </div>
            <div class="p-3.5 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-[11px] text-amber-800 dark:text-amber-200 leading-relaxed italic">
                <flux:icon name="exclamation-triangle" class="inline w-3.5 h-3.5 mr-1 text-amber-500" />
                UUID baru akan di-generate untuk semua data yang belum terdaftar. Proses berjalan di background (queue worker).
            </div>
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-atoms.button variant="ghost" wire:click="$set('showSyncModal', false)">Batal</x-atoms.button>
                <x-atoms.button variant="primary" icon="arrow-path" wire:click="syncAllBpjs">Mulai Sync</x-atoms.button>
            </div>
        </x-slot>
    </x-organisms.modal>

    {{-- Modal Detail UUID BPJS --}}
    <x-organisms.modal wire:model="showBpjsDetailModal" title="Detail UUID BPJS Reaksi Alergi" maxWidth="md">
        @if ($selectedBpjsItem)
            <dl class="space-y-4">
                <div>
                    <dt class="text-[10px] uppercase font-bold text-zinc-400 dark:text-primary-dark-500 mb-1">Nama Reaksi</dt>
                    <dd class="text-sm font-semibold text-zinc-800 dark:text-white">{{ $selectedBpjsItem->name }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] uppercase font-bold text-zinc-400 dark:text-primary-dark-500 mb-1">Local Code (ID SIMRS)</dt>
                    <dd class="font-mono text-sm font-bold text-primary-600 dark:text-primary-400">{{ $selectedBpjsItem->local_code }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] uppercase font-bold text-zinc-400 dark:text-primary-dark-500 mb-1">Resource ID (UUID)</dt>
                    <dd class="font-mono text-sm font-bold text-emerald-600 dark:text-emerald-400 break-all">{{ $selectedBpjsItem->id }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] uppercase font-bold text-zinc-400 dark:text-primary-dark-500 mb-1">Dibuat</dt>
                    <dd class="text-sm text-zinc-600 dark:text-primary-dark-300">{{ $selectedBpjsItem->created_at?->format('d M Y, H:i') }}</dd>
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
                    <span class="text-xs font-medium text-zinc-400 w-10 shrink-0">ID</span>
                    <span class="font-mono text-sm font-bold text-primary-600 dark:text-primary-400">{{ $deleteSnomedId }}</span>
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
                    UUID BPJS untuk reaksi <strong class="text-zinc-800 dark:text-white">{{ $deleteBpjsName }}</strong> akan dihapus.
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
</div>
