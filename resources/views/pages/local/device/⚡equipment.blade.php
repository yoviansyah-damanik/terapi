<?php

use App\Jobs\SyncBpjsDevicesJob;
use App\Models\Bpjs\BpjsDevice;
use App\Models\Mapping\DeviceMap;
use App\Models\Mapping\DeviceActionMap;
use App\Models\Mapping\LabMap;
use App\Models\Mapping\RadMap;
use App\Models\Simrs\InventarisBarang;
use App\Models\Simrs\JnsPerawatanLab;
use App\Models\Simrs\JnsPerawatanRadiologi;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;

/**
 * Halaman mapping Alat Kesehatan → KFA.
 * Data diambil dari inventaris_barang (SIMRS), difilter berdasarkan
 * inventaris_jenis.id_kategori = $jenis_id (default: 'ALKES').
 */
new #[Layout('layouts::app')] #[Title('Alat Kesehatan — Mapping & UUID')] class extends Component {
    use WithPagination;

    /** Kategori inventaris yang ditampilkan — ganti untuk menyesuaikan jenis lain */
    public string $jenis_id = 'ALKES';

    public string $search = '';
    public int $perPage = 25;

    // BPJS UUID
    public bool $showSyncModal = false;
    public bool $showBpjsDetailModal = false;
    public ?BpjsDevice $selectedBpjsItem = null;
    public bool $showDeleteBpjsModal = false;
    public ?string $deleteBpjsCode = null;
    public string $deleteBpjsName = '';

    // State modal pemilihan KFA
    public bool $showModal = false;
    public ?string $selectedCode = null;
    public ?string $selectedName = null;

    // State modal relasi tindakan
    public bool $showActionModal = false;
    public string $actionSearch = '';
    public string $actionType = 'lab'; // lab | rad

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        // 1. Filter inventaris_barang langsung via id_kategori (cross-DB: SIMRS terpisah)
        $items = InventarisBarang::query()->where('id_jenis', $this->jenis_id)->when($this->search, fn($q) => $q->where('kode_barang', 'like', "%{$this->search}%")->orWhere('nama_barang', 'like', "%{$this->search}%"))->orderBy('nama_barang')->paginate($this->perPage);

        // 2. Load mapping dari DB lokal (terpisah — hindari cross-DB join)
        $codes = $items->pluck('kode_barang')->toArray();
        $mappings = DeviceMap::whereIn('local_code', $codes)->get()->keyBy('local_code');

        // 3. Load action counts per device (split by type)
        $labCounts = DeviceActionMap::whereIn('device_code', $codes)->where('action_type', 'lab')->selectRaw('device_code, COUNT(*) as total')->groupBy('device_code')->pluck('total', 'device_code');
        $radCounts = DeviceActionMap::whereIn('device_code', $codes)->where('action_type', 'rad')->selectRaw('device_code, COUNT(*) as total')->groupBy('device_code')->pluck('total', 'device_code');

        // 4. Gabungkan via collection
        $bpjsRegistered = BpjsDevice::pluck('id', 'local_code');
        $items->getCollection()->transform(function ($item) use ($mappings, $labCounts, $radCounts, $bpjsRegistered) {
            $map = $mappings->get($item->kode_barang);
            $item->kfa_code = $map?->kfa_code;
            $item->kfa_name = $map?->kfa_name;
            $item->system_url = $map?->system_url;
            $item->lab_count = $labCounts->get($item->kode_barang) ?? 0;
            $item->rad_count = $radCounts->get($item->kode_barang) ?? 0;
            $item->bpjs_uuid = $bpjsRegistered->get($item->kode_barang);
            return $item;
        });

        $totalSimrs = InventarisBarang::where('id_jenis', $this->jenis_id)->count();

        // 5. Load specific actions for modal if open
        $relatedActions = collect();
        $searchActions = collect();

        if ($this->showActionModal && $this->selectedCode) {
            $relatedActions = DeviceActionMap::where('device_code', $this->selectedCode)->where('action_type', $this->actionType)->get();

            $model = $this->actionType === 'rad' ? JnsPerawatanRadiologi::class : JnsPerawatanLab::class;
            $searchActions = $model::query()->when($this->actionSearch, fn($q) => $q->where('kd_jenis_prw', 'like', "%{$this->actionSearch}%")->orWhere('nm_perawatan', 'like', "%{$this->actionSearch}%"))->limit(10)->get();
        }

        $totalBpjs = BpjsDevice::count();
        $totalKfa  = DeviceMap::count();

        return [
            'items'          => $items,
            'relatedActions' => $relatedActions,
            'searchActions'  => $searchActions,
            'totalSimrs'     => $totalSimrs,
            'totalKfa'       => $totalKfa,
            'totalBpjs'      => $totalBpjs,
            'unsyncedBpjs'   => max(0, $totalSimrs - $totalBpjs),
        ];
    }

    // ── BPJS UUID actions ───────────────────────────────────────────────────

    public function generateBpjsUuid(string $code, string $name): void
    {
        if (BpjsDevice::where('local_code', $code)->exists()) {
            $this->toastWarning('Alkes ini sudah memiliki UUID BPJS.');
            return;
        }
        BpjsDevice::create(['local_code' => $code, 'name' => $name]);
        $this->toastSuccess("UUID BPJS berhasil di-generate untuk {$name}.");
    }

    public function syncAllBpjs(): void
    {
        SyncBpjsDevicesJob::dispatch();
        $this->showSyncModal = false;
        $this->toastSuccess('Sync semua UUID BPJS dijadwalkan. Proses berjalan di background.');
    }

    public function viewBpjsDetail(string $code): void
    {
        $this->selectedBpjsItem = BpjsDevice::where('local_code', $code)->first();
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
        BpjsDevice::where('local_code', $this->deleteBpjsCode)->delete();
        $this->showDeleteBpjsModal = false;
        $this->reset(['deleteBpjsCode', 'deleteBpjsName']);
        $this->toastSuccess('UUID BPJS berhasil dihapus.');
    }

    public function openModal(string $code, string $name): void
    {
        $this->selectedCode = $code;
        $this->selectedName = $name;
        $this->showModal = true;
    }

    #[On('kfa-selected')]
    public function kfaSelected(string $kfa_code, string $name, string $kfa_type, string $system_url): void
    {
        DeviceMap::updateOrCreate(['local_code' => $this->selectedCode], ['kfa_code' => $kfa_code, 'kfa_name' => $name, 'system_url' => $system_url]);

        $this->showModal = false;
        $this->toastSuccess('Mapping KFA Alkes berhasil disimpan', 'Sukses');
    }

    public function deleteMapping(string $code): void
    {
        DeviceMap::where('local_code', $code)->delete();
        $this->toastSuccess('Mapping berhasil dihapus', 'Sukses');
    }

    public function openActionModal(string $code, string $name, string $type): void
    {
        $this->selectedCode = $code;
        $this->selectedName = $name;
        $this->actionType = $type;
        $this->actionSearch = '';
        $this->showActionModal = true;
    }

    public function addAction(string $actionCode): void
    {
        // Validasi eksklusivitas: Cek apakah ada tindakan dari tipe LAIN
        $otherType = $this->actionType === 'lab' ? 'rad' : 'lab';
        $hasOtherType = DeviceActionMap::where('device_code', $this->selectedCode)->where('action_type', $otherType)->exists();

        if ($hasOtherType) {
            $msg = $this->actionType === 'lab' ? 'Radiologi' : 'Laboratorium';
            $this->toastError("Alat ini sudah terhubung dengan tindakan $msg. Tidak dapat mencampur tipe tindakan.", 'Gagal');
            return;
        }

        DeviceActionMap::updateOrCreate([
            'device_code' => $this->selectedCode,
            'action_code' => $actionCode,
            'action_type' => $this->actionType,
        ]);

        $this->toastSuccess('Tindakan berhasil dihubungkan', 'Sukses');
    }

    public function removeAction(string $id): void
    {
        DeviceActionMap::destroy($id);
        $this->toastSuccess('Hubungan tindakan dihapus', 'Sukses');
    }
};
?>

<div>
    <x-ui.page-header title="Alat Kesehatan — Mapping & UUID"
        subtitle="Hubungkan alat kesehatan lokal dengan kode KFA (Kamus Farmasi dan Alkes) Satu Sehat &amp; generate UUID BPJS">
        <x-slot:actions>
            <x-atoms.button variant="outline" icon="arrow-path" wire:click="$set('showSyncModal', true)">
                Sync UUID BPJS
            </x-atoms.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-3 gap-4 mb-6">
        <x-organisms.stat-card title="Total Alkes" :value="number_format($totalSimrs)" icon="wrench-screwdriver" color="zinc" />
        <x-organisms.stat-card title="KFA Ter-mapping" :value="number_format($totalKfa)" icon="link" color="emerald" :subtitle="'dari ' . number_format($totalSimrs) . ' total'" />
        <x-organisms.stat-card title="UUID BPJS" :value="number_format($totalBpjs)" icon="identification" color="blue" :subtitle="'dari ' . number_format($totalSimrs) . ' total'" />
    </div>

    <x-organisms.data-panel>
        <x-slot:filter>
            <div class="flex gap-3">
                <div class="flex-1 min-w-0">
                    <flux:input type="search" wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                        placeholder="Cari kode atau nama alat kesehatan..." />
                </div>
                <div class="shrink-0 w-44">
                    <flux:select wire:model.live="perPage">
                        <flux:select.option value="25">25 / halaman</flux:select.option>
                        <flux:select.option value="50">50 / halaman</flux:select.option>
                        <flux:select.option value="100">100 / halaman</flux:select.option>
                    </flux:select>
                </div>
            </div>
        </x-slot:filter>

        <x-organisms.table>
            <x-slot:headings>
                <x-atoms.table-heading>Kode Lokal</x-atoms.table-heading>
                <x-atoms.table-heading>Nama Alat Kesehatan</x-atoms.table-heading>
                <x-atoms.table-heading>Mapping KFA</x-atoms.table-heading>
                <x-atoms.table-heading align="center" class="w-28">UUID BPJS</x-atoms.table-heading>
                <x-atoms.table-heading align="center">Lab Terhubung</x-atoms.table-heading>
                <x-atoms.table-heading align="center">Rad Terhubung</x-atoms.table-heading>
                <x-atoms.table-heading align="center">Aksi</x-atoms.table-heading>
            </x-slot:headings>

            @forelse ($items as $item)
                <x-molecules.table-row wire:key="{{ $item->kode_barang }}">
                    <x-atoms.table-cell nowrap>
                        <span class="font-mono font-medium text-primary-600 dark:text-primary-400">
                            {{ $item->kode_barang }}
                        </span>
                    </x-atoms.table-cell>

                    <x-atoms.table-cell>{{ $item->nama_barang }}</x-atoms.table-cell>

                    <x-atoms.table-cell>
                        @if ($item->kfa_code)
                            <div class="flex flex-col gap-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-sm text-zinc-600 dark:text-primary-dark-400">
                                        {{ $item->kfa_code }}
                                    </span>
                                    @if ($item->system_url)
                                        <flux:badge size="sm"
                                            color="{{ str_contains($item->system_url, 'kfa-v3') ? 'blue' : 'green' }}">
                                            {{ str_contains($item->system_url, 'kfa-v3') ? 'Alkes' : 'Farmasi' }}
                                        </flux:badge>
                                    @endif
                                </div>
                                <span class="text-xs text-zinc-500 dark:text-primary-dark-400">
                                    {{ $item->kfa_name }}
                                </span>
                            </div>
                        @else
                            <span class="text-sm italic text-zinc-400">Belum di-mapping</span>
                        @endif
                    </x-atoms.table-cell>

                    <x-atoms.table-cell align="center">
                        @if ($item->bpjs_uuid)
                        <flux:badge color="blue" size="sm">Terdaftar</flux:badge>
                        @else
                        <flux:badge color="zinc" size="sm">Belum</flux:badge>
                        @endif
                    </x-atoms.table-cell>

                    <x-atoms.table-cell align="center" nowrap>
                        <x-atoms.button
                            wire:click="openActionModal('{{ $item->kode_barang }}', '{{ addslashes($item->nama_barang) }}', 'lab')"
                            variant="ghost" size="sm" :disabled="$item->rad_count > 0">
                            {{ $item->lab_count }} Tindakan
                        </x-atoms.button>
                    </x-atoms.table-cell>

                    <x-atoms.table-cell align="center" nowrap>
                        <x-atoms.button
                            wire:click="openActionModal('{{ $item->kode_barang }}', '{{ addslashes($item->nama_barang) }}', 'rad')"
                            variant="ghost" size="sm" :disabled="$item->lab_count > 0">
                            {{ $item->rad_count }} Tindakan
                        </x-atoms.button>
                    </x-atoms.table-cell>

                    <x-atoms.table-cell align="center" nowrap action>
                        <div class="flex items-center justify-center gap-0.5">
                            {{-- Grup KFA --}}
                            <div class="flex items-center gap-0.5 pr-2 border-r border-zinc-200 dark:border-primary-dark-600">
                                <x-atoms.button
                                    wire:click="openModal('{{ $item->kode_barang }}', '{{ addslashes($item->nama_barang) }}')"
                                    size="sm" icon="{{ $item->kfa_code ? 'pencil-square' : 'plus' }}" variant="ghost"
                                    tooltip="{{ $item->kfa_code ? 'Edit KFA' : 'Pilih KFA' }}" />
                                @if ($item->kfa_code)
                                    <x-atoms.button wire:click="deleteMapping('{{ $item->kode_barang }}')" size="sm"
                                        icon="trash" variant="ghost" tooltip="Hapus KFA" class="text-red-500" />
                                @endif
                            </div>
                            {{-- Grup BPJS UUID --}}
                            <div class="flex items-center gap-0.5 pl-2">
                                @if ($item->bpjs_uuid)
                                <x-atoms.button
                                    wire:click="viewBpjsDetail('{{ $item->kode_barang }}')"
                                    size="sm" icon="eye" variant="ghost" tooltip="Detail UUID BPJS" />
                                <x-atoms.button
                                    wire:click="confirmDeleteBpjs('{{ $item->kode_barang }}', '{{ addslashes($item->nama_barang) }}')"
                                    size="sm" icon="trash" variant="ghost" tooltip="Hapus UUID BPJS" class="text-red-500" />
                                @else
                                <x-atoms.button
                                    wire:click="generateBpjsUuid('{{ $item->kode_barang }}', '{{ addslashes($item->nama_barang) }}')"
                                    size="sm" icon="sparkles" variant="ghost" tooltip="Generate UUID BPJS" />
                                @endif
                            </div>
                        </div>
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @empty
                <tr>
                    <td colspan="7">
                        <x-ui.empty-state icon="wrench-screwdriver" title="Tidak ada alat kesehatan"
                            description="Tidak ada data dengan jenis {{ $jenis_id }}." />
                    </td>
                </tr>
            @endforelse
        </x-organisms.table>

        @if ($items->hasPages())
            <x-slot:footer>
                @php
                    $lastPage = $items->lastPage();
                    $current = $items->currentPage();
                    if ($lastPage <= 7) {
                        $pageNumbers = range(1, $lastPage);
                    } elseif ($current <= 4) {
                        $pageNumbers = [...range(1, 5), null, $lastPage];
                    } elseif ($current >= $lastPage - 3) {
                        $pageNumbers = [1, null, ...range($lastPage - 4, $lastPage)];
                    } else {
                        $pageNumbers = [1, null, $current - 1, $current, $current + 1, null, $lastPage];
                    }
                @endphp
                <x-molecules.pagination :page="$items->currentPage()" :total-page="$items->lastPage()" :total="$items->total()" :page-numbers="$pageNumbers"
                    on-prev="previousPage" on-next="nextPage" on-goto="gotoPage" />
            </x-slot:footer>
        @endif
    </x-organisms.data-panel>

    {{-- Modal Pencarian KFA --}}
    <x-organisms.modal wire:model="showModal" title="Pilih Kode KFA Alkes" maxWidth="3xl"
        description="Untuk: {{ $selectedName }} ({{ $selectedCode }})">
        <livewire:components.kfa-search defaultType="alkes" :key="'kfa-alkes-' . ($selectedCode ?? '')" />

        <x-slot:footer>
            <x-atoms.button wire:click="$set('showModal', false)" variant="ghost">Tutup</x-atoms.button>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal Sync UUID BPJS --}}
    <x-organisms.modal wire:model="showSyncModal" title="Sync Semua UUID BPJS Alkes" maxWidth="md">
        <div class="space-y-4">
            <p class="text-sm text-zinc-600 dark:text-primary-dark-300">
                Proses ini akan men-generate UUID BPJS untuk semua alat kesehatan yang belum terdaftar.
                Saat ini terdapat <strong>{{ number_format($unsyncedBpjs) }}</strong> alkes belum memiliki UUID BPJS.
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
    <x-organisms.modal wire:model="showBpjsDetailModal" title="Detail UUID BPJS Alkes" maxWidth="md">
        @if ($selectedBpjsItem)
        <dl class="space-y-3 text-sm">
            <div class="flex justify-between gap-4">
                <dt class="text-zinc-500 dark:text-primary-dark-400 shrink-0">Nama Alkes</dt>
                <dd class="font-medium text-zinc-800 dark:text-primary-dark-100 text-right">{{ $selectedBpjsItem->name }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-zinc-500 dark:text-primary-dark-400 shrink-0">Kode Lokal</dt>
                <dd class="font-mono text-xs text-zinc-700 dark:text-primary-dark-200">{{ $selectedBpjsItem->local_code }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-zinc-500 dark:text-primary-dark-400 shrink-0">UUID BPJS</dt>
                <dd class="font-mono text-xs break-all text-blue-700 dark:text-blue-400">{{ $selectedBpjsItem->id }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-zinc-500 dark:text-primary-dark-400 shrink-0">Dibuat</dt>
                <dd class="text-zinc-600 dark:text-primary-dark-300">{{ $selectedBpjsItem->created_at?->format('d M Y H:i') }}</dd>
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
            UUID BPJS untuk alkes <strong>{{ $deleteBpjsName }}</strong> akan dihapus secara permanen.
            Data ini tidak dapat dipulihkan.
        </p>
        <x-slot:footer>
            <x-atoms.button variant="ghost" wire:click="$set('showDeleteBpjsModal', false)">Batal</x-atoms.button>
            <x-atoms.button variant="danger" wire:click="deleteBpjs" icon="trash">Hapus UUID</x-atoms.button>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal Relasi Tindakan --}}
    <x-organisms.modal wire:model="showActionModal" maxWidth="4xl"
        title="Hubungkan Tindakan {{ $actionType === 'lab' ? 'Laboratorium' : 'Radiologi' }}"
        description="Alat: {{ $selectedName }} ({{ $selectedCode }})">

        <div class="flex gap-4">
            {{-- Kiri: Pilih tindakan --}}
            <div class="w-1/2 space-y-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-primary-dark-400">
                    Pilih Tindakan
                </p>

                <flux:input wire:model.live.debounce.300ms="actionSearch" icon="magnifying-glass"
                    placeholder="Cari tindakan..." />

                <div
                    class="overflow-y-auto max-h-[420px] space-y-2 p-1 rounded-lg bg-zinc-50/30 dark:bg-primary-dark-900/10">
                    @php
                        $linkedCodes = collect($relatedActions)->pluck('action_code')->toArray();
                    @endphp
                    @forelse ($searchActions as $action)
                        <div wire:click="addAction('{{ $action->kd_jenis_prw }}')"
                            class="cursor-pointer p-3 flex items-center justify-between bg-white dark:bg-primary-dark-800 rounded-lg shadow-sm hover:shadow-md transition-all duration-200 group">
                            <div class="flex flex-col">
                                <span
                                    class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded
                                    bg-primary-50 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400
                                    w-fit mb-1 leading-tight">
                                    {{ $action->kd_jenis_prw }}
                                </span>
                                <span
                                    class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-200
                                    group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                                    {{ $action->nm_perawatan }}
                                </span>
                            </div>
                            @if (in_array($action->kd_jenis_prw, $linkedCodes))
                                <flux:icon name="check-circle" variant="solid" size="xs" class="text-green-700" />
                            @endif
                        </div>
                    @empty
                        <x-ui.empty-state icon="magnifying-glass" title="Cari tindakan di atas"
                            description="Ketik nama atau kode tindakan untuk memulai." class="py-8" />
                    @endforelse
                </div>
            </div>

            {{-- Kanan: Tindakan terhubung --}}
            <div class="w-1/2 space-y-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-primary-dark-400">
                    Tindakan Terhubung
                    <flux:badge size="sm" color="zinc" class="ml-1">{{ count($relatedActions) }}</flux:badge>
                </p>

                <div
                    class="overflow-y-auto max-h-[460px] rounded-lg divide-y divide-zinc-100
                    dark:divide-primary-dark-700 bg-zinc-50/50 dark:bg-primary-dark-900/30">
                    @forelse ($relatedActions as $rel)
                        <div class="p-3 flex items-center justify-between">
                            <div class="flex flex-col min-w-0">
                                <span class="text-xs font-mono text-zinc-500">{{ $rel->action_code }}</span>
                                <span class="text-sm font-medium text-zinc-800 dark:text-primary-dark-200 truncate">
                                    @php
                                        $name =
                                            $actionType === 'lab'
                                                ? \App\Models\Simrs\JnsPerawatanLab::where(
                                                    'kd_jenis_prw',
                                                    $rel->action_code,
                                                )->value('nm_perawatan')
                                                : \App\Models\Simrs\JnsPerawatanRadiologi::where(
                                                    'kd_jenis_prw',
                                                    $rel->action_code,
                                                )->value('nm_perawatan');
                                    @endphp
                                    {{ $name ?? 'Unknown action' }}
                                </span>
                            </div>
                            <x-atoms.button wire:click="removeAction('{{ $rel->id }}')" icon="trash"
                                size="sm" variant="ghost"
                                class="text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20" />
                        </div>
                    @empty
                        <x-ui.empty-state icon="link" title="Belum ada tindakan"
                            description="Pilih tindakan dari panel sebelah kiri." class="py-10" />
                    @endforelse
                </div>
            </div>
        </div>

        <x-slot:footer>
            <x-atoms.button wire:click="$set('showActionModal', false)" variant="ghost">Tutup</x-atoms.button>
        </x-slot:footer>
    </x-organisms.modal>
</div>
