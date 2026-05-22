<?php

use App\Jobs\SyncBpjsProceduresJob;
use App\Models\Bpjs\BpjsProcedure;
use App\Models\Mapping\ProcedureMap;
use App\Models\Mapping\SurgeryNoteMap;
use App\Models\Simrs\PaketOperasi;
use App\Services\SatuSehat\FhirDictionary;
use App\Traits\WithSmartMapping;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('Operasi — Mapping & UUID')] class extends Component {
    use WithPagination, WithSmartMapping;

    #[Url]
    public string $search = '';

    public int $perPage = 25;

    // State modal pencarian SNOMED/LOINC
    public bool $showLoincSearchModal = false;
    public string $selectedMappingType = 'snomed'; // 'snomed' or 'loinc'
    public ?string $selectedPackageCode = null;
    public ?string $selectedPackageName = null;

    // State konfirmasi hapus mapping
    public bool $showDeleteModal = false;
    public ?string $deleteCode = null;
    public ?string $deleteName = null;

    // BPJS UUID
    public bool $showSyncModal = false;
    public bool $showDeleteBpjsModal = false;
    public ?string $deleteBpjsCode = null;
    public string $deleteBpjsName = '';

    public string $snomedInitialSearch = '';
    public string $loincInitialSearch = '';
    public string $snomedSemanticTag = 'procedure';

    protected function saveSmartMapping(string $sourceCode, string $code, string $term): bool
    {
        if ($this->selectedMappingType === 'snomed') {
            ProcedureMap::updateOrCreate(
                ['source_table' => 'operasi', 'procedure_code' => $sourceCode],
                [
                    'system_code' => $code,
                    'system_term' => $term,
                    'system_display' => FhirDictionary::SNOMED,
                ],
            );
        } else {
            SurgeryNoteMap::updateOrCreate(
                ['procedure_code' => $sourceCode],
                [
                    'loinc_code' => $code,
                    'loinc_term' => $term,
                ],
            );
        }
        return true;
    }

    protected function getUnmappedItemsForPage(int $page, bool $forAiQueue = false)
    {
        $mappedCodes = ProcedureMap::where('source_table', 'operasi')->pluck('procedure_code')->toArray();

        $items = PaketOperasi::query()
            ->whereNotIn('kode_paket', $mappedCodes)
            ->orderBy('kode_paket')
            ->paginate(25, ['*'], 'page', $page);

        if ($forAiQueue) {
            return $items
                ->getCollection()
                ->map(
                    fn($item) => [
                        'code' => $item->kode_paket,
                        'name' => $item->nm_perawatan,
                    ],
                )
                ->toArray();
        }

        return $items;
    }

    public function with(): array
    {
        $items = PaketOperasi::query()
            ->active()
            ->when($this->search, function ($q) {
                $q->where('kode_paket', 'like', "%{$this->search}%")->orWhere('nm_perawatan', 'like', "%{$this->search}%");
            })
            ->orderBy('nm_perawatan')
            ->paginate($this->perPage);

        $codes = $items->getCollection()->pluck('kode_paket')->toArray();
        $snomedMappings = ProcedureMap::where('source_table', 'operasi')->whereIn('procedure_code', $codes)->get()->keyBy('procedure_code');
        $loincMappings = SurgeryNoteMap::whereIn('procedure_code', $codes)->get()->keyBy('procedure_code');
        $bpjsRegistered = BpjsProcedure::where('type', 'operasi')->whereIn('local_code', $codes)->pluck('id', 'local_code');

        $items->getCollection()->transform(function ($item) use ($snomedMappings, $loincMappings, $bpjsRegistered) {
            $sMap = $snomedMappings->get($item->kode_paket);
            $lMap = $loincMappings->get($item->kode_paket);

            $item->snomed_code = $sMap->system_code ?? null;
            $item->snomed_term = $sMap->system_term ?? null;

            $item->loinc_code = $lMap->loinc_code ?? null;
            $item->loinc_term = $lMap->loinc_term ?? null;

            $item->bpjs_uuid = $bpjsRegistered->get($item->kode_paket);

            return $item;
        });

        $totalSimrs = PaketOperasi::count();
        $totalSnomedMapped = ProcedureMap::where('source_table', 'operasi')->count();
        $totalLoincMapped = SurgeryNoteMap::count();
        $totalBpjs = BpjsProcedure::where('type', 'operasi')->count();

        return [
            'items' => $items,
            'totalSimrs' => $totalSimrs,
            'totalSnomedMapped' => $totalSnomedMapped,
            'totalLoincMapped' => $totalLoincMapped,
            'totalBpjs' => $totalBpjs,
        ];
    }

    // ── BPJS UUID actions ───────────────────────────────────────────────────

    public function generateBpjsUuid(string $code, string $name): void
    {
        if (BpjsProcedure::where('type', 'operasi')->where('local_code', $code)->exists()) {
            $this->toastWarning('Kode ini sudah memiliki UUID BPJS.');
            return;
        }
        BpjsProcedure::create(['type' => 'operasi', 'local_code' => $code, 'name' => $name]);
        $this->toastSuccess("UUID BPJS berhasil di-generate untuk {$code}.");
    }

    public function syncAllBpjs(): void
    {
        SyncBpjsProceduresJob::dispatch('operasi');
        $this->showSyncModal = false;
        $this->toastSuccess('Sync semua UUID BPJS Operasi dijadwalkan. Proses berjalan di background.');
    }

    public function confirmDeleteBpjs(string $code, string $name): void
    {
        $this->deleteBpjsCode = $code;
        $this->deleteBpjsName = $name;
        $this->showDeleteBpjsModal = true;
    }

    public function deleteBpjs(): void
    {
        BpjsProcedure::where('type', 'operasi')->where('local_code', $this->deleteBpjsCode)->delete();
        $this->showDeleteBpjsModal = false;
        $this->reset(['deleteBpjsCode', 'deleteBpjsName']);
        $this->toastSuccess('UUID BPJS berhasil dihapus.');
    }

    public function openModal(string $code, string $name, string $type = 'snomed'): void
    {
        $this->selectedPackageCode = $code;
        $this->selectedPackageName = $name;
        $this->selectedMappingType = $type;

        if ($type === 'snomed') {
            $this->snomedInitialSearch = ProcedureMap::where('source_table', 'operasi')->where('procedure_code', $code)->value('system_term') ?? $name;
        } else {
            $this->loincInitialSearch = SurgeryNoteMap::where('procedure_code', $code)->value('loinc_term') ?? $name;
        }

        $this->showLoincSearchModal = true;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /** Di-trigger oleh event dari komponen pencarian SNOMED */
    public function selectSnomed(string $code, string $term): void
    {
        $this->saveSmartMapping($this->selectedPackageCode, $code, $term);
        $this->showLoincSearchModal = false;
        $this->toastSuccess('Mapping SNOMED CT berhasil disimpan');
    }

    /** Di-trigger oleh event dari komponen pencarian LOINC */
    public function selectLoinc(string $code, string $term): void
    {
        $this->saveSmartMapping($this->selectedPackageCode, $code, $term);
        $this->showLoincSearchModal = false;
        $this->toastSuccess('Mapping LOINC berhasil disimpan');
    }

    public function confirmDelete(string $code, string $name): void
    {
        $this->deleteCode = $code;
        $this->deleteName = $name;
        $this->showDeleteModal = true;
    }

    public function deleteMapping(): void
    {
        if ($this->selectedMappingType === 'snomed') {
            ProcedureMap::where('source_table', 'operasi')->where('procedure_code', $this->deleteCode)->delete();
        } else {
            SurgeryNoteMap::where('procedure_code', $this->deleteCode)->delete();
        }

        $this->showDeleteModal = false;
        $this->toastSuccess('Mapping berhasil dihapus');
        $this->reset(['deleteCode', 'deleteName']);
    }
}; ?>

<div>
    <x-ui.page-header title="Operasi — Mapping & UUID"
        subtitle="Kelola mapping SNOMED/LOINC dan UUID BPJS untuk paket operasi">
        <x-slot:actions>
            <x-atoms.button wire:click="$set('showSyncModal', true)" variant="outline" icon="arrow-path">
                Sync UUID BPJS
            </x-atoms.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-4 gap-4 mb-6">
        <x-organisms.stat-card title="Total Paket" :value="number_format($totalSimrs)" icon="scissors" color="zinc" />
        <x-organisms.stat-card title="SNOMED (Prosedur)" :value="number_format($totalSnomedMapped)" icon="sparkles" color="violet"
            :subtitle="'dari ' . number_format($totalSimrs) . ' total'" />
        <x-organisms.stat-card title="LOINC (Laporan)" :value="number_format($totalLoincMapped)" icon="document-text" color="blue"
            :subtitle="'dari ' . number_format($totalSimrs) . ' total'" />
        <x-organisms.stat-card title="UUID BPJS" :value="number_format($totalBpjs)" icon="identification" color="emerald"
            :subtitle="'dari ' . number_format($totalSimrs) . ' total'" />
    </div>

    <x-organisms.data-panel>
        <x-slot:filter>
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-[200px]">
                    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                        placeholder="Cari kode atau nama paket operasi..." clearable />
                </div>
                <flux:select wire:model.live="perPage" class="w-40 shrink-0">
                    <flux:select.option value="25">25 / halaman</flux:select.option>
                    <flux:select.option value="50">50 / halaman</flux:select.option>
                    <flux:select.option value="100">100 / halaman</flux:select.option>
                </flux:select>
            </div>
        </x-slot:filter>

        <x-organisms.table>
            <x-slot:headings>
                <x-atoms.table-heading class="w-36">Kode Lokal</x-atoms.table-heading>
                <x-atoms.table-heading>Nama Paket Operasi</x-atoms.table-heading>
                <x-atoms.table-heading>
                    <span class="inline-flex items-center gap-1">
                        <span class="w-2 h-2 rounded-full bg-violet-400"></span>
                        SNOMED (Prosedur)
                    </span>
                </x-atoms.table-heading>
                <x-atoms.table-heading>
                    <span class="inline-flex items-center gap-1">
                        <span class="w-2 h-2 rounded-full bg-blue-400"></span>
                        LOINC (Laporan)
                    </span>
                </x-atoms.table-heading>
                <x-atoms.table-heading>
                    <span class="inline-flex items-center gap-1">
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                        UUID BPJS
                    </span>
                </x-atoms.table-heading>
                <x-atoms.table-heading align="center" class="w-40">Aksi</x-atoms.table-heading>
            </x-slot:headings>

            @forelse ($items as $item)
                <x-molecules.table-row wire:key="op-{{ $item->kode_paket }}">
                    <x-atoms.table-cell>
                        <span
                            class="inline-block font-mono text-xs font-bold px-2 py-1 rounded-md bg-zinc-50 dark:bg-primary-dark-700/50 text-zinc-700 dark:text-primary-dark-300 ring-1 ring-zinc-200 dark:ring-primary-dark-600/50">
                            {{ $item->kode_paket }}
                        </span>
                    </x-atoms.table-cell>

                    <x-atoms.table-cell>
                        <p class="text-sm font-medium text-zinc-800 dark:text-primary-dark-100 leading-snug">
                            {{ $item->nm_perawatan }}
                        </p>
                    </x-atoms.table-cell>

                    <x-atoms.table-cell>
                        <div class="flex items-center justify-between gap-3 group/snomed">
                            @if ($item->snomed_code)
                                <div class="flex items-start gap-2.5 min-w-0">
                                    <span
                                        class="mt-1 w-2 h-2 rounded-full bg-violet-400 shrink-0 ring-2 ring-violet-100"></span>
                                    <div class="min-w-0">
                                        <p class="font-mono text-[10px] font-bold text-violet-700">
                                            {{ $item->snomed_code }}</p>
                                        <p class="text-[10px] text-zinc-500 line-clamp-1"
                                            title="{{ $item->snomed_term }}">{{ $item->snomed_term }}</p>
                                    </div>
                                </div>
                            @else
                                <div class="flex items-center gap-2 text-zinc-400">
                                    <span class="w-2 h-2 rounded-full bg-zinc-200"></span>
                                    <span class="text-xs italic">Belum di-mapping</span>
                                </div>
                            @endif
                            <x-atoms.button
                                wire:click="openModal('{{ $item->kode_paket }}', '{{ addslashes($item->nm_perawatan) }}', 'snomed')"
                                size="sm" icon="{{ $item->snomed_code ? 'pencil-square' : 'plus' }}"
                                variant="ghost" class="opacity-0 group-hover/snomed:opacity-100 transition-opacity" />
                        </div>
                    </x-atoms.table-cell>

                    <x-atoms.table-cell>
                        <div class="flex items-center justify-between gap-3 group/loinc">
                            @if ($item->loinc_code)
                                <div class="flex items-start gap-2.5 min-w-0">
                                    <span
                                        class="mt-1 w-2 h-2 rounded-full bg-blue-400 shrink-0 ring-2 ring-blue-100"></span>
                                    <div class="min-w-0">
                                        <p class="font-mono text-[10px] font-bold text-blue-700">{{ $item->loinc_code }}
                                        </p>
                                        <p class="text-[10px] text-zinc-500 line-clamp-1"
                                            title="{{ $item->loinc_term }}">{{ $item->loinc_term }}</p>
                                    </div>
                                </div>
                            @else
                                <div class="flex items-center gap-2 text-zinc-400">
                                    <span class="w-2 h-2 rounded-full bg-zinc-200"></span>
                                    <span class="text-xs italic">Belum di-mapping</span>
                                </div>
                            @endif
                            <x-atoms.button
                                wire:click="openModal('{{ $item->kode_paket }}', '{{ addslashes($item->nm_perawatan) }}', 'loinc')"
                                size="sm" icon="{{ $item->loinc_code ? 'pencil-square' : 'plus' }}"
                                variant="ghost" class="opacity-0 group-hover/loinc:opacity-100 transition-opacity" />
                        </div>
                    </x-atoms.table-cell>

                    <x-atoms.table-cell>
                        @if ($item->bpjs_uuid)
                            <span
                                class="font-mono text-xs font-bold text-emerald-700 dark:text-emerald-400">{{ $item->bpjs_uuid }}</span>
                        @else
                            <span class="text-xs italic text-zinc-400">Belum terdaftar</span>
                        @endif
                    </x-atoms.table-cell>

                    <x-atoms.table-cell :action="true" align="center">
                        <div class="flex items-center justify-center gap-1">
                            {{-- Mapping Group --}}
                            <div
                                class="flex items-center gap-0.5 border-r border-zinc-200 dark:border-primary-dark-600 pr-2 mr-1">
                                @if ($item->snomed_code || $item->loinc_code)
                                    <flux:dropdown position="bottom right">
                                        <x-atoms.button variant="ghost" icon="trash" size="sm"
                                            class="text-red-500" tooltip="Hapus mapping" />
                                        <flux:navmenu>
                                            @if ($item->snomed_code)
                                                <flux:navmenu.item
                                                    wire:click="confirmDelete('{{ $item->kode_paket }}', '{{ addslashes($item->nm_perawatan) }}', 'snomed')"
                                                    class="text-red-500 hover:bg-red-50">Hapus SNOMED
                                                </flux:navmenu.item>
                                            @endif
                                            @if ($item->loinc_code)
                                                <flux:navmenu.item
                                                    wire:click="confirmDelete('{{ $item->kode_paket }}', '{{ addslashes($item->nm_perawatan) }}', 'loinc')"
                                                    class="text-red-500 hover:bg-red-50">Hapus LOINC</flux:navmenu.item>
                                            @endif
                                        </flux:navmenu>
                                    </flux:dropdown>
                                @endif
                            </div>

                            {{-- BPJS Group --}}
                            <div class="flex items-center gap-0.5">
                                @if (!$item->bpjs_uuid)
                                    <x-atoms.button
                                        wire:click="generateBpjsUuid('{{ $item->kode_paket }}', '{{ addslashes($item->nm_perawatan) }}')"
                                        size="sm" variant="ghost" icon="plus-circle"
                                        class="text-emerald-600 hover:bg-emerald-50" tooltip="Generate UUID BPJS" />
                                @else
                                    <x-atoms.button
                                        wire:click="confirmDeleteBpjs('{{ $item->kode_paket }}', '{{ addslashes($item->nm_perawatan) }}')"
                                        size="sm" variant="ghost" icon="trash"
                                        class="text-red-400 hover:text-red-600 hover:bg-red-50"
                                        tooltip="Hapus UUID BPJS" />
                                @endif
                            </div>
                        </div>
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @empty
                <tr>
                    <td colspan="6" class="px-5 py-16 text-center">
                        <div class="flex flex-col items-center gap-3">
                            <flux:icon name="scissors" class="w-10 h-10 text-zinc-200" />
                            <p class="text-sm font-semibold text-zinc-500">Tidak ada data paket operasi</p>
                        </div>
                    </td>
                </tr>
            @endforelse
        </x-organisms.table>

        <x-slot:footer>
            {{ $items->links() }}
        </x-slot:footer>
    </x-organisms.data-panel>

    <x-organisms.modal wire:model="showLoincSearchModal" maxWidth="4xl" :title="$selectedMappingType === 'snomed' ? 'Pilih Kode SNOMED CT (Tindakan)' : 'Pilih Kode LOINC (Laporan)'">
        <div class="space-y-4">
            <flux:text>
                Mapping <strong>{{ strtoupper($selectedMappingType) }}</strong> untuk: <span
                    class="font-semibold text-zinc-800 dark:text-white">{{ $selectedPackageName }}</span>
            </flux:text>

            @if ($selectedMappingType === 'snomed')
                <livewire:components.snomed-search :initialSearch="$snomedInitialSearch" :semanticTag="$snomedSemanticTag"
                    @snomed-selected="selectSnomed($event.detail.snomed.conceptId, $event.detail.snomed.pt.term)" />
            @else
                <livewire:components.loinc-search :initialSearch="$loincInitialSearch"
                    @loinc-selected="selectLoinc($event.detail.loinc.loinc_num, $event.detail.loinc.long_common_name)" />
            @endif

            <x-slot:footer>
                <x-atoms.button wire:click="$set('showLoincSearchModal', false)"
                    variant="ghost">Tutup</x-atoms.button>
            </x-slot:footer>
        </div>
    </x-organisms.modal>

    {{-- Modal Konfirmasi Hapus Mapping --}}
    <x-organisms.modal wire:model="showDeleteModal" maxWidth="sm" title="Hapus Mapping">
        <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-4">
            Hapus mapping <strong>{{ strtoupper($selectedMappingType) }}</strong> untuk
            <strong>{{ $deleteName }}</strong>?
        </p>
        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <x-atoms.button wire:click="$set('showDeleteModal', false)" variant="ghost">Batal</x-atoms.button>
                <x-atoms.button wire:click="deleteMapping" variant="danger">Hapus</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal Sync BPJS --}}
    <x-organisms.modal wire:model="showSyncModal" maxWidth="sm" title="Sync UUID BPJS">
        <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-4">
            Generate UUID BPJS untuk semua paket operasi yang belum terdaftar?
        </p>
        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <x-atoms.button wire:click="$set('showSyncModal', false)" variant="ghost">Batal</x-atoms.button>
                <x-atoms.button wire:click="syncAllBpjs" variant="primary">Proses</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal Hapus BPJS --}}
    <x-organisms.modal wire:model="showDeleteBpjsModal" maxWidth="sm" title="Hapus UUID BPJS">
        <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-4">
            Hapus UUID BPJS untuk paket <strong>{{ $deleteBpjsName }}</strong>?
        </p>
        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <x-atoms.button wire:click="$set('showDeleteBpjsModal', false)" variant="ghost">Batal</x-atoms.button>
                <x-atoms.button wire:click="deleteBpjs" variant="danger">Hapus</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>
</div>
