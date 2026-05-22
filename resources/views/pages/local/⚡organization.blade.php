<?php

use App\Jobs\SyncBpjsOrganizationsJob;
use App\Models\Bpjs\BpjsOrganization;
use App\Models\Mapping\OrganizationMap;
use App\Models\Simrs\Departemen;
use App\Models\SatuSehat\SatuSehatOrganization;
use App\Services\SatuSehat\Resources\OrganizationService;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('Organisasi')] class extends Component {
    use WithPagination;

    private array $presetIds = ['RS', 'LAB', 'IGD', 'FAR', 'RAD', 'RI', 'RJ'];

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterBpjs = '';

    #[Url]
    public string $filterSs = '';

    public int $perPage = 25;

    // Modal tambah preset departemen
    public bool $showAddPresetModal = false;
    public array $missingPresetsToAdd = [];

    // Modal sync BPJS
    public bool $showSyncModal = false;

    // Modal hapus UUID BPJS departemen
    public bool $showBpjsDeleteModal = false;
    public ?string $deleteBpjsDep = null;
    public string $deleteBpjsDepName = '';

    // Modal detail SS Organization
    public bool $showDetailModal = false;
    public ?SatuSehatOrganization $selectedOrg = null;

    // Modal konfirmasi hapus SS mapping
    public bool $showDeleteSsModal = false;
    public ?string $deleteSsDeptId = null;
    public string $deleteSsDeptName = '';

    // Modal mapping HL7 organization-type
    public bool $showOrgTypeModal = false;
    public ?string $orgTypeDepId = null;
    public string $orgTypeDepName = '';

    // Modal tarik SS Organization
    public bool $showPullModal = false;
    public ?string $pullDeptId = null;
    public string $pullDeptName = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }
    public function updatedFilterBpjs(): void
    {
        $this->resetPage();
    }
    public function updatedFilterSs(): void
    {
        $this->resetPage();
    }

    // --- HL7 Organization Type Mapping ---

    public function openOrgTypeModal(string $depId, string $depName): void
    {
        $this->orgTypeDepId = $depId;
        $this->orgTypeDepName = $depName;
        $this->showOrgTypeModal = true;
    }

    #[On('fhir-codesystem-selected')]
    public function fhirSelected(array $item): void
    {
        if (!$this->orgTypeDepId) {
            return;
        }

        OrganizationMap::updateOrCreate(
            ['dep_id' => $this->orgTypeDepId],
            [
                'org_type_code' => $item['system_code'],
                'org_type_term' => $item['system_term'],
                'org_type_display' => $item['system_display'] ?? null,
            ],
        );

        $this->showOrgTypeModal = false;
        $this->reset(['orgTypeDepId', 'orgTypeDepName']);
        $this->toastSuccess('Tipe organisasi berhasil disimpan.');
    }

    public function deleteOrgType(string $depId): void
    {
        OrganizationMap::where('dep_id', $depId)->delete();
        $this->toastSuccess('Mapping tipe organisasi dihapus.');
    }

    // --- BPJS UUID Departemen ---

    public function generateBpjsUuid(string $depId, string $depName): void
    {
        if (BpjsOrganization::where('identifier', $depId)->exists()) {
            $this->toastWarning('Departemen ini sudah memiliki UUID BPJS.');
            return;
        }

        BpjsOrganization::create(['identifier' => $depId, 'name' => $depName]);
        $this->toastSuccess("UUID BPJS berhasil di-generate untuk: {$depName}");
    }

    public function confirmBpjsDelete(string $depId, string $depName): void
    {
        $this->deleteBpjsDep = $depId;
        $this->deleteBpjsDepName = $depName;
        $this->showBpjsDeleteModal = true;
    }

    public function deleteBpjsUuid(): void
    {
        if (!$this->deleteBpjsDep) {
            return;
        }

        BpjsOrganization::where('identifier', $this->deleteBpjsDep)->delete();
        $this->showBpjsDeleteModal = false;
        $this->reset(['deleteBpjsDep', 'deleteBpjsDepName']);
        $this->toastSuccess('UUID BPJS berhasil dihapus.');
    }

    /** Nama default untuk setiap preset identifier */
    private function presetNames(): array
    {
        return [
            'RS' => config('hospital.name', 'Rumah Sakit'),
            'LAB' => 'Laboratorium',
            'IGD' => 'Instalasi Gawat Darurat',
            'FAR' => 'Farmasi',
            'RAD' => 'Radiologi',
            'RI' => 'Rawat Inap',
            'RJ' => 'Rawat Jalan',
        ];
    }

    /** Cek preset yang belum ada lalu buka modal konfirmasi */
    public function openAddPresetModal(): void
    {
        try {
            $existing = Departemen::whereIn('dep_id', $this->presetIds)->pluck('dep_id')->map(fn($v) => strtoupper((string) $v))->toArray();

            $missing = array_values(array_diff($this->presetIds, $existing));

            if (empty($missing)) {
                $this->toastWarning('Semua preset sudah tersedia di tabel departemen.');
                return;
            }

            $this->missingPresetsToAdd = $missing;
            $this->showAddPresetModal = true;
        } catch (\Exception $e) {
            $this->toastError('Gagal mengakses SIMRS: ' . $e->getMessage());
        }
    }

    /** Eksekusi penambahan preset setelah konfirmasi modal */
    public function confirmAddPresets(): void
    {
        $names = $this->presetNames();

        try {
            foreach ($this->missingPresetsToAdd as $id) {
                Departemen::create(['dep_id' => $id, 'nama' => $names[$id] ?? $id]);
            }

            $count = count($this->missingPresetsToAdd);
            $this->showAddPresetModal = false;
            $this->missingPresetsToAdd = [];
            $this->toastSuccess("{$count} preset berhasil ditambahkan ke departemen.");
        } catch (\Exception $e) {
            $this->showAddPresetModal = false;
            $this->toastError('Gagal mengakses SIMRS: ' . $e->getMessage());
        }
    }

    public function syncAllBpjs(): void
    {
        SyncBpjsOrganizationsJob::dispatch();
        $this->showSyncModal = false;
        $this->toastSuccess('Sync BPJS Organization telah dijadwalkan di queue.');
    }

    // --- Departemen: SS Organization ---

    public function viewDetail(string $identifier): void
    {
        $this->selectedOrg = SatuSehatOrganization::where('identifier', $identifier)->first();
        $this->showDetailModal = true;
    }

    public function sendToSatuSehat(string $depId, string $depNama): void
    {
        $existing = SatuSehatOrganization::where('identifier', $depId)->first();
        if ($existing?->ihs_number) {
            $this->toastWarning("Departemen ini sudah memiliki IHS Number: {$existing->ihs_number}.");
            return;
        }

        $orgMap = OrganizationMap::where('dep_id', $depId)->first();

        try {
            $response = app(OrganizationService::class)->createOrganization($depNama, $depId, $orgMap?->org_type_code ?? 'dept', $orgMap?->org_type_term ?? 'Hospital Department');
        } catch (\Exception $e) {
            $this->toastError('Gagal menghubungi Satu Sehat: ' . $e->getMessage());
            return;
        }

        if (!$response->success) {
            $this->toastError('Satu Sehat menolak permintaan: ' . ($response->error ?? 'Kesalahan tidak diketahui.'));
            return;
        }

        SatuSehatOrganization::updateOrCreate(
            ['identifier' => $depId],
            [
                'ihs_number' => $response->resourceId,
                'name' => $response->data['name'] ?? $depNama,
                'status' => 'active',
                'part_of' => config('satusehat.organization_id') ?: null,
                'raw_response' => $response->data,
                'synced_at' => now(),
            ],
        );

        $this->toastSuccess("Organization berhasil dikirim ke Satu Sehat. IHS: {$response->resourceId}");
    }

    public function updateOrganization(): void
    {
        if (!$this->selectedOrg?->ihs_number) {
            return;
        }

        try {
            $response = app(OrganizationService::class)->updateOrganization($this->selectedOrg->ihs_number, $this->selectedOrg->name, $this->selectedOrg->identifier, true);
        } catch (\Exception $e) {
            $this->toastError('Gagal menghubungi Satu Sehat: ' . $e->getMessage());
            return;
        }

        if (!$response->success) {
            $this->toastError('Satu Sehat menolak permintaan: ' . ($response->error ?? 'Kesalahan tidak diketahui.'));
            return;
        }

        $this->selectedOrg->update([
            'status' => 'active',
            'raw_response' => $response->data,
            'synced_at' => now(),
        ]);

        $this->selectedOrg = $this->selectedOrg->fresh();
        $this->toastSuccess("Organization berhasil diperbarui. IHS: {$this->selectedOrg->ihs_number}");
    }

    public function confirmDeleteSs(string $depId, string $depNama): void
    {
        $this->deleteSsDeptId = $depId;
        $this->deleteSsDeptName = $depNama;
        $this->showDeleteSsModal = true;
    }

    public function deleteSsMapping(): void
    {
        if (!$this->deleteSsDeptId) {
            return;
        }

        $org = SatuSehatOrganization::where('identifier', $this->deleteSsDeptId)->first();

        if ($org) {
            if ($org->ihs_number) {
                try {
                    app(OrganizationService::class)->updateOrganization($org->ihs_number, $org->name, $org->identifier, false);
                } catch (\Exception $e) {
                    $this->toastError('Gagal menonaktifkan di Satu Sehat: ' . $e->getMessage());
                    $this->showDeleteSsModal = false;
                    $this->reset(['deleteSsDeptId', 'deleteSsDeptName']);
                    return;
                }
            }

            $org->delete();
        }

        $this->showDeleteSsModal = false;
        $this->reset(['deleteSsDeptId', 'deleteSsDeptName']);
        $this->toastSuccess('Mapping SS Organization dihapus.');
    }

    public function openPullModal(string $depId, string $depNama): void
    {
        $this->pullDeptId = $depId;
        $this->pullDeptName = $depNama;
        $this->showPullModal = true;
    }

    #[On('satusehat-resource-selected')]
    public function onResourceSelected(array $resource): void
    {
        if (!$this->pullDeptId) {
            return;
        }

        $ihsNumber = $resource['id'] ?? null;
        if (!$ihsNumber) {
            return;
        }

        $existingByIhs = SatuSehatOrganization::where('ihs_number', $ihsNumber)->first();
        $existingByDep = SatuSehatOrganization::where('identifier', $this->pullDeptId)->first();

        if ($existingByIhs && ($existingByDep === null || $existingByIhs->id !== $existingByDep->id)) {
            $label = $existingByIhs->identifier ? "departemen '{$existingByIhs->identifier}'" : 'entri lain';
            $this->toastError("IHS Number {$ihsNumber} sudah terdaftar untuk {$label}.");
            return;
        }

        $partOfRef = $resource['partOf']['reference'] ?? null;
        $partOf = $partOfRef ? last(explode('/', $partOfRef)) : null;

        SatuSehatOrganization::updateOrCreate(
            ['identifier' => $this->pullDeptId],
            [
                'ihs_number' => $ihsNumber,
                'name' => $resource['name'] ?? $this->pullDeptName,
                'status' => $resource['active'] ?? true ? 'active' : 'inactive',
                'part_of' => $partOf,
                'raw_response' => $resource,
                'synced_at' => now(),
            ],
        );

        $this->showPullModal = false;
        $this->reset(['pullDeptId', 'pullDeptName']);
        $this->toastSuccess("Organization berhasil ditarik. IHS: {$ihsNumber}");
    }

    public function with(): array
    {
        // Cek preset wajib di bpjs_organizations dan satu_sehat_organizations

        $existingBpjsPresets = BpjsOrganization::whereIn('identifier', $this->presetIds)->pluck('identifier')->map(fn($v) => strtoupper((string) $v))->toArray();
        $existingSsPresets = SatuSehatOrganization::whereIn('identifier', $this->presetIds)->whereNotNull('ihs_number')->pluck('identifier')->map(fn($v) => strtoupper((string) $v))->toArray();
        $missingBpjsPresets = array_values(array_diff($this->presetIds, $existingBpjsPresets));
        $missingSsPresets = array_values(array_diff($this->presetIds, $existingSsPresets));

        $allBpjs = BpjsOrganization::pluck('id', 'identifier');
        $orgTypeMap = OrganizationMap::pluck('org_type_code', 'dep_id');
        $ssMap = SatuSehatOrganization::whereNotNull('identifier')->pluck('ihs_number', 'identifier');

        $simrsError = false;
        $totalSimrs = 0;
        $missingDeptPresets = [];
        $items = new LengthAwarePaginator([], 0, $this->perPage);

        try {
            $existingDeptPresets = Departemen::whereIn('dep_id', $this->presetIds)->pluck('dep_id')->map(fn($v) => strtoupper((string) $v))->toArray();
            $missingDeptPresets = array_values(array_diff($this->presetIds, $existingDeptPresets));

            $totalSimrs = Departemen::count();
            $query = Departemen::query();

            if ($this->search) {
                $query->where(fn($q) => $q->where('dep_id', 'like', "%{$this->search}%")->orWhere('nama', 'like', "%{$this->search}%"));
            }

            if ($this->filterBpjs === 'registered') {
                $query->whereIn('dep_id', $allBpjs->keys());
            } elseif ($this->filterBpjs === 'unregistered') {
                $query->whereNotIn('dep_id', $allBpjs->keys());
            }

            if ($this->filterSs === 'mapped') {
                $query->whereIn('dep_id', $ssMap->filter()->keys());
            } elseif ($this->filterSs === 'unmapped') {
                $query->whereNotIn('dep_id', $ssMap->filter()->keys());
            }

            $items = $query->orderBy('nama')->paginate($this->perPage);
        } catch (\Exception) {
            $simrsError = true;
        }

        return [
            'items' => $items,
            'allBpjs' => $allBpjs,
            'ssMap' => $ssMap,
            'totalSimrs' => $totalSimrs,
            'totalBpjs' => $allBpjs->count(),
            'totalSs' => $ssMap->filter()->count(),
            'orgTypeMap' => $orgTypeMap,
            'missingDeptPresets' => $missingDeptPresets,
            'missingBpjsPresets' => $missingBpjsPresets,
            'missingSsPresets' => $missingSsPresets,
            'simrsError' => $simrsError,
        ];
    }
};

?>

<div>
    <x-ui.page-header title="Organisasi" subtitle="UUID BPJS dan IHS Satu Sehat untuk departemen SIMRS.">
        <x-slot:actions>
            <x-atoms.button wire:click="$set('showSyncModal', true)" icon="arrow-path" variant="outline" size="sm">
                Sync BPJS
            </x-atoms.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Notifikasi preset belum ditambahkan ke departemen --}}
    @if (!empty($missingDeptPresets))
        <div
            class="mb-4 flex items-start gap-3 p-4 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
            <flux:icon name="exclamation-triangle" class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" />
            <div class="flex-1 text-sm text-amber-800 dark:text-amber-200">
                <p class="font-semibold">Preset departemen belum lengkap</p>
                <p class="mt-0.5">
                    Kode preset berikut belum terdaftar di tabel departemen:
                    <span class="font-mono font-bold">{{ implode(', ', $missingDeptPresets) }}</span>.
                </p>
            </div>
            <x-atoms.button wire:click="openAddPresetModal" icon="plus" size="sm" variant="primary"
                color="yellow"
                class="shrink-0 border-amber-300 dark:border-amber-700 text-amber-800 dark:text-amber-200 hover:bg-amber-100 dark:hover:bg-amber-900/30">
                Tambah Preset
            </x-atoms.button>
        </div>
    @endif

    {{-- Notifikasi preset wajib --}}
    @if (!empty($missingBpjsPresets) || !empty($missingSsPresets))
        <div
            class="mb-4 flex items-start gap-3 p-4 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
            <flux:icon name="exclamation-triangle" class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" />
            <div class="text-sm text-amber-800 dark:text-amber-200 space-y-1.5">
                <p class="font-semibold">Preset Organization belum lengkap (wajib tersedia)</p>
                @if (!empty($missingBpjsPresets))
                    <p>
                        UUID BPJS belum di-generate:
                        <span
                            class="font-mono font-bold">{{ implode(', ', array_map('strtoupper', $missingBpjsPresets)) }}</span>.
                    </p>
                @endif
                @if (!empty($missingSsPresets))
                    <p>
                        IHS Satu Sehat belum dipetakan:
                        <span
                            class="font-mono font-bold">{{ implode(', ', array_map('strtoupper', $missingSsPresets)) }}</span>.
                    </p>
                @endif
            </div>
        </div>
    @endif

    {{-- SIMRS error --}}
    @if ($simrsError)
        <div
            class="mb-4 flex items-center gap-3 p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-sm text-red-700 dark:text-red-300">
            <flux:icon name="exclamation-triangle" class="w-5 h-5 shrink-0" />
            Koneksi ke database SIMRS gagal. Data departemen tidak dapat ditampilkan.
        </div>
    @endif

    <div class="grid grid-cols-3 gap-4 mb-6">
        <x-organisms.stat-card title="Total Departemen" :value="number_format($totalSimrs)" icon="building-library" color="zinc" />
        <x-organisms.stat-card title="UUID BPJS" :value="number_format($totalBpjs)" icon="identification" color="blue" />
        <x-organisms.stat-card title="IHS Satu Sehat" :value="number_format($totalSs)" icon="cube" color="emerald" />
    </div>

    <x-organisms.data-panel>
        <x-slot:filter>
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-[200px]">
                    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                        placeholder="Cari kode atau nama departemen..." clearable />
                </div>
                <flux:select wire:model.live="filterBpjs" class="sm:w-44">
                    <flux:select.option value="">Semua Status BPJS</flux:select.option>
                    <flux:select.option value="registered">Terdaftar BPJS</flux:select.option>
                    <flux:select.option value="unregistered">Belum Terdaftar</flux:select.option>
                </flux:select>
                <flux:select wire:model.live="filterSs" class="sm:w-44">
                    <flux:select.option value="">Semua Status SS</flux:select.option>
                    <flux:select.option value="mapped">IHS Terpetakan</flux:select.option>
                    <flux:select.option value="unmapped">Belum Terpetakan</flux:select.option>
                </flux:select>
                <flux:select wire:model.live="perPage" class="w-40 shrink-0">
                    <flux:select.option value="25">25 / halaman</flux:select.option>
                    <flux:select.option value="50">50 / halaman</flux:select.option>
                    <flux:select.option value="100">100 / halaman</flux:select.option>
                </flux:select>
            </div>
        </x-slot:filter>

        <x-organisms.table>
            <x-slot:headings>
                <x-atoms.table-heading class="w-32">Kode</x-atoms.table-heading>
                <x-atoms.table-heading>Nama Departemen</x-atoms.table-heading>
                <x-atoms.table-heading class="w-36">Tipe Organisasi</x-atoms.table-heading>
                <x-atoms.table-heading class="w-52">UUID BPJS</x-atoms.table-heading>
                <x-atoms.table-heading class="w-44">IHS Satu Sehat</x-atoms.table-heading>
                <x-atoms.table-heading align="center" class="w-40">Aksi</x-atoms.table-heading>
            </x-slot:headings>

            @forelse ($items as $item)
                @php
                    $bpjsUuid = $allBpjs[$item->dep_id] ?? null;
                    $ssIhs = $ssMap[$item->dep_id] ?? null;
                    $orgTypeCode = $orgTypeMap[$item->dep_id] ?? null;
                @endphp
                <x-molecules.table-row wire:key="dept-{{ $item->dep_id }}">
                    <x-atoms.table-cell nowrap>
                        <span
                            class="inline-block font-mono text-xs font-bold px-2 py-1 rounded-md bg-zinc-100 dark:bg-primary-dark-700 text-zinc-600 dark:text-primary-dark-300 ring-1 ring-zinc-200 dark:ring-primary-dark-600">
                            {{ $item->dep_id }}
                        </span>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        <p class="text-sm font-medium text-zinc-800 dark:text-primary-dark-100">{{ $item->nama }}</p>
                    </x-atoms.table-cell>

                    {{-- Tipe Organisasi --}}
                    <x-atoms.table-cell>
                        @if ($orgTypeCode)
                            <span
                                class="inline-block font-mono text-xs font-bold px-2 py-1 rounded-md bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-400 ring-1 ring-violet-200 dark:ring-violet-800">
                                {{ $orgTypeCode }}
                            </span>
                        @else
                            <span class="text-xs italic text-zinc-400 dark:text-primary-dark-500">Belum dipetakan</span>
                        @endif
                    </x-atoms.table-cell>

                    {{-- UUID BPJS --}}
                    <x-atoms.table-cell>
                        @if ($bpjsUuid)
                            <span
                                class="font-mono text-xs text-blue-700 dark:text-blue-400 break-all">{{ $bpjsUuid }}</span>
                        @else
                            <span class="text-xs italic text-zinc-400 dark:text-primary-dark-500">Belum terdaftar</span>
                        @endif
                    </x-atoms.table-cell>

                    {{-- IHS Satu Sehat --}}
                    <x-atoms.table-cell>
                        @if ($ssIhs)
                            <span
                                class="font-mono text-xs font-semibold text-emerald-700 dark:text-emerald-400">{{ $ssIhs }}</span>
                        @else
                            <span class="text-xs italic text-zinc-400 dark:text-primary-dark-500">Belum terdaftar</span>
                        @endif
                    </x-atoms.table-cell>

                    {{-- Aksi --}}
                    <x-atoms.table-cell :action="true" align="center" nowrap>
                        <div class="flex items-center justify-center gap-1">
                            {{-- BPJS group --}}
                            @if ($bpjsUuid)
                                <x-atoms.button size="sm" variant="ghost" icon="trash" class="text-red-500"
                                    tooltip="Hapus UUID BPJS"
                                    wire:click="confirmBpjsDelete('{{ $item->dep_id }}', '{{ addslashes($item->nama) }}')" />
                            @else
                                <x-atoms.button size="sm" variant="ghost" icon="plus"
                                    tooltip="Generate UUID BPJS"
                                    wire:click="generateBpjsUuid('{{ $item->dep_id }}', '{{ addslashes($item->nama) }}')" />
                            @endif

                            <span class="w-px h-4 bg-zinc-200 dark:bg-primary-dark-600 mx-0.5"></span>

                            {{-- Org type group --}}
                            @if ($orgTypeCode)
                                <x-atoms.button size="sm" variant="ghost" icon="tag" class="text-violet-500"
                                    tooltip="Ubah Tipe Org"
                                    wire:click="openOrgTypeModal('{{ $item->dep_id }}', '{{ addslashes($item->nama) }}')" />
                                <x-atoms.button size="sm" variant="ghost" icon="x-mark" class="text-zinc-400"
                                    tooltip="Hapus Tipe Org" wire:click="deleteOrgType('{{ $item->dep_id }}')" />
                            @else
                                <x-atoms.button size="sm" variant="ghost" icon="tag"
                                    tooltip="Set Tipe Organisasi"
                                    wire:click="openOrgTypeModal('{{ $item->dep_id }}', '{{ addslashes($item->nama) }}')" />
                            @endif

                            <span class="w-px h-4 bg-zinc-200 dark:bg-primary-dark-600 mx-0.5"></span>

                            {{-- SS group --}}
                            @if ($ssIhs)
                                <x-atoms.button size="sm" variant="ghost" icon="eye" tooltip="Detail SS"
                                    wire:click="viewDetail('{{ $item->dep_id }}')" />
                                <x-atoms.button size="sm" variant="ghost" icon="trash" class="text-red-500"
                                    tooltip="Hapus SS"
                                    wire:click="confirmDeleteSs('{{ $item->dep_id }}', '{{ addslashes($item->nama) }}')" />
                            @else
                                <x-atoms.button size="sm" variant="ghost" icon="paper-airplane"
                                    tooltip="Kirim ke SS"
                                    wire:click="sendToSatuSehat('{{ $item->dep_id }}', '{{ addslashes($item->nama) }}')" />
                                <x-atoms.button size="sm" variant="ghost" icon="arrow-down-tray"
                                    tooltip="Tarik dari SS"
                                    wire:click="openPullModal('{{ $item->dep_id }}', '{{ addslashes($item->nama) }}')" />
                            @endif
                        </div>
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @empty
                <x-molecules.table-row>
                    <x-atoms.table-cell colspan="6" align="center" class="py-16">
                        <div class="flex flex-col items-center gap-3">
                            <div
                                class="flex h-14 w-14 items-center justify-center rounded-2xl bg-zinc-100 dark:bg-primary-dark-700">
                                <flux:icon name="building-library"
                                    class="h-7 w-7 text-zinc-300 dark:text-primary-dark-500" />
                            </div>
                            <p class="text-sm font-semibold text-zinc-500 dark:text-primary-dark-400">Tidak ada
                                departemen ditemukan</p>
                        </div>
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @endforelse
        </x-organisms.table>

        @if ($items->hasPages())
            <x-slot:footer>{{ $items->links() }}</x-slot:footer>
        @endif
    </x-organisms.data-panel>

    {{-- Modal: Mapping Tipe Organisasi HL7 --}}
    <x-organisms.modal wire:model="showOrgTypeModal" title="Pilih Organization Type" maxWidth="3xl">
        <div class="space-y-4">
            <div>
                @if ($orgTypeDepId)
                    <flux:text class="mt-0.5">
                        Departemen: <span class="font-semibold">{{ $orgTypeDepName }}</span>
                        <span class="font-mono text-xs text-zinc-400">({{ $orgTypeDepId }})</span>
                    </flux:text>
                @endif
            </div>

            <livewire:components.fhir-codesystem-search defaultType="organization-type" :limitTypes="['organization-type']" />
        </div>
        <x-slot name="footer">
            <div class="flex justify-end">
                <x-atoms.button variant="ghost" wire:click="$set('showOrgTypeModal', false)">Batal</x-atoms.button>
            </div>
        </x-slot>
    </x-organisms.modal>

    {{-- Modal: Tambah Preset Departemen --}}
    <x-organisms.modal wire:model="showAddPresetModal" maxWidth="sm" title="Tambah Preset Departemen">
        <div class="space-y-5">
            <div>
                
                <flux:text class="mt-0.5">Preset berikut belum tersedia di tabel departemen SIMRS.</flux:text>
            </div>
            <ul
                class="divide-y divide-zinc-100 dark:divide-primary-dark-700 rounded-xl border border-zinc-200 dark:border-primary-dark-700 overflow-hidden">
                @foreach ($missingPresetsToAdd as $preset)
                    @php $names = ['RS' => config('hospital.name', 'Rumah Sakit'), 'LAB' => 'Laboratorium', 'IGD' => 'Instalasi Gawat Darurat', 'FAR' => 'Farmasi', 'RAD' => 'Radiologi', 'RI' => 'Rawat Inap', 'RJ' => 'Rawat Jalan']; @endphp
                    <li class="flex items-center gap-3 px-4 py-2.5">
                        <span
                            class="font-mono text-xs font-bold text-zinc-500 dark:text-primary-dark-400 w-10 shrink-0">{{ $preset }}</span>
                        <span
                            class="text-sm text-zinc-700 dark:text-primary-dark-200">{{ $names[$preset] ?? $preset }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        <x-slot:footer>
            <div class="flex justify-end gap-3">
                <x-atoms.button variant="ghost" wire:click="$set('showAddPresetModal', false)">Batal</x-atoms.button>
                <x-atoms.button variant="primary" icon="plus" wire:click="confirmAddPresets">Proses</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal: Sync BPJS --}}
    <x-organisms.modal wire:model="showSyncModal" maxWidth="sm" title="Sync UUID BPJS Departemen">
        <div class="space-y-5">
            <div>
                
                <flux:text class="mt-0.5">Proses berjalan langsung (tanpa queue).</flux:text>
            </div>
            <div class="flex items-center gap-4">
                <div
                    class="flex items-center justify-center w-12 h-12 rounded-full bg-blue-100 dark:bg-blue-900/30 shrink-0">
                    <flux:icon name="arrow-path" class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                </div>
                <p class="text-sm text-zinc-500 dark:text-primary-dark-400">
                    UUID baru akan di-generate untuk semua departemen SIMRS yang belum terdaftar.
                </p>
            </div>
        </div>

        <x-slot:footer>
            <div class="flex justify-end gap-3">
                <x-atoms.button variant="ghost" wire:click="$set('showSyncModal', false)">Batal</x-atoms.button>
                <x-atoms.button variant="primary" icon="arrow-path" wire:click="syncAllBpjs">Sync
                    Sekarang</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal: Hapus UUID BPJS --}}
    <x-organisms.modal wire:model="showBpjsDeleteModal" maxWidth="sm" title="Hapus UUID BPJS">
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
                class="p-4 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/40 border border-zinc-200 dark:border-primary-dark-700 space-y-2">
                <p class="text-xs font-medium text-zinc-500">Kode: <span
                        class="font-mono font-bold text-zinc-700 dark:text-primary-dark-200">{{ $deleteBpjsDep }}</span>
                </p>
                <p class="text-xs font-medium text-zinc-500">Nama: <span
                        class="text-zinc-700 dark:text-primary-dark-200">{{ $deleteBpjsDepName }}</span></p>
            </div>
        </div>

        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <x-atoms.button wire:click="$set('showBpjsDeleteModal', false)" variant="ghost">Batal</x-atoms.button>
                <x-atoms.button wire:click="deleteBpjsUuid" variant="danger" icon="trash">Hapus
                    UUID</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal: Detail SS Organization --}}
    <x-organisms.modal wire:model="showDetailModal" maxWidth="md" title="{{ $selectedOrg?->name ?? '' }}">
        @if ($selectedOrg)
            <div class="space-y-4">
                
                <div
                    class="p-4 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/40 border border-zinc-200 dark:border-primary-dark-700 space-y-3">
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-medium text-zinc-400 w-20 shrink-0">Identifier</span>
                        <span
                            class="font-mono text-sm text-zinc-700 dark:text-primary-dark-200">{{ $selectedOrg->identifier }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-medium text-zinc-400 w-20 shrink-0">IHS Number</span>
                        <span
                            class="font-mono text-sm font-bold text-emerald-700 dark:text-emerald-400">{{ $selectedOrg->ihs_number }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-medium text-zinc-400 w-20 shrink-0">Status</span>
                        <span
                            class="text-xs px-2 py-0.5 rounded-full {{ $selectedOrg->status === 'active' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-zinc-100 text-zinc-600' }}">
                            {{ $selectedOrg->status ?? '-' }}
                        </span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-medium text-zinc-400 w-20 shrink-0">Synced At</span>
                        <span
                            class="text-sm text-zinc-600 dark:text-primary-dark-300">{{ $selectedOrg->synced_at?->format('d/m/Y H:i') ?? '-' }}</span>
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <x-atoms.button wire:click="$set('showDetailModal', false)" variant="ghost">Tutup</x-atoms.button>
                    <x-atoms.button wire:click="updateOrganization" icon="arrow-path">Update SS</x-atoms.button>
                </div>
            </div>
        @endif
    
    </x-organisms.modal>

    {{-- Modal: Hapus SS Mapping --}}
    <x-organisms.modal wire:model="showDeleteSsModal" maxWidth="sm" title="Hapus Mapping SS Organization">
        <div class="space-y-6">
            <div class="flex items-center gap-4">
                <div
                    class="flex items-center justify-center w-12 h-12 rounded-2xl bg-red-100 dark:bg-red-900/30 shrink-0">
                    <flux:icon name="exclamation-triangle" class="w-6 h-6 text-red-600 dark:text-red-400" />
                </div>
                <div>
                    
                    <flux:text class="mt-0.5">Organization akan dinonaktifkan di Satu Sehat.</flux:text>
                </div>
            </div>
            <div
                class="p-4 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/40 border border-zinc-200 dark:border-primary-dark-700">
                <p class="text-sm text-zinc-700 dark:text-primary-dark-200">{{ $deleteSsDeptName }}</p>
            </div>
        </div>

        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <x-atoms.button wire:click="$set('showDeleteSsModal', false)" variant="ghost">Batal</x-atoms.button>
                <x-atoms.button wire:click="deleteSsMapping" variant="danger" icon="trash">Hapus</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal: Tarik SS Organization --}}
    <x-organisms.modal wire:model="showPullModal" maxWidth="xl" title="">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Tarik Organization dari Satu Sehat</flux:heading>
                @if ($pullDeptId)
                    <flux:text class="mt-0.5">
                        Departemen: <span class="font-semibold">{{ $pullDeptName }}</span>
                        <span class="font-mono text-xs text-zinc-400">({{ $pullDeptId }})</span>
                    </flux:text>
                @endif
            </div>

            <livewire:components.satusehat-resource-search :serviceClass="\App\Services\SatuSehat\Resources\OrganizationService::class" resourceLabel="Organization"
                :initialSearch="$pullDeptName" :key="'org-pull-' . $pullDeptId" />
        </div>

        <x-slot:footer>
            <div class="flex justify-end pt-1">
                <x-atoms.button wire:click="$set('showPullModal', false)" variant="ghost">Batal</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>
</div>
