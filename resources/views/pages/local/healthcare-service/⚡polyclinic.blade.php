<?php

use App\Jobs\SyncBpjsHealthcareServicesJob;
use App\Models\Bpjs\BpjsHealthcareService;
use App\Models\Mapping\HealthcareServiceMap;
use App\Models\Mapping\HsServiceItem;
use App\Models\Simrs\Poliklinik;
use App\Models\SatuSehat\SatuSehatHealthcareService;
use App\Models\SatuSehat\SatuSehatLocation;
use App\Models\SatuSehat\SatuSehatOrganization;
use App\Services\SatuSehat\Resources\HealthcareServiceService;
use App\Services\SatuSehat\Resources\LocationService;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('Healthcare Service — Poliklinik')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterBpjs = '';

    #[Url]
    public string $filterSs = '';

    public int $perPage = 25;

    // Modal mapping komprehensif
    public bool $showMappingModal = false;
    public ?string $mappingPoliCode = null;
    public string $mappingPoliName = '';

    public bool $showFhirSearchModal = false;
    public string $addingItemType = '';

    // Konfirmasi hapus semua mapping
    public bool $showDeleteMappingModal = false;
    public ?string $deleteMappingCode = null;
    public string $deleteMappingName = '';

    // Modal BPJS
    public bool $showBpjsDeleteModal = false;
    public ?string $deleteBpjsCode = null;
    public string $deleteBpjsName = '';
    public bool $showSyncModal = false;

    // Modal SS HealthcareService
    public bool $showHsDetailModal = false;
    public ?SatuSehatHealthcareService $selectedService = null;
    public bool $showHsDeleteModal = false;
    public ?string $deleteHsPoliId = null;
    public string $deleteHsPoliName = '';
    public bool $showHsPullModal = false;
    public ?string $pullHsPoliId = null;
    public string $pullHsPoliName = '';

    // Modal SS Location ralan
    public bool $showLocDetailModal = false;
    public ?SatuSehatLocation $selectedLocation = null;
    public bool $showPositionModal = false;
    public string $positionAction = 'kirim';
    public ?string $positionLocalCode = null;
    public string $positionLocalName = '';
    public string $positionLongitude = '';
    public string $positionLatitude = '';
    public string $positionAltitude = '';
    public string $positionManagingOrg = '';
    public bool $positionHasPhysType = false;
    public bool $showLocPullModal = false;
    public ?string $pullLocCode = null;
    public string $pullLocName = '';
    public string $activePullType = '';
    public bool $showLocDeleteModal = false;
    public ?string $deleteLocCode = null;
    public string $deleteLocName = '';

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

    // --- Mapping ---

    public function openMappingModal(string $poliCode, string $poliName): void
    {
        $this->mappingPoliCode = $poliCode;
        $this->mappingPoliName = $poliName;
        $this->showMappingModal = true;
    }

    public function openAddItemModal(string $type): void
    {
        $this->addingItemType = $type;
        $this->showFhirSearchModal = true;
    }

    #[On('fhir-codesystem-selected')]
    public function fhirSelected(array $item): void
    {
        if (!$this->mappingPoliCode || !$this->addingItemType) {
            return;
        }

        if ($this->addingItemType === 'physical-type') {
            HealthcareServiceMap::updateOrCreate(
                ['type' => 'polyclinic', 'local_code' => $this->mappingPoliCode],
                [
                    'physical_type_code' => $item['system_code'],
                    'physical_type_term' => $item['system_term'],
                    'physical_type_display' => $item['system_display'] ?? null,
                ],
            );
        } else {
            HsServiceItem::updateOrCreate(['type' => 'polyclinic', 'local_code' => $this->mappingPoliCode, 'item_type' => $this->addingItemType, 'system_code' => $item['system_code']], ['system_term' => $item['system_term'], 'system_display' => $item['system_display'] ?? null]);
        }

        $this->showFhirSearchModal = false;
        $this->addingItemType = '';
        $this->toastSuccess('Mapping berhasil disimpan.');
    }

    public function removeHsItem(string $itemId): void
    {
        HsServiceItem::destroy($itemId);
        $this->toastSuccess('Item mapping berhasil dihapus.');
    }

    public function removePhysicalType(): void
    {
        HealthcareServiceMap::where('type', 'polyclinic')
            ->where('local_code', $this->mappingPoliCode)
            ->update([
                'physical_type_code' => null,
                'physical_type_term' => null,
                'physical_type_display' => null,
            ]);
        $this->toastSuccess('Physical Type berhasil dihapus.');
    }

    public function confirmDeleteMapping(string $poliCode, string $poliName): void
    {
        $this->deleteMappingCode = $poliCode;
        $this->deleteMappingName = $poliName;
        $this->showDeleteMappingModal = true;
    }

    public function deleteMapping(): void
    {
        HsServiceItem::where('type', 'polyclinic')->where('local_code', $this->deleteMappingCode)->delete();
        HealthcareServiceMap::where('type', 'polyclinic')->where('local_code', $this->deleteMappingCode)->delete();
        $this->showDeleteMappingModal = false;
        $this->showMappingModal = false;
        $this->reset(['deleteMappingCode', 'deleteMappingName']);
        $this->toastSuccess('Semua mapping berhasil dihapus.');
    }

    // --- BPJS UUID ---

    public function generateBpjsUuid(string $code, string $name): void
    {
        if (BpjsHealthcareService::where('type', 'poliklinik')->where('local_code', $code)->exists()) {
            $this->toastWarning('Poliklinik ini sudah memiliki UUID BPJS.');
            return;
        }

        BpjsHealthcareService::create(['type' => 'poliklinik', 'local_code' => $code, 'name' => $name]);
        $this->toastSuccess("UUID BPJS berhasil di-generate untuk: {$name}");
    }

    public function confirmBpjsDelete(string $code, string $name): void
    {
        $this->deleteBpjsCode = $code;
        $this->deleteBpjsName = $name;
        $this->showBpjsDeleteModal = true;
    }

    public function deleteBpjsUuid(): void
    {
        if (!$this->deleteBpjsCode) {
            return;
        }

        BpjsHealthcareService::where('type', 'poliklinik')->where('local_code', $this->deleteBpjsCode)->delete();
        $this->showBpjsDeleteModal = false;
        $this->reset(['deleteBpjsCode', 'deleteBpjsName']);
        $this->toastSuccess('UUID BPJS berhasil dihapus.');
    }

    public function syncAllBpjs(): void
    {
        SyncBpjsHealthcareServicesJob::dispatch('poliklinik');
        $this->showSyncModal = false;
        $this->toastSuccess('Sync BPJS Poliklinik telah dijadwalkan di queue.', 'Dijadwalkan');
    }

    // --- SS HealthcareService ---

    public function viewHsDetail(string $kdPoli): void
    {
        $this->selectedService = SatuSehatHealthcareService::findByIdentifier($kdPoli);
        $this->showHsDetailModal = true;
    }

    public function sendHsToSatuSehat(string $kdPoli, string $nmPoli): void
    {
        $existing = SatuSehatHealthcareService::findByIdentifier($kdPoli);
        if ($existing?->ihs_number) {
            $this->toastWarning("Poliklinik ini sudah memiliki IHS Number: {$existing->ihs_number}.");
            return;
        }

        try {
            $response = app(HealthcareServiceService::class)->createHealthcareService($nmPoli, $kdPoli);
        } catch (\Exception $e) {
            $this->toastError($e->getMessage());
            return;
        }

        if (!$response->success) {
            $this->toastError('Satu Sehat menolak permintaan: ' . ($response->error ?? 'Kesalahan tidak diketahui.'));
            return;
        }

        SatuSehatHealthcareService::updateOrCreate(
            ['identifier' => $kdPoli],
            [
                'ihs_number' => $response->resourceId,
                'name' => $response->data['name'] ?? $nmPoli,
                'status' => 'active',
                'raw_response' => $response->data,
                'synced_at' => now(),
            ],
        );

        $this->toastSuccess("HealthcareService berhasil dikirim ke Satu Sehat. IHS: {$response->resourceId}");
    }

    public function updateHsService(): void
    {
        if (!$this->selectedService?->ihs_number) {
            return;
        }

        try {
            $response = app(HealthcareServiceService::class)->updateHealthcareService($this->selectedService->ihs_number, $this->selectedService->name, $this->selectedService->identifier, true);
        } catch (\Exception $e) {
            $this->toastError($e->getMessage());
            return;
        }

        if (!$response->success) {
            $this->toastError('Satu Sehat menolak permintaan: ' . ($response->error ?? 'Kesalahan tidak diketahui.'));
            return;
        }

        $this->selectedService->update([
            'status' => 'active',
            'raw_response' => $response->data,
            'synced_at' => now(),
        ]);

        $this->selectedService = $this->selectedService->fresh();
        $this->toastSuccess("HealthcareService berhasil diperbarui. IHS: {$this->selectedService->ihs_number}");
    }

    public function confirmHsDelete(string $kdPoli, string $nmPoli): void
    {
        $this->deleteHsPoliId = $kdPoli;
        $this->deleteHsPoliName = $nmPoli;
        $this->showHsDeleteModal = true;
    }

    public function deleteHsMapping(): void
    {
        if (!$this->deleteHsPoliId) {
            return;
        }

        $service = SatuSehatHealthcareService::findByIdentifier($this->deleteHsPoliId);

        if ($service) {
            if ($service->ihs_number) {
                try {
                    app(HealthcareServiceService::class)->updateHealthcareService($service->ihs_number, $service->name, $service->identifier, false);
                } catch (\Exception $e) {
                    $this->toastError('Gagal menonaktifkan di Satu Sehat: ' . $e->getMessage());
                    $this->showHsDeleteModal = false;
                    $this->reset(['deleteHsPoliId', 'deleteHsPoliName']);
                    return;
                }
            }

            $service->delete();
        }

        $this->showHsDeleteModal = false;
        $this->reset(['deleteHsPoliId', 'deleteHsPoliName']);
        $this->toastSuccess('HealthcareService dinonaktifkan dan mapping lokal dihapus.');
    }

    public function openHsPullModal(string $kdPoli, string $nmPoli): void
    {
        $this->pullHsPoliId = $kdPoli;
        $this->pullHsPoliName = $nmPoli;
        $this->activePullType = 'hs';
        $this->showHsPullModal = true;
    }

    // --- SS Location ralan ---

    public function openSendLocModal(string $localCode, string $localName): void
    {
        $this->resetErrorBag();
        $this->positionAction = 'kirim';
        $this->positionLocalCode = $localCode;
        $this->positionLocalName = $localName;
        $this->positionHasPhysType = (bool) HealthcareServiceMap::where('type', 'polyclinic')->where('local_code', $localCode)->value('physical_type_code');
        $this->positionLongitude = $this->positionLatitude = $this->positionAltitude = $this->positionManagingOrg = '';
        $this->showPositionModal = true;
        $this->dispatch('position-modal-opened');
    }

    public function openUpdateLocModal(): void
    {
        if (!$this->selectedLocation) {
            return;
        }

        $pos = $this->selectedLocation->getPosition();
        $this->resetErrorBag();
        $this->positionAction = 'update';
        $this->positionLocalCode = $this->selectedLocation->identifier ?? '';
        $this->positionLocalName = $this->selectedLocation->name;
        $this->positionHasPhysType = (bool) HealthcareServiceMap::where('type', 'polyclinic')->where('local_code', $this->positionLocalCode)->value('physical_type_code');
        $this->positionLongitude = (string) ($pos['longitude'] ?? '');
        $this->positionLatitude = (string) ($pos['latitude'] ?? '');
        $this->positionAltitude = (string) ($pos['altitude'] ?? '');
        $this->positionManagingOrg = $this->selectedLocation->managing_organization ?? '';
        $this->showLocDetailModal = false;
        $this->showPositionModal = true;
        $this->dispatch('position-modal-opened');
    }

    public function savePosition(): void
    {
        $this->validate(
            [
                'positionLongitude' => 'required|numeric',
                'positionLatitude' => 'required|numeric',
                'positionAltitude' => 'required|numeric',
                'positionManagingOrg' => 'required|string',
            ],
            [
                'positionLongitude.required' => 'Longitude wajib diisi.',
                'positionLongitude.numeric' => 'Longitude harus berupa angka.',
                'positionLatitude.required' => 'Latitude wajib diisi.',
                'positionLatitude.numeric' => 'Latitude harus berupa angka.',
                'positionAltitude.required' => 'Altitude wajib diisi.',
                'positionAltitude.numeric' => 'Altitude harus berupa angka.',
                'positionManagingOrg.required' => 'Managing Organization wajib dipilih.',
            ],
        );

        if ($this->positionAction === 'kirim') {
            $this->processSendLoc();
        } else {
            $this->processUpdateLoc();
        }
    }

    private function processSendLoc(): void
    {
        $map = HealthcareServiceMap::where('type', 'polyclinic')->where('local_code', $this->positionLocalCode)->first();
        if (!$map?->physical_type_code) {
            $this->toastError('Petakan Location Physical Type (HL7) terlebih dahulu sebelum mengirim ke Satu Sehat.');
            $this->showPositionModal = false;
            return;
        }

        $existing = SatuSehatLocation::where('identifier', $this->positionLocalCode)->where('type', 'ralan')->first();
        if ($existing?->ihs_number) {
            $this->toastWarning("Lokasi ini sudah memiliki IHS Number: {$existing->ihs_number}.");
            return;
        }

        try {
            $response = app(LocationService::class)->createLocation($this->positionLocalName, $this->positionLocalCode, $this->positionLongitude, $this->positionLatitude, $this->positionAltitude, $this->positionManagingOrg, $map->physical_type_code, $map->physical_type_display ?? $map->physical_type_term);
        } catch (\Exception $e) {
            $this->toastError('Gagal menghubungi Satu Sehat: ' . $e->getMessage());
            return;
        }

        if (!$response->success) {
            $this->toastError('Satu Sehat menolak permintaan: ' . ($response->error ?? 'Kesalahan tidak diketahui.'));
            return;
        }

        SatuSehatLocation::updateOrCreate(
            ['identifier' => $this->positionLocalCode, 'type' => 'ralan'],
            [
                'ihs_number' => $response->resourceId,
                'name' => $this->positionLocalName,
                'status' => 'active',
                'managing_organization' => $this->positionManagingOrg,
                'raw_response' => $response->data,
                'synced_at' => now(),
            ],
        );

        $this->showPositionModal = false;
        $this->reset(['positionLocalCode', 'positionLocalName', 'positionLongitude', 'positionLatitude', 'positionAltitude', 'positionManagingOrg']);
        $this->toastSuccess("Location berhasil dikirim ke Satu Sehat. IHS: {$response->resourceId}");
    }

    private function processUpdateLoc(): void
    {
        $map = HealthcareServiceMap::where('type', 'polyclinic')->where('local_code', $this->positionLocalCode)->first();
        if (!$map?->physical_type_code) {
            $this->toastError('Petakan Location Physical Type (HL7) terlebih dahulu sebelum memperbarui ke Satu Sehat.');
            $this->showPositionModal = false;
            return;
        }

        $loc = SatuSehatLocation::where('identifier', $this->positionLocalCode)->where('type', 'ralan')->first();
        if (!$loc?->ihs_number) {
            $this->toastError('Location tidak ditemukan atau belum memiliki IHS Number.');
            return;
        }

        try {
            $response = app(LocationService::class)->updateLocation($loc->ihs_number, $this->positionLocalName, $this->positionLocalCode, $this->positionLongitude, $this->positionLatitude, $this->positionAltitude, 'active', $this->positionManagingOrg, $map->physical_type_code, $map->physical_type_display ?? $map->physical_type_term);
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
            'status' => $response->data['status'] ?? $loc->status,
            'managing_organization' => $this->positionManagingOrg,
            'raw_response' => $response->data,
            'synced_at' => now(),
        ]);

        $this->showPositionModal = false;
        $this->reset(['positionLocalCode', 'positionLocalName', 'positionLongitude', 'positionLatitude', 'positionAltitude', 'positionManagingOrg']);
        $this->selectedLocation = $loc->fresh()->load('organization');
        $this->showLocDetailModal = true;
        $this->dispatch('detail-modal-opened');
        $this->toastSuccess('Location berhasil diperbarui di Satu Sehat.');
    }

    public function viewLocDetail(string $kdPoli): void
    {
        $this->selectedLocation = SatuSehatLocation::where('identifier', $kdPoli)->where('type', 'ralan')->with('organization')->first();
        $this->showLocDetailModal = true;
        $this->dispatch('detail-modal-opened');
    }

    public function openLocPullModal(string $localCode, string $localName): void
    {
        $this->pullLocCode = $localCode;
        $this->pullLocName = $localName;
        $this->activePullType = 'loc';
        $this->showLocPullModal = true;
    }

    #[On('satusehat-resource-selected')]
    public function onResourceSelected(array $resource): void
    {
        $ihsNumber = $resource['id'] ?? null;
        if (!$ihsNumber) {
            return;
        }

        if ($this->activePullType === 'hs') {
            if (!$this->pullHsPoliId) {
                return;
            }

            SatuSehatHealthcareService::updateOrCreate(
                ['identifier' => $this->pullHsPoliId],
                [
                    'ihs_number' => $ihsNumber,
                    'name' => $resource['name'] ?? $this->pullHsPoliName,
                    'status' => $resource['active'] ?? true ? 'active' : 'inactive',
                    'raw_response' => $resource,
                    'synced_at' => now(),
                ],
            );

            $this->showHsPullModal = false;
            $this->reset(['pullHsPoliId', 'pullHsPoliName', 'activePullType']);
            $this->toastSuccess("HealthcareService berhasil ditarik. IHS: {$ihsNumber}");
            return;
        }

        if ($this->activePullType === 'loc') {
            if (!$this->pullLocCode) {
                return;
            }

            $existingByIhs = SatuSehatLocation::where('ihs_number', $ihsNumber)->first();
            $existingByIdent = SatuSehatLocation::where('identifier', $this->pullLocCode)->where('type', 'ralan')->first();

            if ($existingByIhs && ($existingByIdent === null || $existingByIhs->id !== $existingByIdent->id)) {
                $this->toastError("IHS Number {$ihsNumber} sudah terdaftar untuk '{$existingByIhs->identifier}'.");
                return;
            }

            $managingOrgRef = $resource['managingOrganization']['reference'] ?? '';
            $managingOrgId = $managingOrgRef ? last(explode('/', $managingOrgRef)) : null;

            SatuSehatLocation::updateOrCreate(
                ['identifier' => $this->pullLocCode, 'type' => 'ralan'],
                [
                    'ihs_number' => $ihsNumber,
                    'name' => $resource['name'] ?? '',
                    'status' => $resource['status'] ?? 'active',
                    'managing_organization' => $managingOrgId,
                    'raw_response' => $resource,
                    'synced_at' => now(),
                ],
            );

            $this->showLocPullModal = false;
            $this->reset(['pullLocCode', 'pullLocName', 'activePullType']);
            $this->toastSuccess("Location berhasil ditarik. IHS: {$ihsNumber}");
        }
    }

    public function confirmLocDelete(string $localCode, string $localName): void
    {
        $this->deleteLocCode = $localCode;
        $this->deleteLocName = $localName;
        $this->showLocDeleteModal = true;
    }

    public function deleteSsLocation(): void
    {
        if (!$this->deleteLocCode) {
            return;
        }

        $loc = SatuSehatLocation::where('identifier', $this->deleteLocCode)->where('type', 'ralan')->first();

        if ($loc) {
            if ($loc->ihs_number) {
                try {
                    app(LocationService::class)->updateStatus($loc->ihs_number, 'inactive');
                } catch (\Exception $e) {
                    $this->toastError('Gagal menonaktifkan di Satu Sehat: ' . $e->getMessage());
                    $this->showLocDeleteModal = false;
                    $this->reset(['deleteLocCode', 'deleteLocName']);
                    return;
                }
            }

            if ($this->selectedLocation?->id === $loc->id) {
                $this->selectedLocation = null;
            }

            $loc->delete();
        }

        $this->showLocDeleteModal = false;
        $this->reset(['deleteLocCode', 'deleteLocName']);
        $this->toastSuccess('Location dinonaktifkan di Satu Sehat dan mapping lokal dihapus.');
    }

    public function with(): array
    {
        $allBpjs = BpjsHealthcareService::where('type', 'poliklinik')->pluck('id', 'local_code');
        $allHs = SatuSehatHealthcareService::whereNotNull('identifier')->pluck('ihs_number', 'identifier');
        $allLocRalan = SatuSehatLocation::where('type', 'ralan')->get()->keyBy('identifier');
        $organizations = SatuSehatOrganization::whereNotNull('ihs_number')->get();
        $simrsError = false;

        try {
            $query = Poliklinik::query();

            if ($this->search) {
                $query->where(fn($q) => $q->where('kd_poli', 'like', "%{$this->search}%")->orWhere('nm_poli', 'like', "%{$this->search}%"));
            }

            if ($this->filterBpjs === 'registered') {
                $query->whereIn('kd_poli', $allBpjs->keys());
            } elseif ($this->filterBpjs === 'unregistered') {
                $query->whereNotIn('kd_poli', $allBpjs->keys());
            }

            if ($this->filterSs === 'mapped') {
                $query->whereIn('kd_poli', $allHs->filter()->keys());
            } elseif ($this->filterSs === 'unmapped') {
                $query->whereNotIn('kd_poli', $allHs->filter()->keys());
            }

            $items = $query->orderBy('nm_poli')->paginate($this->perPage);
        } catch (\Exception) {
            $items = new LengthAwarePaginator([], 0, $this->perPage);
            $simrsError = true;
        }

        $poliCodes = $items->pluck('kd_poli')->toArray();
        $physMaps = HealthcareServiceMap::where('type', 'polyclinic')->whereIn('local_code', $poliCodes)->get()->keyBy('local_code');
        $serviceItems = HsServiceItem::where('type', 'polyclinic')->whereIn('local_code', $poliCodes)->get()->groupBy(fn($i) => $i->local_code . ':' . $i->item_type);

        $items->getCollection()->transform(function ($item) use ($physMaps, $serviceItems) {
            $map = $physMaps->get($item->kd_poli);
            $prefix = $item->kd_poli . ':';
            $item->physical_type_code = $map?->physical_type_code;
            $item->physical_type_term = $map?->physical_type_term;
            $item->mapping_categories = $serviceItems->get($prefix . 'service-category', collect());
            $item->mapping_types = $serviceItems->get($prefix . 'service-type', collect());
            $item->mapping_specialties = $serviceItems->get($prefix . 'clinical-speciality', collect());
            $item->mapping_programs = $serviceItems->get($prefix . 'program', collect());
            return $item;
        });

        // Data untuk modal mapping aktif
        $mappingItems = collect();
        $mappingPhysType = null;
        if ($this->mappingPoliCode) {
            $mappingItems = HsServiceItem::where('type', 'polyclinic')->where('local_code', $this->mappingPoliCode)->get()->groupBy('item_type');
            $physMap = HealthcareServiceMap::where('type', 'polyclinic')->where('local_code', $this->mappingPoliCode)->first();
            if ($physMap?->physical_type_code) {
                $mappingPhysType = ['code' => $physMap->physical_type_code, 'term' => $physMap->physical_type_term];
            }
        }

        $totalSimrs = 0;
        try {
            $totalSimrs = Poliklinik::count();
        } catch (\Exception) {
        }

        return [
            'items' => $items,
            'allBpjs' => $allBpjs,
            'allHs' => $allHs,
            'allLocRalan' => $allLocRalan,
            'organizations' => $organizations,
            'totalSimrs' => $totalSimrs,
            'totalBpjs' => $allBpjs->count(),
            'totalHs' => $allHs->filter()->count(),
            'totalLocRalan' => $allLocRalan->filter(fn($l) => $l->ihs_number)->count(),
            'simrsError' => $simrsError,
            'mappingItems' => $mappingItems,
            'mappingPhysType' => $mappingPhysType,
        ];
    }
};
?>

<div>
    <x-ui.page-header title="Healthcare Service — Poliklinik"
        subtitle="UUID BPJS, IHS HealthcareService, dan IHS Location Satu Sehat untuk poliklinik">
        <x-slot:actions>
            <x-atoms.button wire:click="$set('showSyncModal', true)" icon="arrow-path" variant="outline" size="sm">
                Sync BPJS
            </x-atoms.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <x-organisms.stat-card title="Total Poliklinik" :value="number_format($totalSimrs)" icon="building-storefront" color="zinc" />
        <x-organisms.stat-card title="UUID BPJS" :value="number_format($totalBpjs)" icon="identification" color="blue" />
        <x-organisms.stat-card title="IHS HealthcareService" :value="number_format($totalHs)" icon="cube" color="emerald" />
        <x-organisms.stat-card title="IHS Location" :value="number_format($totalLocRalan)" icon="map-pin" color="sky" />
    </div>

    <x-organisms.data-panel>
        <x-slot:filter>
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-[200px]">
                    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                        placeholder="Cari kode atau nama poliklinik..." clearable />
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

        @if ($simrsError)
            <div
                class="mb-4 p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-sm text-red-700 dark:text-red-400">
                Gagal terhubung ke SIMRS. Periksa koneksi database SIMRS.
            </div>
        @endif

        <x-organisms.table>
            <x-slot:headings>
                <x-atoms.table-heading class="w-28">Kode Poli</x-atoms.table-heading>
                <x-atoms.table-heading>Nama Poli</x-atoms.table-heading>
                <x-atoms.table-heading class="w-72">Mapping</x-atoms.table-heading>
                <x-atoms.table-heading class="w-32">UUID BPJS</x-atoms.table-heading>
                <x-atoms.table-heading class="w-32">IHS HS</x-atoms.table-heading>
                <x-atoms.table-heading class="w-32">IHS Location</x-atoms.table-heading>
                <x-atoms.table-heading align="center" class="w-36">Aksi</x-atoms.table-heading>
            </x-slot:headings>

            @forelse ($items as $item)
                @php
                    $hasMapping =
                        $item->mapping_categories->isNotEmpty() ||
                        $item->mapping_types->isNotEmpty() ||
                        $item->mapping_specialties->isNotEmpty() ||
                        $item->mapping_programs->isNotEmpty() ||
                        $item->physical_type_code;
                    $bpjsUuid = $allBpjs[$item->kd_poli] ?? null;
                    $hsIhs = $allHs[$item->kd_poli] ?? null;
                    $loc = $allLocRalan[$item->kd_poli] ?? null;
                @endphp
                <x-molecules.table-row wire:key="poli-{{ $item->kd_poli }}">
                    <x-atoms.table-cell nowrap>
                        <span
                            class="inline-block font-mono text-xs font-bold px-2 py-1 rounded-md bg-zinc-100 dark:bg-primary-dark-700 text-zinc-600 dark:text-primary-dark-300 ring-1 ring-zinc-200 dark:ring-primary-dark-600">
                            {{ $item->kd_poli }}
                        </span>
                    </x-atoms.table-cell>

                    <x-atoms.table-cell>
                        <p class="text-sm font-medium text-zinc-800 dark:text-primary-dark-100">{{ $item->nm_poli }}</p>
                    </x-atoms.table-cell>

                    {{-- Mapping detail --}}
                    <x-atoms.table-cell>
                        @if ($hasMapping)
                            @include('pages::local.healthcare-service.partials._mapping-detail', [
                                'item' => $item,
                            ])
                        @else
                            <span class="text-xs italic text-zinc-400 dark:text-primary-dark-500">Belum
                                di-mapping</span>
                        @endif
                    </x-atoms.table-cell>

                    {{-- UUID BPJS (truncated) --}}
                    <x-atoms.table-cell>
                        @if ($bpjsUuid)
                            <span class="font-mono text-xs text-blue-700 dark:text-blue-400"
                                title="{{ $bpjsUuid }}">{{ substr($bpjsUuid, 0, 8) }}…</span>
                        @else
                            <span class="text-xs italic text-zinc-400 dark:text-primary-dark-500">—</span>
                        @endif
                    </x-atoms.table-cell>

                    {{-- IHS HealthcareService --}}
                    <x-atoms.table-cell>
                        @if ($hsIhs)
                            <span class="font-mono text-xs font-semibold text-emerald-700 dark:text-emerald-400"
                                title="{{ $hsIhs }}">{{ substr($hsIhs, 0, 8) }}…</span>
                        @else
                            <span class="text-xs italic text-zinc-400 dark:text-primary-dark-500">—</span>
                        @endif
                    </x-atoms.table-cell>

                    {{-- IHS Location Ralan --}}
                    <x-atoms.table-cell>
                        @if ($loc?->ihs_number)
                            <span class="font-mono text-xs font-semibold text-sky-700 dark:text-sky-400"
                                title="{{ $loc->ihs_number }}">{{ substr($loc->ihs_number, 0, 8) }}…</span>
                        @else
                            <span class="text-xs italic text-zinc-400 dark:text-primary-dark-500">—</span>
                        @endif
                    </x-atoms.table-cell>

                    {{-- Aksi --}}
                    <x-atoms.table-cell :action="true" align="center" nowrap>
                        <div class="flex items-center justify-center gap-1">
                            {{-- Mapping --}}
                            <x-atoms.button size="sm" variant="ghost"
                                icon="{{ $hasMapping ? 'pencil-square' : 'tag' }}"
                                tooltip="{{ $hasMapping ? 'Edit Mapping' : 'Buat Mapping' }}"
                                wire:click="openMappingModal('{{ $item->kd_poli }}', '{{ addslashes($item->nm_poli) }}')" />

                            <span class="w-px h-4 bg-zinc-200 dark:bg-primary-dark-600 mx-0.5"></span>

                            {{-- BPJS --}}
                            @if ($bpjsUuid)
                                <x-atoms.button size="sm" variant="ghost" icon="trash" class="text-red-500"
                                    tooltip="Hapus UUID BPJS"
                                    wire:click="confirmBpjsDelete('{{ $item->kd_poli }}', '{{ addslashes($item->nm_poli) }}')" />
                            @else
                                <x-atoms.button size="sm" variant="ghost" icon="plus"
                                    tooltip="Generate UUID BPJS"
                                    wire:click="generateBpjsUuid('{{ $item->kd_poli }}', '{{ addslashes($item->nm_poli) }}')" />
                            @endif

                            <span class="w-px h-4 bg-zinc-200 dark:bg-primary-dark-600 mx-0.5"></span>

                            {{-- SS HealthcareService --}}
                            @if ($hsIhs)
                                <x-atoms.button size="sm" variant="ghost" icon="eye" tooltip="Detail HS SS"
                                    wire:click="viewHsDetail('{{ $item->kd_poli }}')" />
                                <x-atoms.button size="sm" variant="ghost" icon="trash" class="text-red-500"
                                    tooltip="Hapus HS SS"
                                    wire:click="confirmHsDelete('{{ $item->kd_poli }}', '{{ addslashes($item->nm_poli) }}')" />
                            @else
                                <x-atoms.button size="sm" variant="ghost" icon="paper-airplane"
                                    tooltip="Kirim HS ke SS"
                                    wire:click="sendHsToSatuSehat('{{ $item->kd_poli }}', '{{ addslashes($item->nm_poli) }}')" />
                                <x-atoms.button size="sm" variant="ghost" icon="arrow-down-tray"
                                    tooltip="Tarik HS dari SS"
                                    wire:click="openHsPullModal('{{ $item->kd_poli }}', '{{ addslashes($item->nm_poli) }}')" />
                            @endif

                            <span class="w-px h-4 bg-zinc-200 dark:bg-primary-dark-600 mx-0.5"></span>

                            {{-- SS Location --}}
                            @if ($loc?->ihs_number)
                                <x-atoms.button size="sm" variant="ghost" icon="eye"
                                    tooltip="Detail Location SS"
                                    wire:click="viewLocDetail('{{ $item->kd_poli }}')" />
                                <x-atoms.button size="sm" variant="ghost" icon="trash" class="text-red-500"
                                    tooltip="Hapus Location SS"
                                    wire:click="confirmLocDelete('{{ $item->kd_poli }}', '{{ addslashes($item->nm_poli) }}')" />
                            @else
                                <x-atoms.button size="sm" variant="ghost" icon="map-pin"
                                    tooltip="Kirim Location ke SS"
                                    wire:click="openSendLocModal('{{ $item->kd_poli }}', '{{ addslashes($item->nm_poli) }}')" />
                                <x-atoms.button size="sm" variant="ghost" icon="arrow-down-tray"
                                    tooltip="Tarik Location dari SS"
                                    wire:click="openLocPullModal('{{ $item->kd_poli }}', '{{ addslashes($item->nm_poli) }}')" />
                            @endif
                        </div>
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @empty
                <tr>
                    <td colspan="7" class="px-5 py-16 text-center">
                        <div class="flex flex-col items-center gap-3">
                            <div
                                class="flex items-center justify-center w-14 h-14 rounded-2xl bg-zinc-100 dark:bg-primary-dark-700">
                                <flux:icon name="building-storefront"
                                    class="w-7 h-7 text-zinc-300 dark:text-primary-dark-500" />
                            </div>
                            <p class="text-sm font-semibold text-zinc-500 dark:text-primary-dark-400">Tidak ada data
                                poliklinik</p>
                        </div>
                    </td>
                </tr>
            @endforelse
        </x-organisms.table>

        @if ($items->hasPages())
            <x-slot:footer>
                {{ $items->links() }}
            </x-slot:footer>
        @endif
    </x-organisms.data-panel>

    {{-- Modal: Mapping HealthcareService --}}
    <x-organisms.modal wire:model="showMappingModal" title="Mapping HealthcareService" maxWidth="2xl">
        <div class="space-y-5">
            @if ($mappingPoliCode)
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    <span class="font-semibold text-zinc-700 dark:text-zinc-200">{{ $mappingPoliName }}</span>
                    <span class="font-mono text-xs text-zinc-400 ml-1">({{ $mappingPoliCode }})</span>
                </p>
            @endif

            @include('pages::local.healthcare-service.partials._mapping-section', [
                'label' => 'Service Category',
                'source' => 'HL7',
                'color' => 'emerald',
                'items' => $mappingItems->get('service-category', collect()),
                'addType' => 'service-category',
            ])

            @include('pages::local.healthcare-service.partials._mapping-section', [
                'label' => 'Service Type',
                'source' => 'HL7',
                'color' => 'sky',
                'items' => $mappingItems->get('service-type', collect()),
                'addType' => 'service-type',
            ])

            @include('pages::local.healthcare-service.partials._mapping-section', [
                'label' => 'Clinical Speciality',
                'source' => 'Satu Sehat',
                'color' => 'violet',
                'items' => $mappingItems->get('clinical-speciality', collect()),
                'addType' => 'clinical-speciality',
            ])

            @include('pages::local.healthcare-service.partials._mapping-section', [
                'label' => 'Program',
                'source' => 'Satu Sehat',
                'color' => 'yellow',
                'items' => $mappingItems->get('program', collect()),
                'addType' => 'program',
            ])

            {{-- Physical Type (HL7, 1-1) --}}
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <p class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-200">Physical Type</p>
                        <flux:badge size="sm" color="zinc">HL7</flux:badge>
                    </div>
                    <x-atoms.button size="sm" icon="{{ $mappingPhysType ? 'pencil-square' : 'plus' }}"
                        variant="ghost" class="text-zinc-500" wire:click="openAddItemModal('physical-type')">
                        {{ $mappingPhysType ? 'Ubah' : 'Tambah' }}
                    </x-atoms.button>
                </div>
                @if ($mappingPhysType)
                    <div
                        class="flex items-center justify-between px-3 py-2 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/40 border border-zinc-100 dark:border-primary-dark-700">
                        <div>
                            <span
                                class="font-mono text-xs font-bold text-zinc-700 dark:text-primary-dark-200">{{ $mappingPhysType['code'] }}</span>
                            <span
                                class="ml-2 text-xs text-zinc-500 dark:text-primary-dark-400">{{ $mappingPhysType['term'] }}</span>
                        </div>
                        <x-atoms.button size="sm" icon="trash" variant="ghost" class="text-red-500"
                            wire:click="removePhysicalType" />
                    </div>
                @else
                    <p class="text-xs italic text-zinc-400 dark:text-primary-dark-500 py-1">Belum dipetakan.</p>
                @endif
            </div>
        </div>
        <x-slot name="footer">
            <div class="flex justify-between items-center">
                @if ($mappingPoliCode && ($mappingItems->isNotEmpty() || $mappingPhysType))
                    <x-atoms.button size="sm" icon="trash" variant="ghost" class="text-red-500"
                        wire:click="confirmDeleteMapping('{{ $mappingPoliCode }}', '{{ addslashes($mappingPoliName) }}')">
                        Hapus Semua
                    </x-atoms.button>
                @else
                    <div></div>
                @endif
                <x-atoms.button variant="ghost" wire:click="$set('showMappingModal', false)">Tutup</x-atoms.button>
            </div>
        </x-slot>
    </x-organisms.modal>

    {{-- Modal: Sub-Pencarian Code System --}}
    <x-organisms.modal wire:model="showFhirSearchModal" :title="$addingItemType === 'clinical-speciality' ? 'Pilih Clinical Speciality' : ($addingItemType === 'program' ? 'Pilih Program' : ($addingItemType === 'service-category' ? 'Pilih Service Category' : ($addingItemType === 'service-type' ? 'Pilih Service Type' : 'Pilih Location Physical Type')))" maxWidth="3xl">
        @if ($mappingPoliName)
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mb-3">
                Untuk: <span class="font-semibold text-zinc-700 dark:text-zinc-200">{{ $mappingPoliName }}</span>
            </p>
        @endif
        @if ($addingItemType)
            <livewire:components.fhir-codesystem-search :defaultType="$addingItemType === 'physical-type' ? 'location-physical-type' : $addingItemType" :limitTypes="$addingItemType === 'physical-type' ? ['location-physical-type'] : [$addingItemType]" :key="'fhir-search-' . $addingItemType . '-' . ($mappingPoliCode ?? '')" />
        @endif
        <x-slot name="footer">
            <div class="flex justify-end">
                <x-atoms.button variant="ghost" wire:click="$set('showFhirSearchModal', false)">Tutup</x-atoms.button>
            </div>
        </x-slot>
    </x-organisms.modal>

    {{-- Modal: Konfirmasi Hapus Semua Mapping --}}
    <x-organisms.modal wire:model="showDeleteMappingModal" title="Hapus Semua Mapping?"
        description="Semua mapping untuk poliklinik ini akan dihapus." maxWidth="sm">
        <div class="space-y-4">
            <div
                class="p-4 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/40 border border-zinc-200 dark:border-primary-dark-700 space-y-2">
                <div class="flex items-center gap-3">
                    <span class="text-xs font-medium text-zinc-400 w-16 shrink-0">Kode</span>
                    <span
                        class="font-mono text-sm font-bold text-primary-600 dark:text-primary-400">{{ $deleteMappingCode }}</span>
                </div>
                <div class="flex items-start gap-3">
                    <span class="text-xs font-medium text-zinc-400 w-16 shrink-0 mt-0.5">Nama</span>
                    <span class="text-sm text-zinc-700 dark:text-primary-dark-300">{{ $deleteMappingName }}</span>
                </div>
            </div>
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-atoms.button wire:click="$set('showDeleteMappingModal', false)"
                    variant="ghost">Batal</x-atoms.button>
                <x-atoms.button wire:click="deleteMapping" variant="danger" icon="trash">Hapus
                    Semua</x-atoms.button>
            </div>
        </x-slot>
    </x-organisms.modal>

    {{-- Modal: Sync BPJS --}}
    <x-organisms.modal wire:model="showSyncModal" title="Sync UUID BPJS Poliklinik"
        description="Proses dijalankan di background queue." maxWidth="sm">
        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-atoms.button wire:click="$set('showSyncModal', false)" variant="ghost">Batal</x-atoms.button>
                <x-atoms.button wire:click="syncAllBpjs" variant="primary" icon="arrow-path">Sync
                    Sekarang</x-atoms.button>
            </div>
        </x-slot>
    </x-organisms.modal>

    {{-- Modal: Hapus UUID BPJS --}}
    <x-organisms.modal wire:model="showBpjsDeleteModal" title="Hapus UUID BPJS"
        description="Tindakan ini tidak dapat dibatalkan." maxWidth="sm">
        <div
            class="p-4 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/40 border border-zinc-200 dark:border-primary-dark-700 space-y-2">
            <p class="text-xs font-medium text-zinc-500">Kode: <span
                    class="font-mono font-bold text-zinc-700 dark:text-primary-dark-200">{{ $deleteBpjsCode }}</span>
            </p>
            <p class="text-xs font-medium text-zinc-500">Nama: <span
                    class="text-zinc-700 dark:text-primary-dark-200">{{ $deleteBpjsName }}</span></p>
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-atoms.button wire:click="$set('showBpjsDeleteModal', false)" variant="ghost">Batal</x-atoms.button>
                <x-atoms.button wire:click="deleteBpjsUuid" variant="danger" icon="trash">Hapus
                    UUID</x-atoms.button>
            </div>
        </x-slot>
    </x-organisms.modal>

    {{-- Modal: Detail SS HealthcareService --}}
    <x-organisms.modal wire:model="showHsDetailModal" :title="$selectedService?->name ?? 'Detail HealthcareService'" maxWidth="lg">
        @if ($selectedService)
            <div
                class="p-4 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/40 border border-zinc-200 dark:border-primary-dark-700 space-y-3">
                <div class="flex items-center gap-3">
                    <span class="text-xs font-medium text-zinc-400 w-20 shrink-0">Identifier</span>
                    <span
                        class="font-mono text-sm text-zinc-700 dark:text-primary-dark-200">{{ $selectedService->identifier }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs font-medium text-zinc-400 w-20 shrink-0">IHS Number</span>
                    <span
                        class="font-mono text-sm font-bold text-emerald-700 dark:text-emerald-400">{{ $selectedService->ihs_number }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs font-medium text-zinc-400 w-20 shrink-0">Status</span>
                    <span
                        class="text-xs px-2 py-0.5 rounded-full {{ $selectedService->status === 'active' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-zinc-100 text-zinc-600' }}">
                        {{ $selectedService->status ?? '-' }}
                    </span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs font-medium text-zinc-400 w-20 shrink-0">Synced At</span>
                    <span
                        class="text-sm text-zinc-600 dark:text-primary-dark-300">{{ $selectedService->synced_at?->format('d/m/Y H:i') ?? '-' }}</span>
                </div>
            </div>
        @endif
        <x-slot name="footer">
            <div class="flex justify-between items-center">
                <x-atoms.button wire:click="updateHsService" icon="arrow-path">Update SS</x-atoms.button>
                <x-atoms.button variant="ghost" wire:click="$set('showHsDetailModal', false)">Tutup</x-atoms.button>
            </div>
        </x-slot>
    </x-organisms.modal>

    {{-- Modal: Hapus SS HealthcareService --}}
    <x-organisms.modal wire:model="showHsDeleteModal" title="Hapus SS HealthcareService"
        description="Resource akan dinonaktifkan di Satu Sehat." maxWidth="sm">
        <div
            class="p-4 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/40 border border-zinc-200 dark:border-primary-dark-700">
            <p class="text-sm text-zinc-700 dark:text-primary-dark-200">{{ $deleteHsPoliName }}</p>
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-atoms.button wire:click="$set('showHsDeleteModal', false)" variant="ghost">Batal</x-atoms.button>
                <x-atoms.button wire:click="deleteHsMapping" variant="danger" icon="trash">Hapus</x-atoms.button>
            </div>
        </x-slot>
    </x-organisms.modal>

    {{-- Modal: Tarik SS HealthcareService --}}
    <x-organisms.modal wire:model="showHsPullModal" title="Tarik HealthcareService dari Satu Sehat" maxWidth="xl">
        @if ($pullHsPoliId)
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mb-3">
                Poliklinik: <span class="font-semibold text-zinc-700 dark:text-zinc-200">{{ $pullHsPoliName }}</span>
                <span class="font-mono text-xs text-zinc-400">({{ $pullHsPoliId }})</span>
            </p>
        @endif
        <livewire:components.satusehat-resource-search :serviceClass="\App\Services\SatuSehat\Resources\HealthcareServiceService::class" resourceLabel="HealthcareService"
            :initialSearch="$pullHsPoliName" :key="'hs-pull-' . $pullHsPoliId" />
        <x-slot name="footer">
            <div class="flex justify-end">
                <x-atoms.button wire:click="$set('showHsPullModal', false)" variant="ghost">Batal</x-atoms.button>
            </div>
        </x-slot>
    </x-organisms.modal>

    {{-- Modal: Input Posisi SS Location Ralan --}}
    <x-organisms.modal wire:model="showPositionModal" :title="$positionAction === 'kirim' ? 'Kirim Location ke Satu Sehat' : 'Update Location ke Satu Sehat'" maxWidth="4xl">
        <div class="space-y-4">
            @if ($positionLocalName)
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    Poliklinik: <span
                        class="font-semibold text-zinc-700 dark:text-zinc-200">{{ $positionLocalName }}</span>
                    @if ($positionLocalCode)
                        <span class="font-mono text-xs text-zinc-400">({{ $positionLocalCode }})</span>
                    @endif
                </p>
            @endif

            @if (!$positionHasPhysType)
                <div
                    class="flex items-start gap-3 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-xs text-amber-700 dark:text-amber-300">
                    <flux:icon name="exclamation-triangle" class="w-4 h-4 shrink-0 mt-0.5" />
                    <p>Location Physical Type belum dipetakan untuk poliklinik ini. Petakan terlebih dahulu agar
                        pengiriman ke Satu Sehat berhasil.</p>
                </div>
            @endif

            @if ($organizations->isEmpty())
                <div
                    class="flex items-start gap-3 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-xs text-amber-700 dark:text-amber-300">
                    <flux:icon name="exclamation-triangle" class="w-4 h-4 shrink-0 mt-0.5" />
                    <p>Belum ada Organization yang terdaftar di Satu Sehat. Petakan minimal satu Organization terlebih
                        dahulu.</p>
                </div>
            @endif

            <div x-data="positionMapPoli()" @position-modal-opened.window="setTimeout(() => initMap(), 300)">
                <div wire:ignore>
                    <div x-ref="mapEl"
                        class="h-64 w-full rounded-xl border border-zinc-200 dark:border-primary-dark-700 overflow-hidden"
                        style="z-index: 0;"></div>
                    <p class="mt-1.5 text-xs text-center text-zinc-400 dark:text-primary-dark-500">
                        Klik pada peta untuk menentukan posisi — marker dapat digeser untuk penyesuaian
                    </p>
                </div>

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

                <div class="flex justify-end mt-1">
                    <x-atoms.button @click="updatePin()" type="button"
                        class="flex items-center gap-1.5 text-xs text-zinc-400 hover:text-zinc-600 dark:hover:text-primary-dark-300 transition-colors px-2 py-1 rounded-md hover:bg-zinc-100 dark:hover:bg-primary-dark-700">
                        <flux:icon name="map-pin" class="w-3.5 h-3.5" />
                        Perbarui pin dari koordinat
                    </x-atoms.button>
                </div>
            </div>

            <div class="space-y-3">
                <div
                    class="flex items-center gap-2 px-3 py-2 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/40 border border-zinc-200 dark:border-primary-dark-700 text-xs text-zinc-500 dark:text-primary-dark-400">
                    <flux:icon name="information-circle" class="w-4 h-4 shrink-0" />
                    Tipe lokasi otomatis: <span class="font-semibold ml-1">Rawat Jalan (ralan)</span>
                </div>
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
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-atoms.button variant="ghost" wire:click="$set('showPositionModal', false)">Batal</x-atoms.button>
                <x-atoms.button variant="primary" wire:click="savePosition" icon="paper-airplane" :disabled="$organizations->isEmpty()">
                    <span wire:loading.remove wire:target="savePosition">
                        {{ $positionAction === 'kirim' ? 'Kirim ke Satu Sehat' : 'Update ke Satu Sehat' }}
                    </span>
                    <span wire:loading wire:target="savePosition">Memproses...</span>
                </x-atoms.button>
            </div>
        </x-slot>
    </x-organisms.modal>

    {{-- Modal: Detail SS Location Ralan --}}
    <x-organisms.modal wire:model="showLocDetailModal" :title="$selectedLocation?->name ?? 'Detail Location'" maxWidth="4xl">
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
                <div class="flex flex-wrap items-center gap-2">
                    <flux:badge :color="$stColor" size="sm">{{ $stLabel }}</flux:badge>
                    @if ($physType)
                        <flux:badge color="zinc" size="sm">{{ $physLabels[$physType] ?? $physType }}
                        </flux:badge>
                    @endif
                </div>

                <div>
                    <p
                        class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-primary-dark-500">
                        Identitas</p>
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                        <div>
                            <dt class="text-xs text-zinc-500 dark:text-primary-dark-400">IHS Number</dt>
                            <dd class="mt-0.5 font-mono font-bold text-sky-700 dark:text-sky-400">
                                {{ $selectedLocation->ihs_number }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-500 dark:text-primary-dark-400">Identifier</dt>
                            <dd class="mt-0.5 font-mono text-zinc-700 dark:text-primary-dark-300">
                                {{ $selectedLocation->identifier ?? '-' }}</dd>
                        </div>
                    </dl>
                </div>

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

                @if (!empty($pos['latitude']) && !empty($pos['longitude']))
                    <div>
                        <p
                            class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-primary-dark-500">
                            Peta Posisi</p>
                        <div x-data="detailMapPoli({{ $pos['latitude'] }}, {{ $pos['longitude'] }})" @detail-modal-opened.window="setTimeout(() => initMap(), 300)">
                            <div wire:ignore>
                                <div x-ref="detailMapEl"
                                    class="h-48 w-full rounded-xl border border-zinc-200 dark:border-primary-dark-700 overflow-hidden"
                                    style="z-index: 0;"></div>
                            </div>
                        </div>
                    </div>
                @endif

                <div>
                    <p
                        class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-primary-dark-500">
                        Managing Organization</p>
                    <dd class="font-mono text-sm text-zinc-700 dark:text-primary-dark-300">
                        {{ $selectedLocation->managing_organization ?? '-' }}
                        @if ($selectedLocation->organization)
                            <span class="font-sans text-zinc-500"> —
                                {{ $selectedLocation->organization->name }}</span>
                        @endif
                    </dd>
                </div>

                @php
                    $detailPhone = $selectedLocation->getTelecom('phone');
                    $detailEmail = $selectedLocation->getTelecom('email');
                    $detailWebsite = $selectedLocation->getTelecom('url');
                @endphp
                @if ($detailPhone || $detailEmail || $detailWebsite)
                    <div>
                        <p
                            class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-primary-dark-500">
                            Kontak</p>
                        <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                            <div>
                                <dt class="text-xs text-zinc-500 dark:text-primary-dark-400">Telepon</dt>
                                <dd class="mt-0.5 text-zinc-700 dark:text-primary-dark-300">{{ $detailPhone ?? '-' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-zinc-500 dark:text-primary-dark-400">Email</dt>
                                <dd class="mt-0.5 text-zinc-700 dark:text-primary-dark-300 truncate">
                                    {{ $detailEmail ?? '-' }}</dd>
                            </div>
                            <div class="col-span-2">
                                <dt class="text-xs text-zinc-500 dark:text-primary-dark-400">Website</dt>
                                <dd class="mt-0.5 text-zinc-700 dark:text-primary-dark-300 truncate">
                                    {{ $detailWebsite ?? '-' }}</dd>
                            </div>
                        </dl>
                    </div>
                @endif

                <div class="text-xs text-zinc-400 dark:text-primary-dark-500">
                    Disinkron: {{ $selectedLocation->synced_at?->format('d/m/Y H:i') ?? '-' }}
                </div>

                @if ($selectedLocation->raw_response)
                    <details class="group">
                        <summary
                            class="cursor-pointer text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-primary-dark-500 hover:text-zinc-600 dark:hover:text-primary-dark-300 transition-colors select-none">
                            <span class="group-open:hidden">Lihat FHIR Resource</span>
                            <span class="hidden group-open:inline">Sembunyikan</span>
                        </summary>
                        <x-atoms.code-block language="json" maxHeight="max-h-52"
                            class="mt-2">{{ json_encode($selectedLocation->raw_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</x-atoms.code-block>
                    </details>
                @endif
            </div>
        @endif

        <x-slot name="footer">
            <div class="flex justify-between items-center">
                <x-atoms.button wire:click="openUpdateLocModal" variant="primary" icon="arrow-path">
                    Update ke SS
                </x-atoms.button>
                <x-atoms.button variant="ghost" wire:click="$set('showLocDetailModal', false)">Tutup</x-atoms.button>
            </div>
        </x-slot>
    </x-organisms.modal>



    {{-- Modal: Tarik SS Location Ralan --}}
    <x-organisms.modal wire:model="showLocPullModal" title="Tarik Location dari Satu Sehat" maxWidth="xl">
        @if ($pullLocCode)
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mb-3">
                Poliklinik: <span class="font-semibold text-zinc-700 dark:text-zinc-200">{{ $pullLocName }}</span>
                <span class="font-mono text-xs text-zinc-400">({{ $pullLocCode }})</span>
            </p>
        @endif
        <livewire:components.satusehat-resource-search :serviceClass="\App\Services\SatuSehat\Resources\LocationService::class" resourceLabel="Location" :initialSearch="$pullLocName"
            :key="'loc-pull-' . $pullLocCode" />
        <x-slot name="footer">
            <div class="flex justify-end">
                <x-atoms.button variant="ghost" wire:click="$set('showLocPullModal', false)">Batal</x-atoms.button>
            </div>
        </x-slot>
    </x-organisms.modal>

    {{-- Modal: Hapus SS Location Ralan --}}
    <x-organisms.modal wire:model="showLocDeleteModal" title="Hapus Mapping?" maxWidth="sm">
        <div class="space-y-4">
            <p class="text-sm text-zinc-600 dark:text-primary-dark-300">
                Location untuk <strong>{{ $deleteLocName }}</strong> akan dihapus.
            </p>
            <div
                class="p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-xs text-amber-700 dark:text-amber-300">
                <flux:icon name="exclamation-triangle" class="inline w-3.5 h-3.5 mr-1" />
                Location akan dinonaktifkan di Satu Sehat, kemudian mapping lokal dihapus.
            </div>
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-atoms.button variant="ghost" wire:click="$set('showLocDeleteModal', false)">Batal</x-atoms.button>
                <x-atoms.button variant="danger" wire:click="deleteSsLocation" icon="trash">
                    <span wire:loading.remove wire:target="deleteSsLocation">Hapus</span>
                    <span wire:loading wire:target="deleteSsLocation">Menghapus...</span>
                </x-atoms.button>
            </div>
        </x-slot>
    </x-organisms.modal>
</div>

@pushOnce('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" />
@endPushOnce

@pushOnce('scripts')
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        function positionMapPoli() {
            return {
                map: null,
                marker: null,

                initMap() {
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

                    if (hasCoords) {
                        this.buildMap(lat, lng, 17);
                        this.setMarker(lat, lng);
                        return;
                    }

                    if (navigator.geolocation) {
                        navigator.geolocation.getCurrentPosition(
                            (pos) => {
                                this.buildMap(pos.coords.latitude, pos.coords.longitude, 17);
                            },
                            () => {
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
                            `https://api.open-meteo.com/v1/elevation?latitude=${lat}&longitude=${lng}`);
                        const data = await res.json();
                        const altitude = data.elevation?.[0];
                        if (altitude !== undefined && altitude !== null) {
                            this.$wire.set('positionAltitude', String(Math.round(altitude)));
                        }
                    } catch {
                        /* biarkan user isi manual */
                    }
                },

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

        function detailMapPoli(lat, lng) {
            return {
                map: null,
                lat,
                lng,

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
