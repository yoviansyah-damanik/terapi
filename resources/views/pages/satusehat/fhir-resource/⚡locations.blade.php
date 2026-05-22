<?php

use App\Models\Simrs\Poliklinik;
use App\Models\Simrs\Bangsal;
use App\Models\SatuSehat\SatuSehatLocation;
use App\Models\SatuSehat\SatuSehatOrganization;
use App\Services\SatuSehat\Resources\LocationService;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('Satu Sehat - Location')] class extends Component {
    use WithPagination;

    // Tab aktif: 'poliklinik' | 'ruangan'
    #[Url]
    public string $tab = 'poliklinik';

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterStatus = '';

    // Modal detail
    public bool $showDetailModal = false;
    public ?SatuSehatLocation $selectedLocation = null;

    // Modal konfirmasi hapus
    public bool $showDeleteModal = false;
    public ?string $deleteLocalCode = null;
    public string $deleteLocalName = '';
    public string $deleteTabContext = '';

    // Modal input posisi (untuk kirim dan update)
    public bool $showPositionModal = false;
    public string $positionAction = 'kirim'; // 'kirim' | 'update'
    public ?string $positionLocalCode = null;
    public string $positionLocalName = '';
    public string $positionTabContext = '';
    public string $positionLongitude = '';
    public string $positionLatitude = '';
    public string $positionAltitude = '';
    public string $positionManagingOrg = '';
    public string $positionType = 'ralan'; // ralan|ranap|apotek|lab|rad

    // Modal tarik dari Satu Sehat
    public bool $showPullModal = false;
    public ?string $pullLocalCode = null;
    public string $pullLocalName = '';
    public string $pullTabContext = '';
    public string $pullSearch = '';
    public array $pullResults = [];
    public ?string $pullError = null;

    /** Cari record SatuSehatLocation berdasarkan kode lokal dan tab (poliklinik = ralan, ruangan = non-ralan) */
    private function findLocation(string $localCode, string $tab): ?SatuSehatLocation
    {
        $query = SatuSehatLocation::where('identifier', $localCode);
        return ($tab === 'poliklinik' ? $query->where('type', 'ralan') : $query->where('type', '!=', 'ralan'))->first();
    }

    public function switchTab(string $tab): void
    {
        $this->tab = $tab;
        $this->search = '';
        $this->filterStatus = '';
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    /** Buka modal posisi untuk kirim location baru ke Satu Sehat */
    public function openSendModal(string $localCode, string $localNama, string $tabContext): void
    {
        $this->resetErrorBag();
        $this->positionAction = 'kirim';
        $this->positionLocalCode = $localCode;
        $this->positionLocalName = $localNama;
        $this->positionTabContext = $tabContext;
        $this->positionLongitude = '';
        $this->positionLatitude = '';
        $this->positionAltitude = '';
        $this->positionManagingOrg = '';
        $this->positionType = $tabContext === 'poliklinik' ? 'ralan' : 'ranap';
        $this->showPositionModal = true;
        $this->dispatch('position-modal-opened');
    }

    /** Buka modal posisi untuk update location yang sudah terpetakan, pre-fill dari raw_response */
    public function openUpdateModal(): void
    {
        if (!$this->selectedLocation) {
            return;
        }

        $pos = $this->selectedLocation->getPosition();
        $this->resetErrorBag();
        $this->positionAction = 'update';
        $this->positionLongitude = (string) ($pos['longitude'] ?? '');
        $this->positionLatitude = (string) ($pos['latitude'] ?? '');
        $this->positionAltitude = (string) ($pos['altitude'] ?? '');
        $this->positionManagingOrg = $this->selectedLocation->managing_organization ?? '';
        $this->positionType = $this->selectedLocation->type ?? 'ralan';
        $this->positionLocalName = $this->selectedLocation->name;

        $this->positionTabContext = $this->selectedLocation->type === 'ralan' ? 'poliklinik' : 'ruangan';
        $this->positionLocalCode = $this->selectedLocation->identifier ?? '';

        $this->showDetailModal = false;
        $this->showPositionModal = true;
        $this->dispatch('position-modal-opened');
    }

    /** Validasi dan arahkan ke processSend atau processUpdate */
    public function savePosition(): void
    {
        $this->validate(
            [
                'positionLongitude' => 'required|numeric',
                'positionLatitude' => 'required|numeric',
                'positionAltitude' => 'required|numeric',
                'positionManagingOrg' => 'required|string',
                'positionType' => 'required|in:ralan,ranap,apotek,lab,rad',
            ],
            [
                'positionLongitude.required' => 'Longitude wajib diisi.',
                'positionLongitude.numeric' => 'Longitude harus berupa angka.',
                'positionLatitude.required' => 'Latitude wajib diisi.',
                'positionLatitude.numeric' => 'Latitude harus berupa angka.',
                'positionAltitude.required' => 'Altitude wajib diisi.',
                'positionAltitude.numeric' => 'Altitude harus berupa angka.',
                'positionManagingOrg.required' => 'Managing Organization wajib dipilih.',
                'positionType.required' => 'Tipe lokasi wajib dipilih.',
                'positionType.in' => 'Tipe lokasi tidak valid.',
            ],
        );

        if ($this->positionAction === 'kirim') {
            $this->processSend();
        } else {
            $this->processUpdate();
        }
    }

    /** Kirim location baru ke Satu Sehat via API */
    private function processSend(): void
    {
        $existing = $this->findLocation($this->positionLocalCode, $this->positionTabContext);
        if ($existing && $existing->ihs_number) {
            $this->toastWarning("Lokasi ini sudah memiliki IHS Number: {$existing->ihs_number}.");
            return;
        }

        try {
            $response = app(LocationService::class)->createLocation($this->positionLocalName, $this->positionLocalCode, $this->positionLongitude, $this->positionLatitude, $this->positionAltitude, $this->positionManagingOrg);
        } catch (\Exception $e) {
            $this->toastError('Gagal menghubungi Satu Sehat: ' . $e->getMessage());
            return;
        }

        if (!$response->success) {
            $this->toastError('Satu Sehat menolak permintaan: ' . ($response->error ?? 'Kesalahan tidak diketahui.'));
            return;
        }

        SatuSehatLocation::updateOrCreate(
            ['identifier' => $this->positionLocalCode, 'type' => $this->positionType],
            [
                'ihs_number' => $response->resourceId,
                'name' => $response->data['name'] ?? $this->positionLocalName,
                'status' => 'active',
                'managing_organization' => $this->positionManagingOrg,
                'raw_response' => $response->data,
                'synced_at' => now(),
            ],
        );

        $this->showPositionModal = false;
        $this->reset(['positionLocalCode', 'positionLocalName', 'positionTabContext', 'positionLongitude', 'positionLatitude', 'positionAltitude', 'positionManagingOrg', 'positionType']);
        $this->toastSuccess("Location berhasil dikirim ke Satu Sehat. IHS: {$response->resourceId}");
    }

    /** Update location yang sudah ada ke Satu Sehat via API */
    private function processUpdate(): void
    {
        $loc = $this->findLocation($this->positionLocalCode, $this->positionTabContext);

        if (!$loc || !$loc->ihs_number) {
            $this->toastError('Data location tidak ditemukan atau belum memiliki IHS Number.');
            return;
        }

        try {
            $response = app(LocationService::class)->updateLocation($loc->ihs_number, $this->positionLocalName, $this->positionLocalCode, $this->positionLongitude, $this->positionLatitude, $this->positionAltitude, $loc->status ?? 'active', $this->positionManagingOrg);
        } catch (\Exception $e) {
            $this->toastError('Gagal menghubungi Satu Sehat: ' . $e->getMessage());
            return;
        }

        if (!$response->success) {
            $this->toastError('Satu Sehat menolak permintaan: ' . ($response->error ?? 'Kesalahan tidak diketahui.'));
            return;
        }

        $loc->update([
            'name' => $response->data['name'] ?? $this->positionLocalName,
            'type' => $this->positionType,
            'status' => $response->data['status'] ?? $loc->status,
            'managing_organization' => $this->positionManagingOrg,
            'raw_response' => $response->data,
            'synced_at' => now(),
        ]);

        $this->showPositionModal = false;
        $this->reset(['positionLocalCode', 'positionLocalName', 'positionTabContext', 'positionLongitude', 'positionLatitude', 'positionAltitude', 'positionManagingOrg', 'positionType']);

        // Buka ulang detail modal dengan data terbaru
        $this->selectedLocation = $loc->fresh()->load('organization');
        $this->showDetailModal = true;
        $this->dispatch('detail-modal-opened');

        $this->toastSuccess('Location berhasil diperbarui di Satu Sehat.');
    }

    /** Buka modal detail */
    public function viewDetail(string $localCode, string $tabContext): void
    {
        $query = SatuSehatLocation::where('identifier', $localCode)->with('organization');
        $this->selectedLocation = ($tabContext === 'poliklinik' ? $query->where('type', 'ralan') : $query->where('type', '!=', 'ralan'))->first();
        $this->showDetailModal = true;
        $this->dispatch('detail-modal-opened');
    }

    /** Buka modal pencarian location dari Satu Sehat */
    public function openPullModal(string $localCode, string $localNama, string $tabContext): void
    {
        $this->pullLocalCode = $localCode;
        $this->pullLocalName = $localNama;
        $this->pullTabContext = $tabContext;
        $this->pullSearch = $localNama;
        $this->pullResults = [];
        $this->pullError = null;
        $this->showPullModal = true;
    }

    /** Cari location berdasarkan nama di Satu Sehat */
    public function searchLocation(): void
    {
        if (blank($this->pullSearch)) {
            return;
        }

        $this->pullResults = [];
        $this->pullError = null;

        try {
            $response = app(LocationService::class)->searchByName(trim($this->pullSearch));

            if (!$response->success) {
                $this->pullError = $response->error ?? 'Pencarian gagal.';
                return;
            }

            $resources = $response->getResources();

            if (empty($resources)) {
                $this->pullError = 'Tidak ada location ditemukan dengan nama tersebut.';
                return;
            }

            $this->pullResults = $resources;
        } catch (\Exception $e) {
            $this->pullError = 'Gagal menghubungi Satu Sehat: ' . $e->getMessage();
        }
    }

    /** Pilih location dari hasil pencarian dan simpan ke database */
    public function selectLocation(string $ihsNumber): void
    {
        if (!$this->pullLocalCode) {
            return;
        }

        $resource = collect($this->pullResults)->firstWhere('id', $ihsNumber);
        if (!$resource) {
            return;
        }

        // Cek konflik: IHS number sudah dipakai lokasi lain
        $existingByIhs = SatuSehatLocation::where('ihs_number', $ihsNumber)->first();
        $existingByIdent = $this->findLocation($this->pullLocalCode, $this->pullTabContext);

        if ($existingByIhs && ($existingByIdent === null || $existingByIhs->id !== $existingByIdent->id)) {
            $label = $existingByIhs->identifier ?? 'entri lain';
            $this->pullError = "IHS Number {$ihsNumber} sudah terdaftar untuk '{$label}'.";
            return;
        }

        $managingOrgRef = $resource['managingOrganization']['reference'] ?? '';
        $managingOrgId = $managingOrgRef ? last(explode('/', $managingOrgRef)) : null;

        $inferredType = $this->pullTabContext === 'poliklinik' ? 'ralan' : 'ranap';

        SatuSehatLocation::updateOrCreate(
            ['identifier' => $this->pullLocalCode, 'type' => $inferredType],
            [
                'ihs_number' => $ihsNumber,
                'name' => $resource['name'] ?? '',
                'status' => $resource['status'] ?? 'active',
                'managing_organization' => $managingOrgId,
                'raw_response' => $resource,
                'synced_at' => now(),
            ],
        );

        $this->showPullModal = false;
        $this->reset(['pullLocalCode', 'pullLocalName', 'pullTabContext', 'pullSearch', 'pullResults', 'pullError']);
        $this->toastSuccess("Location berhasil ditarik dari Satu Sehat. IHS: {$ihsNumber}");
    }

    /** Konfirmasi hapus */
    public function confirmDelete(string $localCode, string $localNama, string $tabContext): void
    {
        $this->deleteLocalCode = $localCode;
        $this->deleteLocalName = $localNama;
        $this->deleteTabContext = $tabContext;
        $this->showDeleteModal = true;
    }

    /** Nonaktifkan di Satu Sehat lalu hapus mapping lokal */
    public function deleteMapping(): void
    {
        if (!$this->deleteLocalCode) {
            return;
        }

        $loc = $this->findLocation($this->deleteLocalCode, $this->deleteTabContext);

        if ($loc) {
            if ($loc->ihs_number) {
                try {
                    app(LocationService::class)->updateStatus($loc->ihs_number, 'inactive');
                } catch (\Exception $e) {
                    $this->toastError('Gagal menonaktifkan di Satu Sehat: ' . $e->getMessage());
                    $this->showDeleteModal = false;
                    $this->reset(['deleteLocalCode', 'deleteLocalName', 'deleteTabContext']);
                    return;
                }
            }

            // Reset selectedLocation sebelum hapus agar Livewire tidak mencoba re-hydrate model yang sudah tidak ada
            if ($this->selectedLocation?->id === $loc->id) {
                $this->selectedLocation = null;
            }

            $loc->delete();
        }

        $this->showDeleteModal = false;
        $this->reset(['deleteLocalCode', 'deleteLocalName', 'deleteTabContext']);
        $this->toastSuccess('Location dinonaktifkan di Satu Sehat dan mapping lokal dihapus.');
    }

    public function cancelDelete(): void
    {
        $this->showDeleteModal = false;
        $this->reset(['deleteLocalCode', 'deleteLocalName', 'deleteTabContext']);
    }

    public function with(): array
    {
        $allLocs = ($this->tab === 'poliklinik' ? SatuSehatLocation::where('type', 'ralan') : SatuSehatLocation::where('type', '!=', 'ralan'))->with('organization')->get()->keyBy('identifier');

        $mappedCodes = $allLocs->keys()->toArray();
        $items = collect();
        $simrsError = false;
        $totalLocal = 0;

        try {
            if ($this->tab === 'poliklinik') {
                $query = Poliklinik::query();

                if ($this->search) {
                    $query->where(function ($q) {
                        $q->where('kd_poli', 'like', "%{$this->search}%")->orWhere('nm_poli', 'like', "%{$this->search}%");
                    });
                }

                if ($this->filterStatus === 'mapped') {
                    $query->whereIn('kd_poli', $mappedCodes);
                } elseif ($this->filterStatus === 'unmapped') {
                    $query->whereNotIn('kd_poli', $mappedCodes);
                }

                $totalLocal = Poliklinik::count();
                $items = $query->orderBy('nm_poli')->paginate(25);
            } else {
                $query = Bangsal::query();

                if ($this->search) {
                    $query->where(function ($q) {
                        $q->where('kd_bangsal', 'like', "%{$this->search}%")->orWhere('nm_bangsal', 'like', "%{$this->search}%");
                    });
                }

                if ($this->filterStatus === 'mapped') {
                    $query->whereIn('kd_bangsal', $mappedCodes);
                } elseif ($this->filterStatus === 'unmapped') {
                    $query->whereNotIn('kd_bangsal', $mappedCodes);
                }

                $totalLocal = Bangsal::count();
                $items = $query->orderBy('nm_bangsal')->paginate(25);
            }
        } catch (\Exception) {
            $simrsError = true;
        }

        // Hanya organization yang sudah memiliki IHS number
        $organizations = SatuSehatOrganization::whereNotNull('ihs_number')->orderBy('name')->get();

        return [
            'items' => $items,
            'allLocs' => $allLocs,
            'organizations' => $organizations,
            'totalLocal' => $totalLocal,
            'totalMapped' => $allLocs->count(),
            'simrsError' => $simrsError,
        ];
    }
};
?>

<div>
    <x-ui.page-header title="Satu Sehat — Location"
        subtitle="Mapping lokasi SIMRS (Poliklinik & Ruangan) ke Location Satu Sehat" />

    {{-- Error SIMRS --}}
    @if ($simrsError)
        <div
            class="flex items-center gap-3 p-4 mb-6 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
            <flux:icon name="exclamation-triangle" class="w-5 h-5 text-red-500 shrink-0" />
            <p class="text-sm text-red-700 dark:text-red-300">Koneksi ke database SIMRS gagal. Data lokasi tidak dapat
                ditampilkan.</p>
        </div>
    @endif

    {{-- Tab Navigation --}}
    <x-molecules.tabs>
    <x-atoms.tab-item wire:click="switchTab('poliklinik')">Poliklinik
            <span class="ml-1.5 text-xs opacity-70">(Rawat Jalan)</span></x-atoms.tab-item>
        <x-atoms.tab-item wire:click="switchTab('ruangan')">Bangsal / Ruangan
            <span class="ml-1.5 text-xs opacity-70">(Ranap, Lab, Apotek)</span></x-atoms.tab-item>
    
    </x-molecules.tabs>

    {{-- Toolbar --}}
    <div class="flex flex-col gap-3 mb-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center flex-1">
            <div class="flex-1 max-w-sm">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                    placeholder="{{ $tab === 'poliklinik' ? 'Cari kode atau nama poliklinik...' : 'Cari kode atau nama bangsal...' }}"
                    clearable />
            </div>
            <flux:select wire:model.live="filterStatus" class="sm:w-48">
                <flux:select.option value="">Semua Status</flux:select.option>
                <flux:select.option value="mapped">Sudah Dipetakan</flux:select.option>
                <flux:select.option value="unmapped">Belum Dipetakan</flux:select.option>
            </flux:select>
        </div>

        {{-- Stats chip --}}
        <div
            class="hidden sm:flex items-center gap-2.5 px-3.5 py-2.5 text-xs font-medium bg-white dark:bg-primary-dark-800 rounded-xl border border-zinc-200 dark:border-primary-dark-700 whitespace-nowrap shadow-sm">
            <span class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400">
                <span class="inline-block w-2 h-2 rounded-full bg-emerald-400"></span>
                {{ $totalMapped }} terpetakan
            </span>
            <span class="text-zinc-300 dark:text-primary-dark-600">/</span>
            <span class="text-zinc-500 dark:text-primary-dark-400">
                {{ $totalLocal }} {{ $tab === 'poliklinik' ? 'poliklinik' : 'bangsal' }}
            </span>
        </div>
    </div>

    {{-- Tabel --}}
    <div
        class="overflow-hidden bg-white border rounded-2xl dark:bg-primary-dark-800 border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr
                        class="border-b border-zinc-100 dark:border-primary-dark-700/60 bg-zinc-50/70 dark:bg-primary-dark-900/40">
                        <th
                            class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500 w-28">
                            Kode</th>
                        <th
                            class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500">
                            {{ $tab === 'poliklinik' ? 'Nama Poliklinik' : 'Nama Bangsal / Ruangan' }}
                        </th>
                        <th
                            class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500 w-28">
                            Tipe</th>
                        <th
                            class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500">
                            Mapping Location</th>
                        <th
                            class="hidden px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase sm:table-cell text-zinc-400 dark:text-primary-dark-500">
                            Organization</th>
                        <th
                            class="px-5 py-3.5 text-xs font-semibold tracking-wider text-center uppercase text-zinc-400 dark:text-primary-dark-500 w-28">
                            Status</th>
                        <th
                            class="px-5 py-3.5 text-xs font-semibold tracking-wider text-center uppercase text-zinc-400 dark:text-primary-dark-500 w-36">
                            Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100/80 dark:divide-primary-dark-700/40">
                    @php
                        $codeField = $tab === 'poliklinik' ? 'kd_poli' : 'kd_bangsal';
                        $namaField = $tab === 'poliklinik' ? 'nm_poli' : 'nm_bangsal';
                    @endphp
                    @php
                        $typeLabels = [
                            'ralan' => 'Rawat Jalan',
                            'ranap' => 'Rawat Inap',
                            'apotek' => 'Apotek',
                            'lab' => 'Lab',
                            'rad' => 'Radiologi',
                        ];
                        $typeColors = [
                            'ralan' => 'sky',
                            'ranap' => 'indigo',
                            'apotek' => 'green',
                            'lab' => 'amber',
                            'rad' => 'purple',
                        ];
                    @endphp
                    @forelse ($items as $item)
                        @php
                            $loc = $allLocs[$item->$codeField] ?? null;
                            $code = $item->$codeField;
                            $nama = $item->$namaField;
                        @endphp
                        <tr wire:key="{{ $tab }}-{{ $code }}"
                            class="group transition-colors hover:bg-zinc-50/60 dark:hover:bg-primary-dark-700/20">
                            <td class="px-5 py-4 whitespace-nowrap">
                                <span
                                    class="inline-block font-mono text-xs font-bold px-2 py-1 rounded-md
                                    bg-zinc-100 dark:bg-primary-dark-700 text-zinc-600 dark:text-primary-dark-300
                                    ring-1 ring-zinc-200 dark:ring-primary-dark-600">
                                    {{ $code }}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <p class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">
                                    {{ $nama }}
                                </p>
                            </td>
                            <td class="px-5 py-4">
                                @if ($loc && $loc->type)
                                    <flux:badge :color="$typeColors[$loc->type] ?? 'zinc'" size="sm"
                                        class="mt-1">
                                        {{ $typeLabels[$loc->type] ?? $loc->type }}
                                    </flux:badge>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                @if ($loc)
                                    <div class="flex items-start gap-2.5">
                                        <span
                                            class="mt-1 w-2 h-2 rounded-full bg-emerald-400 dark:bg-emerald-500 shrink-0 ring-2 ring-emerald-100 dark:ring-emerald-900/50"></span>
                                        <div class="min-w-0">
                                            <p
                                                class="font-mono text-xs font-bold text-emerald-700 dark:text-emerald-400">
                                                {{ $loc->ihs_number }}</p>
                                            <p
                                                class="mt-0.5 text-xs text-zinc-500 dark:text-primary-dark-400 leading-snug">
                                                {{ $loc->name }}
                                            </p>
                                        </div>
                                    </div>
                                @else
                                    <div class="flex items-center gap-2 text-zinc-400 dark:text-primary-dark-500">
                                        <span class="w-2 h-2 rounded-full bg-zinc-200 dark:bg-primary-dark-600"></span>
                                        <span class="text-xs italic">Belum dipetakan</span>
                                    </div>
                                @endif
                            </td>
                            <td class="hidden px-5 py-4 sm:table-cell">
                                @if ($loc && $loc->managing_organization)
                                    <p class="text-xs font-semibold text-zinc-700 dark:text-primary-dark-200">
                                        {{ $loc->organization?->name ?? '—' }}</p>
                                    <p class="mt-0.5 font-mono text-xs text-zinc-400 dark:text-primary-dark-500">
                                        {{ $loc->managing_organization }}</p>
                                @else
                                    <span class="text-xs text-zinc-300 dark:text-primary-dark-600">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-center">
                                @if ($loc)
                                    @php
                                        $locStatusColor = match ($loc->status) {
                                            'active' => 'green',
                                            'suspended' => 'yellow',
                                            'inactive' => 'zinc',
                                            default => 'zinc',
                                        };
                                        $locStatusLabel = match ($loc->status) {
                                            'active' => 'Aktif',
                                            'suspended' => 'Ditangguhkan',
                                            'inactive' => 'Nonaktif',
                                            default => ucfirst($loc->status),
                                        };
                                    @endphp
                                    <flux:badge :color="$locStatusColor" size="sm">{{ $locStatusLabel }}
                                    </flux:badge>
                                @else
                                    <span class="text-xs text-zinc-300 dark:text-primary-dark-600">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-center whitespace-nowrap">
                                <div class="flex items-center justify-center gap-1.5">
                                    @if (!$loc)
                                        {{-- Belum dipetakan: kirim atau tarik dari SS --}}
                                        <x-atoms.button
                                            wire:click="openSendModal('{{ $code }}', '{{ addslashes($nama) }}', '{{ $tab }}')"
                                            size="sm" variant="ghost" icon="paper-airplane"
                                            title="Kirim ke Satu Sehat" />
                                        <x-atoms.button
                                            wire:click="openPullModal('{{ $code }}', '{{ addslashes($nama) }}', '{{ $tab }}')"
                                            size="sm" variant="ghost" icon="arrow-down-tray"
                                            title="Tarik dari Satu Sehat" />
                                    @else
                                        {{-- Sudah dipetakan: lihat detail atau hapus --}}
                                        <x-atoms.button variant="ghost"
                                            wire:click="viewDetail('{{ $code }}', '{{ $tab }}')"
                                            size="sm" icon="eye" title="Lihat detail" />
                                        <x-atoms.button variant="ghost"
                                            wire:click="confirmDelete('{{ $code }}', '{{ addslashes($nama) }}', '{{ $tab }}')"
                                            size="sm" icon="trash"
                                            class="text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-opacity duration-150"
                                            title="Hapus mapping" />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-16 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <div
                                        class="flex items-center justify-center w-14 h-14 rounded-2xl bg-zinc-100 dark:bg-primary-dark-700">
                                        <flux:icon name="map-pin"
                                            class="w-7 h-7 text-zinc-300 dark:text-primary-dark-500" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-zinc-500 dark:text-primary-dark-400">
                                            {{ $simrsError ? 'Koneksi SIMRS gagal' : 'Tidak ada data ditemukan' }}
                                        </p>
                                        <p class="mt-0.5 text-xs text-zinc-400 dark:text-primary-dark-500">
                                            {{ $simrsError ? 'Periksa konfigurasi database SIMRS' : 'Coba ubah filter pencarian' }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($items instanceof \Illuminate\Pagination\LengthAwarePaginator && $items->hasPages())
            <div
                class="px-5 py-4 border-t border-zinc-100 dark:border-primary-dark-700/60 bg-zinc-50/50 dark:bg-primary-dark-900/20">
                {{ $items->links() }}
            </div>
        @endif
    </div>

    {{-- Modal Input Posisi (Kirim / Update) --}}
    <x-organisms.modal wire:model="showPositionModal" maxWidth="2xl" title="">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">
                    {{ $positionAction === 'kirim' ? 'Kirim Location ke Satu Sehat' : 'Update Location ke Satu Sehat' }}
                </flux:heading>
                @if ($positionLocalName)
                    <flux:text class="mt-0.5">
                        {{ $positionTabContext === 'poliklinik' ? 'Poliklinik' : 'Bangsal' }}:
                        <span class="font-semibold">{{ $positionLocalName }}</span>
                        @if ($positionLocalCode)
                            <span class="font-mono text-xs text-zinc-400">({{ $positionLocalCode }})</span>
                        @endif
                    </flux:text>
                @endif
            </div>

            @if ($organizations->isEmpty())
                <div
                    class="flex items-start gap-3 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-xs text-amber-700 dark:text-amber-300">
                    <flux:icon name="exclamation-triangle" class="w-4 h-4 shrink-0 mt-0.5" />
                    <p>Belum ada Organization yang terdaftar di Satu Sehat. Petakan minimal satu Organization terlebih
                        dahulu sebelum mengirim Location.</p>
                </div>
            @endif

            {{-- Peta Leaflet + Input koordinat --}}
            <div x-data="positionMap()" @position-modal-opened.window="setTimeout(() => initMap(), 300)">
                {{-- Peta interaktif (wire:ignore agar Livewire tidak merusak instance Leaflet) --}}
                <div wire:ignore>
                    <div x-ref="mapEl"
                        class="h-64 w-full rounded-xl border border-zinc-200 dark:border-primary-dark-700 overflow-hidden"
                        style="z-index: 0;"></div>
                    <p class="mt-1.5 text-xs text-center text-zinc-400 dark:text-primary-dark-500">
                        Klik pada peta untuk menentukan posisi — marker dapat digeser untuk penyesuaian
                    </p>
                </div>

                {{-- Input koordinat (diisi otomatis saat klik/geser marker) --}}
                <div class="mt-3 grid grid-cols-3 gap-3">
                    <div>
                        <flux:input wire:model="positionLongitude" label="Longitude" placeholder="107.619"
                            type="text" inputmode="decimal" />
                        @error('positionLongitude')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <flux:input wire:model="positionLatitude" label="Latitude" placeholder="-6.917"
                            type="text" inputmode="decimal" />
                        @error('positionLatitude')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <flux:input wire:model="positionAltitude" label="Altitude (m)" placeholder="0"
                            type="text" inputmode="decimal" />
                        @error('positionAltitude')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Sinkronisasi koordinat yang diketik manual ke posisi pin di peta --}}
                
        <x-slot:footer>
            <div class="flex justify-end mt-1">
                    <x-atoms.button @click="updatePin()" type="button"
                        class="flex items-center gap-1.5 text-xs text-zinc-400 hover:text-zinc-600 dark:hover:text-primary-dark-300 transition-colors px-2 py-1 rounded-md hover:bg-zinc-100 dark:hover:bg-primary-dark-700">
                        <flux:icon name="map-pin" class="w-3.5 h-3.5" />
                        Perbarui pin dari koordinat
                    </x-atoms.button>
                </div>
            </div>

            <div class="space-y-3">
                {{-- Tipe lokasi: auto untuk poliklinik, pilih untuk bangsal --}}
                @if ($positionTabContext === 'poliklinik')
                    <div
                        class="flex items-center gap-2 px-3 py-2 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/40 border border-zinc-200 dark:border-primary-dark-700 text-xs text-zinc-500 dark:text-primary-dark-400">
                        <flux:icon name="information-circle" class="w-4 h-4 shrink-0" />
                        Tipe lokasi otomatis: <span class="font-semibold ml-1">Rawat Jalan (ralan)</span>
                    </div>
                @else
                    <div>
                        <label class="block mb-1 text-sm font-medium text-zinc-700 dark:text-primary-dark-300">Tipe
                            Lokasi</label>
                        <flux:select wire:model="positionType">
                            <flux:select.option value="ranap">Rawat Inap (ranap)</flux:select.option>
                            <flux:select.option value="apotek">Apotek (apotek)</flux:select.option>
                            <flux:select.option value="lab">Laboratorium (lab)</flux:select.option>
                            <flux:select.option value="rad">Radiologi (rad)</flux:select.option>
                        </flux:select>
                        @error('positionType')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                <div>
                    <label class="block mb-1 text-sm font-medium text-zinc-700 dark:text-primary-dark-300">Managing
                        Organization</label>
                    <flux:select wire:model="positionManagingOrg">
                        <flux:select.option value="">— Pilih Organization —</flux:select.option>
                        @foreach ($organizations as $org)
                            <flux:select.option value="{{ $org->ihs_number }}">
                                {{ $org->name }} ({{ $org->ihs_number }})
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    @error('positionManagingOrg')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-1">
                <x-atoms.button variant="ghost" wire:click="$set('showPositionModal', false)">Batal</x-atoms.button>
                <x-atoms.button variant="primary" wire:click="savePosition" wire:loading.attr="disabled"
                    wire:target="savePosition" icon="paper-airplane" :disabled="$organizations->isEmpty()">
                    <span wire:loading.remove wire:target="savePosition">
                        {{ $positionAction === 'kirim' ? 'Kirim ke Satu Sehat' : 'Update ke Satu Sehat' }}
                    </span>
                    <span wire:loading wire:target="savePosition">Memproses...</span>
                </x-atoms.button>
            </x-slot:footer>
    </div>
    </div>
    </x-organisms.modal>

    {{-- Modal Tarik dari Satu Sehat --}}
    <x-organisms.modal wire:model="showPullModal" maxWidth="xl" title="">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Tarik Location dari Satu Sehat</flux:heading>
                @if ($pullLocalCode)
                    <flux:text class="mt-0.5">
                        {{ $pullTabContext === 'poliklinik' ? 'Poliklinik' : 'Bangsal' }}:
                        <span class="font-semibold">{{ $pullLocalName }}</span>
                        <span class="font-mono text-xs text-zinc-400">({{ $pullLocalCode }})</span>
                    </flux:text>
                @endif
            </div>

            {{-- Search --}}
            <div class="flex gap-2">
                <div class="flex-1">
                    <flux:input wire:model="pullSearch" wire:keydown.enter="searchLocation"
                        placeholder="Nama location di Satu Sehat..." icon="magnifying-glass" />
                </div>
                <x-atoms.button wire:click="searchLocation" wire:loading.attr="disabled" wire:target="searchLocation"
                    variant="primary" icon="magnifying-glass">
                    <span wire:loading.remove wire:target="searchLocation">Cari</span>
                    <span wire:loading wire:target="searchLocation">Mencari...</span>
                </x-atoms.button>
            </div>

            {{-- Error --}}
            @if ($pullError)
                <div
                    class="flex items-center gap-2.5 px-3.5 py-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-sm text-red-700 dark:text-red-300">
                    <flux:icon name="exclamation-circle" class="w-4 h-4 shrink-0" />
                    {{ $pullError }}
                </div>
            @endif

            {{-- Hasil Pencarian --}}
            @if (!empty($pullResults))
                <div class="rounded-xl border border-zinc-200 dark:border-primary-dark-700 overflow-hidden">
                    <div
                        class="px-4 py-2.5 bg-zinc-50 dark:bg-primary-dark-900/40 border-b border-zinc-200 dark:border-primary-dark-700">
                        <p
                            class="text-xs font-semibold text-zinc-500 dark:text-primary-dark-400 uppercase tracking-wide">
                            {{ count($pullResults) }} hasil ditemukan
                        </p>
                    </div>
                    <ul class="divide-y divide-zinc-100 dark:divide-primary-dark-700/50 max-h-72 overflow-y-auto">
                        @foreach ($pullResults as $resource)
                            @php
                                $resIhs = $resource['id'] ?? '-';
                                $resNama = $resource['name'] ?? '-';
                                $resStatus = $resource['status'] ?? 'active';
                                $resMgOrg =
                                    last(explode('/', $resource['managingOrganization']['reference'] ?? '')) ?: '-';
                            @endphp
                            <li wire:click="selectLocation('{{ $resIhs }}')"
                                wire:loading.class="opacity-50 pointer-events-none" wire:target="selectLocation"
                                class="flex items-center justify-between gap-4 px-4 py-3
                                       cursor-pointer select-none group
                                       hover:bg-emerald-50 dark:hover:bg-emerald-900/20
                                       active:bg-emerald-100 dark:active:bg-emerald-900/30
                                       transition-colors">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span
                                            class="font-mono text-xs font-bold text-emerald-700 dark:text-emerald-400">
                                            {{ $resIhs }}
                                        </span>
                                        <flux:badge :color="$resStatus === 'active' ? 'green' : 'zinc'"
                                            size="sm">
                                            {{ $resStatus === 'active' ? 'Aktif' : ucfirst($resStatus) }}
                                        </flux:badge>
                                    </div>
                                    <p
                                        class="mt-0.5 text-sm font-medium text-zinc-800 dark:text-primary-dark-200 truncate">
                                        {{ $resNama }}
                                    </p>
                                    <p class="mt-0.5 text-xs text-zinc-400 dark:text-primary-dark-500 font-mono">
                                        Org: {{ $resMgOrg }}
                                    </p>
                                </div>
                                <flux:icon name="check-circle"
                                    class="w-5 h-5 shrink-0 text-emerald-400 dark:text-emerald-500
                                           opacity-0 group-hover:opacity-100 transition-opacity" />
                            </li>
                        @endforeach
                    </ul>
                </div>
            @elseif (empty($pullResults) && !$pullError)
                <div
                    class="flex flex-col items-center gap-2 py-8 text-center text-zinc-400 dark:text-primary-dark-500">
                    <flux:icon name="magnifying-glass" class="w-8 h-8 opacity-40" />
                    <p class="text-sm">Ketik nama location lalu klik <strong>Cari</strong></p>
                </div>
            @endif

            
        <x-slot:footer>
            <div class="flex justify-end pt-1">
                <x-atoms.button variant="ghost" wire:click="$set('showPullModal', false)">Batal</x-atoms.button>
            </div>
        </x-slot:footer>
    </div>
    </x-organisms.modal>

    {{-- Modal Detail --}}
    <x-organisms.modal wire:model="showDetailModal" maxWidth="xl" title="">
        @if ($selectedLocation)
            @php
                $stColor = match ($selectedLocation->status) {
                    'active' => 'green',
                    'suspended' => 'yellow',
                    default => 'zinc',
                };
                $stLabel = match ($selectedLocation->status) {
                    'active' => 'Aktif',
                    'suspended' => 'Ditangguhkan',
                    default => 'Nonaktif',
                };
                $physType = $selectedLocation->getPhysicalTypeCode();
                $physLabels = [
                    'ro' => 'Ruangan',
                    'wa' => 'Bangsal/Ward',
                    'bu' => 'Gedung',
                    'co' => 'Koridor',
                    'area' => 'Area',
                    've' => 'Kendaraan',
                ];
                $pos = $selectedLocation->getPosition();
            @endphp
            <div class="space-y-5">
                {{-- Header --}}
                <div class="flex items-start gap-4 pb-4 border-b border-zinc-200 dark:border-primary-dark-700">
                    <div
                        class="flex items-center justify-center flex-shrink-0 w-12 h-12 rounded-xl bg-primary-100 dark:bg-primary-900/30">
                        <flux:icon name="map-pin" class="w-6 h-6 text-primary-600 dark:text-primary-400" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <h2 class="text-base font-bold text-zinc-900 dark:text-white truncate">
                            {{ $selectedLocation->name }}
                        </h2>
                        <div class="flex flex-wrap items-center gap-2 mt-1.5">
                            <flux:badge :color="$stColor" size="sm">{{ $stLabel }}</flux:badge>
                            @if ($physType)
                                <flux:badge color="zinc" size="sm">
                                    {{ $physLabels[$physType] ?? $physType }}
                                </flux:badge>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Identitas --}}
                <div>
                    <p
                        class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-primary-dark-500">
                        Identitas</p>
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                        <div>
                            <dt class="text-xs text-zinc-500 dark:text-primary-dark-400">IHS Number</dt>
                            <dd class="mt-0.5 font-mono font-bold text-emerald-700 dark:text-emerald-400">
                                {{ $selectedLocation->ihs_number }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-500 dark:text-primary-dark-400">Identifier</dt>
                            <dd class="mt-0.5 font-mono text-zinc-700 dark:text-primary-dark-300">
                                {{ $selectedLocation->identifier ?? '-' }}</dd>
                        </div>
                    </dl>
                </div>

                {{-- Posisi --}}
                <div>
                    <p
                        class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-primary-dark-500">
                        Posisi</p>
                    <dl class="grid grid-cols-3 gap-x-6 gap-y-3 text-sm">
                        <div>
                            <dt class="text-xs text-zinc-500 dark:text-primary-dark-400">Longitude</dt>
                            <dd class="mt-0.5 font-mono text-zinc-700 dark:text-primary-dark-300">
                                {{ $pos['longitude'] ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-500 dark:text-primary-dark-400">Latitude</dt>
                            <dd class="mt-0.5 font-mono text-zinc-700 dark:text-primary-dark-300">
                                {{ $pos['latitude'] ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-500 dark:text-primary-dark-400">Altitude</dt>
                            <dd class="mt-0.5 font-mono text-zinc-700 dark:text-primary-dark-300">
                                {{ $pos['altitude'] ?? '-' }}</dd>
                        </div>
                    </dl>
                </div>

                {{-- Peta Posisi (preview read-only) --}}
                @if (!empty($pos['latitude']) && !empty($pos['longitude']))
                    <div>
                        <p
                            class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-primary-dark-500">
                            Peta Posisi</p>
                        <div x-data="detailMap({{ $pos['latitude'] }}, {{ $pos['longitude'] }})" @detail-modal-opened.window="setTimeout(() => initMap(), 300)">
                            <div wire:ignore>
                                <div x-ref="detailMapEl"
                                    class="h-48 w-full rounded-xl border border-zinc-200 dark:border-primary-dark-700 overflow-hidden"
                                    style="z-index: 0;"></div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Managing Organization --}}
                <div>
                    <p
                        class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-primary-dark-500">
                        Managing Organization</p>
                    <dl class="text-sm">
                        <dd class="font-mono text-zinc-700 dark:text-primary-dark-300">
                            {{ $selectedLocation->managing_organization ?? '-' }}
                            @if ($selectedLocation->organization)
                                <span class="font-sans text-zinc-500">
                                    — {{ $selectedLocation->organization->name }}</span>
                            @endif
                        </dd>
                    </dl>
                </div>

                {{-- Kontak --}}
                @php
                    $detailPhone = $selectedLocation->getTelecom('phone');
                    $detailEmail = $selectedLocation->getTelecom('email');
                    $detailWebsite = $selectedLocation->getTelecom('url');
                @endphp
                <div>
                    <p
                        class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-primary-dark-500">
                        Kontak</p>
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                        <div>
                            <dt class="text-xs text-zinc-500 dark:text-primary-dark-400">Telepon</dt>
                            <dd class="mt-0.5 text-zinc-700 dark:text-primary-dark-300">{{ $detailPhone ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-500 dark:text-primary-dark-400">Email</dt>
                            <dd class="mt-0.5 text-zinc-700 dark:text-primary-dark-300 truncate">
                                {{ $detailEmail ?? '-' }}
                            </dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-xs text-zinc-500 dark:text-primary-dark-400">Website</dt>
                            <dd class="mt-0.5 text-zinc-700 dark:text-primary-dark-300 truncate">
                                {{ $detailWebsite ?? '-' }}
                            </dd>
                        </div>
                    </dl>
                </div>

                {{-- Disinkron --}}
                <div class="text-xs text-zinc-400 dark:text-primary-dark-500">
                    Disinkron: {{ $selectedLocation->synced_at?->format('d/m/Y H:i') ?? '-' }}
                </div>

                {{-- Raw FHIR (opsional, collapsed) --}}
                @if ($selectedLocation->raw_response)
                    <details class="group">
                        <summary
                            class="cursor-pointer text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-primary-dark-500
                                   hover:text-zinc-600 dark:hover:text-primary-dark-300 transition-colors select-none">
                            <span class="group-open:hidden">Lihat FHIR Resource</span>
                            <span class="hidden group-open:inline">Sembunyikan</span>
                        </summary>
                        <x-atoms.code-block language="json" maxHeight="max-h-52" class="mt-2">{{ json_encode($selectedLocation->raw_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</x-atoms.code-block>
                    </details>
                @endif

                <div class="flex justify-between items-center pt-1">
                    <x-atoms.button wire:click="openUpdateModal" wire:loading.attr="disabled"
                        wire:target="openUpdateModal" variant="primary" icon="arrow-path">
                        Update ke SS
                    </x-atoms.button>
                    <x-atoms.button variant="ghost" wire:click="$set('showDetailModal', false)">Tutup</x-atoms.button>
                </div>
            </div>
        @endif
    
    </x-organisms.modal>

    {{-- Modal Konfirmasi Hapus --}}
    <x-organisms.modal wire:model="showDeleteModal" maxWidth="sm" title="">
        <div class="space-y-5">
            <div class="flex items-center gap-4">
                <div
                    class="flex items-center justify-center w-12 h-12 rounded-2xl bg-red-100 dark:bg-red-900/30 shrink-0">
                    <flux:icon name="trash" class="w-6 h-6 text-red-600 dark:text-red-400" />
                </div>
                <div>
                    <flux:heading size="lg">Hapus Mapping?</flux:heading>
                    <flux:text class="mt-0.5">Location untuk <strong>{{ $deleteLocalName }}</strong> akan dihapus.
                    </flux:text>
                </div>
            </div>

            <div
                class="p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-xs text-amber-700 dark:text-amber-300">
                <flux:icon name="exclamation-triangle" class="inline w-3.5 h-3.5 mr-1" />
                Location akan dinonaktifkan di Satu Sehat, kemudian mapping lokal dihapus.
            </div>

            
        <x-slot:footer>
            <div class="flex justify-end gap-3">
                <x-atoms.button variant="ghost" wire:click="cancelDelete">Batal</x-atoms.button>
                <x-atoms.button variant="danger" wire:click="deleteMapping" wire:loading.attr="disabled"
                    wire:target="deleteMapping" icon="trash">
                    <span wire:loading.remove wire:target="deleteMapping">Hapus</span>
                    <span wire:loading wire:target="deleteMapping">Menghapus...</span>
                </x-atoms.button>
            </div>
        </x-slot:footer>
    </div>
    </x-organisms.modal>
</div>

@pushOnce('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" />
@endPushOnce

@pushOnce('scripts')
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        function positionMap() {
            return {
                map: null,
                marker: null,

                initMap() {
                    // Hapus instance sebelumnya jika ada
                    if (this.map) {
                        this.map.remove();
                        this.map = null;
                        this.marker = null;
                    }

                    const latStr = this.$wire.positionLatitude;
                    const lngStr = this.$wire.positionLongitude;
                    const lat = parseFloat(latStr);
                    const lng = parseFloat(lngStr);
                    const hasCoords = !isNaN(lat) && !isNaN(lng) && latStr !== '' && lngStr !== '';

                    // Jika ada koordinat (mode update), langsung tampilkan
                    if (hasCoords) {
                        this.buildMap(lat, lng, 17);
                        this.setMarker(lat, lng);
                        return;
                    }

                    // Jika tidak ada koordinat, coba dapatkan posisi saat ini
                    if (navigator.geolocation) {
                        navigator.geolocation.getCurrentPosition(
                            (pos) => {
                                this.buildMap(pos.coords.latitude, pos.coords.longitude, 17);
                            },
                            () => {
                                // Izin ditolak atau gagal → fallback ke pusat Indonesia
                                this.buildMap(-7.25, 112.75, 12);
                            }, {
                                timeout: 5000
                            }
                        );
                    } else {
                        this.buildMap(-7.25, 112.75, 12);
                    }
                },

                buildMap(lat, lng, zoom) {
                    this.map = L.map(this.$refs.mapEl).setView([lat, lng], zoom);

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                        maxZoom: 19,
                    }).addTo(this.map);

                    // Klik peta → set posisi, update state Livewire, fetch altitude
                    this.map.on('click', (e) => {
                        this.setMarker(e.latlng.lat, e.latlng.lng);
                        this.$wire.set('positionLatitude', e.latlng.lat.toFixed(6));
                        this.$wire.set('positionLongitude', e.latlng.lng.toFixed(6));
                        this.fetchAltitude(e.latlng.lat, e.latlng.lng);
                    });
                },

                setMarker(lat, lng) {
                    if (this.marker) {
                        this.marker.setLatLng([lat, lng]);
                    } else {
                        this.marker = L.marker([lat, lng], {
                            draggable: true
                        }).addTo(this.map);
                        // Geser marker → update state Livewire dan fetch altitude
                        this.marker.on('dragend', (e) => {
                            const pos = e.target.getLatLng();
                            this.$wire.set('positionLatitude', pos.lat.toFixed(6));
                            this.$wire.set('positionLongitude', pos.lng.toFixed(6));
                            this.fetchAltitude(pos.lat, pos.lng);
                        });
                    }
                },

                async fetchAltitude(lat, lng) {
                    try {
                        const res = await fetch(
                            `https://api.open-meteo.com/v1/elevation?latitude=${lat}&longitude=${lng}`
                        );
                        const data = await res.json();
                        const altitude = data.elevation?.[0];
                        if (altitude !== undefined && altitude !== null) {
                            this.$wire.set('positionAltitude', String(Math.round(altitude)));
                        }
                    } catch {
                        // Gagal fetch altitude — biarkan user isi manual
                    }
                },

                // Sinkronisasi koordinat yang diketik manual ke posisi pin di peta
                updatePin() {
                    if (!this.map) return;
                    const lat = parseFloat(this.$wire.positionLatitude);
                    const lng = parseFloat(this.$wire.positionLongitude);
                    if (!isNaN(lat) && !isNaN(lng)) {
                        this.setMarker(lat, lng);
                        this.map.setView([lat, lng], 17);
                    }
                },
            };
        }

        /** Peta read-only untuk preview di modal detail */
        function detailMap(lat, lng) {
            return {
                map: null,
                lat: lat,
                lng: lng,

                initMap() {
                    if (this.map) {
                        this.map.remove();
                        this.map = null;
                    }

                    if (!this.lat || !this.lng) return;

                    this.map = L.map(this.$refs.detailMapEl, {
                        zoomControl: true,
                        dragging: true,
                        scrollWheelZoom: false,
                        doubleClickZoom: false,
                    }).setView([this.lat, this.lng], 17);

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                        maxZoom: 19,
                    }).addTo(this.map);

                    L.marker([this.lat, this.lng]).addTo(this.map);
                },
            };
        }
    </script>
@endPushOnce
