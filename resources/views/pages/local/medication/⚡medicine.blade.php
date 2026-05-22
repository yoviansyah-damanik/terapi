<?php

use App\Jobs\SyncBpjsMedicationsJob;
use App\Models\Bpjs\BpjsMedication;
use App\Models\FhirDictionary;
use App\Models\Mapping\MedicationMap;
use App\Models\SatuSehat\SatuSehatMedication;
use App\Models\Simrs\DataBarang;
use App\Services\SatuSehat\Resources\MedicationService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app')] #[Title('Obat — Mapping & UUID')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterSs = '';

    public int $perPage = 25;

    // BPJS UUID
    public bool $showSyncModal = false;
    public bool $showBpjsDetailModal = false;
    public ?BpjsMedication $selectedBpjsItem = null;
    public bool $showDeleteBpjsModal = false;
    public ?string $deleteBpjsCode = null;
    public string $deleteBpjsName = '';

    // Satu Sehat detail
    public bool $showSsDetailModal = false;
    public ?SatuSehatMedication $selectedSs = null;

    // Modal pemilihan KFA
    public bool $showModal = false;
    public ?string $selectedCode = null;
    public ?string $selectedName = null;
    public string $kfaTypeHint = 'farmasi';

    // Payload form mapping
    public ?string $selectedKfaCode = null;
    public ?string $selectedKfaName = null;
    public ?string $selectedSystemUrl = null;
    public ?array $kfa_payload = null;
    public ?string $form_code = null;
    public ?string $route_code = null;
    public ?string $numerator_code = null;
    public ?string $denominator_code = null;
    public ?string $controlled_drug_code = null;
    public ?string $medication_type_code = null;

    // Tambah dictionary modal
    public bool $showDictionaryModal = false;
    public string $dictTitle = '';
    public string $dictSource = '';
    public string $dictType = '';
    public string $dictSystemCode = '';
    public string $dictSystemTerm = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }
    public function updatedFilterSs(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $ssMap = SatuSehatMedication::pluck('ihs_number', 'kfa_code');

        // Dapatkan local_codes yang sudah terkirim ke SS
        $syncedLocalCodes = MedicationMap::whereIn('kfa_code', $ssMap->filter()->keys()->toArray())
            ->pluck('local_code')
            ->toArray();

        $query = DataBarang::query()
            ->active()
            ->with(['industriFarmasi', 'kategoriBarang', 'golonganBarang', 'satuanKecil', 'satuanBesar', 'jenis'])
            ->whereDoesntHave('kategoriBarang', fn($q) => $q->where('nama', 'like', '%vaksin%'))
            ->when($this->search, fn($q) => $q->where(fn($sq) => $sq->where('kode_brng', 'like', "%{$this->search}%")->orWhere('nama_brng', 'like', "%{$this->search}%")))
            ->orderBy('nama_brng');

        $mappedLocalCodes = MedicationMap::whereNotNull('kfa_code')->pluck('local_code')->toArray();

        if ($this->filterSs === 'terdaftar') {
            $query->whereIn('kode_brng', $syncedLocalCodes);
        } elseif ($this->filterSs === 'belum') {
            $query->whereNotIn('kode_brng', $syncedLocalCodes);
        } elseif ($this->filterSs === 'mapped_belum_kirim') {
            $query->whereIn('kode_brng', $mappedLocalCodes)->whereNotIn('kode_brng', $syncedLocalCodes);
        }

        $items = $query->paginate($this->perPage);

        $codes = $items->pluck('kode_brng')->toArray();
        $mappings = MedicationMap::whereIn('local_code', $codes)->get()->keyBy('local_code');

        $bpjsRegistered = BpjsMedication::pluck('id', 'local_code');

        $items->getCollection()->transform(function ($item) use ($mappings, $bpjsRegistered, $ssMap) {
            $map = $mappings->get($item->kode_brng);
            $item->kfa_code = $map->kfa_code ?? null;
            $item->kfa_name = $map->kfa_name ?? null;
            $item->system_url = $map->system_url ?? null;
            $item->form_name = $map->form_name ?? null;
            $item->route_name = $map->route_name ?? null;
            $item->numerator_code = $map->numerator_code ?? null;
            $item->denominator_code = $map->denominator_code ?? null;
            $item->controlled_drug_name = $map->controlled_drug_name ?? null;
            $item->bpjs_uuid = $bpjsRegistered->get($item->kode_brng);
            $item->ihs_number = $item->kfa_code ? $ssMap[$item->kfa_code] ?? null : null;
            return $item;
        });

        $totalSimrs = $query->count();
        $totalBpjs = BpjsMedication::count();
        $totalKfa = MedicationMap::count();
        $totalSs = SatuSehatMedication::count();

        return [
            'items' => $items,
            'totalSimrs' => $totalSimrs,
            'totalKfa' => $totalKfa,
            'totalBpjs' => $totalBpjs,
            'totalSs' => $totalSs,
            'unsyncedBpjs' => max(0, $totalSimrs - $totalBpjs),
        ];
    }

    // ── BPJS UUID actions ───────────────────────────────────────────────────

    public function generateBpjsUuid(string $code, string $name): void
    {
        if (BpjsMedication::where('local_code', $code)->exists()) {
            $this->toastWarning('Obat ini sudah memiliki UUID BPJS.');
            return;
        }
        BpjsMedication::create(['local_code' => $code, 'name' => $name]);
        $this->toastSuccess("UUID BPJS berhasil di-generate untuk {$name}.");
    }

    public function syncAllBpjs(): void
    {
        SyncBpjsMedicationsJob::dispatch();
        $this->showSyncModal = false;
        $this->toastSuccess('Sync semua UUID BPJS dijadwalkan. Proses berjalan di background.');
    }

    public function viewBpjsDetail(string $code): void
    {
        $this->selectedBpjsItem = BpjsMedication::where('local_code', $code)->first();
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
        BpjsMedication::where('local_code', $this->deleteBpjsCode)->delete();
        $this->showDeleteBpjsModal = false;
        $this->reset(['deleteBpjsCode', 'deleteBpjsName']);
        $this->toastSuccess('UUID BPJS berhasil dihapus.');
    }

    // ── Satu Sehat Medication actions ────────────────────────────────────────

    public function sendToSatuSehat(string $localCode): void
    {
        $map = MedicationMap::where('local_code', $localCode)->first();
        if (!$map || !$map->kfa_code) {
            $this->toastError('Obat belum memiliki mapping KFA');
            return;
        }

        try {
            $service = app(MedicationService::class);

            $form = $map->form_code ? ['coding' => [['system' => 'http://terminology.kemkes.go.id/CodeSystem/medication-form', 'code' => $map->form_code, 'display' => $map->form_name]]] : null;

            $ingredient =
                $map->numerator_code && $map->denominator_code
                    ? [
                        [
                            'itemCodeableConcept' => ['coding' => [['system' => $map->system_url ?? 'http://sys-ids.kemkes.go.id/kfa', 'code' => $map->kfa_code, 'display' => $map->kfa_name]]],
                            'strength' => [
                                'numerator' => ['value' => 1, 'system' => 'http://unitsofmeasure.org', 'code' => $map->numerator_code, 'unit' => $map->numerator_name ?? $map->numerator_code],
                                'denominator' => ['value' => 1, 'system' => 'http://terminology.hl7.org/CodeSystem/v3-orderableDrugForm', 'code' => $map->denominator_code, 'unit' => $map->denominator_name ?? $map->denominator_code],
                            ],
                        ],
                    ]
                    : null;

            $extension = [
                [
                    'url' => 'https://fhir.kemkes.go.id/r4/StructureDefinition/MedicationType',
                    'valueCodeableConcept' => ['coding' => [['system' => 'http://terminology.kemkes.go.id/CodeSystem/medication-type', 'code' => 'NC', 'display' => 'Non-compound']]],
                ],
            ];

            $existing = SatuSehatMedication::findByKfaCode($map->kfa_code);

            if ($existing?->ihs_number) {
                $payload = [
                    'id' => $existing->ihs_number,
                    'status' => 'active',
                    'code' => ['coding' => [['system' => $map->system_url ?? 'http://sys-ids.kemkes.go.id/kfa', 'code' => $map->kfa_code, 'display' => $map->kfa_name]]],
                ];
                if ($form) {
                    $payload['form'] = $form;
                }
                if ($ingredient) {
                    $payload['ingredient'] = $ingredient;
                }
                if ($extension) {
                    $payload['extension'] = $extension;
                }
                $result = $service->update($existing->ihs_number, $payload);
            } else {
                $result = $service->createMedication(kfaCode: $map->kfa_code, kfaDisplay: $map->kfa_name, identifier: $map->local_code, status: 'active', form: $form, ingredient: $ingredient, extension: $extension);
            }

            if (!$result->success) {
                $this->toastError("Gagal kirim Medication (HTTP {$result->statusCode})");
                return;
            }

            $data = $result->data ?? [];
            SatuSehatMedication::updateOrCreate(
                ['kfa_code' => $map->kfa_code],
                [
                    'ihs_number' => $data['id'] ?? $result->resourceId,
                    'identifier' => $data['identifier'][0]['value'] ?? $map->local_code,
                    'kfa_display' => $map->kfa_name,
                    'status' => $data['status'] ?? 'active',
                    'form_code' => $map->form_code,
                    'form_display' => $map->form_name,
                    'medication_type' => str_contains($map->system_url ?? '', 'kfa-v3') ? 'Alkes' : 'Farmasi',
                    'raw_response' => $data,
                    'synced_at' => now(),
                ],
            );

            $this->toastSuccess('Medication berhasil dikirim ke Satu Sehat');
        } catch (\Exception $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    public function showSsDetail(string $kfaCode): void
    {
        $this->selectedSs = SatuSehatMedication::where('kfa_code', $kfaCode)->first();
        $this->showSsDetailModal = true;
    }

    // ── KFA Mapping actions ──────────────────────────────────────────────────

    public function openModal(string $code, string $name): void
    {
        $barang = \App\Models\Simrs\DataBarang::with('industriFarmasi')->where('kode_brng', $code)->first();

        $industri = $barang?->industriFarmasi?->nama_industri;
        $industriSuffix = $industri && $industri !== '-' ? ' ' . $industri : '';

        $this->selectedCode = $code;
        $this->selectedName = $name . $industriSuffix;

        $existing = MedicationMap::where('local_code', $code)->first();
        $this->kfaTypeHint = $existing && str_contains($existing->system_url ?? '', 'kfa-v3') ? 'alkes' : 'farmasi';
        $this->selectedKfaCode = $existing->kfa_code ?? null;
        $this->selectedKfaName = $existing->kfa_name ?? null;
        $this->selectedSystemUrl = $existing->system_url ?? null;
        $this->kfa_payload = $existing->kfa_payload ?? null;
        $this->form_code = $existing->form_code ?? null;
        $this->route_code = $existing->route_code ?? null;
        $this->numerator_code = $existing->numerator_code ?? null;
        $this->denominator_code = $existing->denominator_code ?? null;
        $this->controlled_drug_code = $existing->controlled_drug_code ?? null;
        $this->medication_type_code = $existing->medication_type_code ?? 'NC';

        $this->showModal = true;
    }

    #[On('kfa-selected')]
    public function setKfaSelection(string $kfa_code, string $name, string $kfa_type, string $system_url, array $payload = []): void
    {
        $this->selectedKfaCode = $kfa_code;
        $this->selectedKfaName = $name;
        $this->selectedSystemUrl = $system_url;
        $this->kfa_payload = $payload;

        if (isset($payload['dosage_form']['code'], $payload['dosage_form']['name'])) {
            $code = $payload['dosage_form']['code'];
            $text = $payload['dosage_form']['name'];
            FhirDictionary::registerDefault('medication-form', $code, $text, 'kemkes', \App\Services\SatuSehat\FhirDictionary::KEMKES_TERMINOLOGY . '/medication-form');
            $this->form_code = $code;
        }

        if (!empty($payload['rute_pemberian'])) {
            $rute = $payload['rute_pemberian'];
            $code = $rute['code'] ?? (is_string($rute) ? $rute : $rute['name'] ?? null);
            $text = $rute['name'] ?? (is_string($rute) ? $rute : $rute['code'] ?? null);
            if ($code && $text) {
                FhirDictionary::registerDefault('medication-route', $code, $text, 'atc');
                $this->route_code = $code;
            }
        }

        if (!empty($payload['ucum'])) {
            $uc = $payload['ucum'];
            $code = $uc['cs_code'] ?? ($uc['cs_code'] ?? (is_string($uc) ? $uc : $uc['name'] ?? null));
            $text = $uc['name'] ?? (is_string($uc) ? $uc : $code ?? null);
            if ($code && $text) {
                FhirDictionary::registerDefault('numerator', $code, $text, 'ucum');
                $this->numerator_code = $code;
            }
        } elseif (!empty($payload['net_weight_uom_name'])) {
            $text = $payload['net_weight_uom_name'];
            $code = strtoupper(trim($text));
            FhirDictionary::registerDefault('numerator', $code, $text, 'ucum');
            $this->numerator_code = $code;
        }

        if (!empty($payload['uom'])) {
            $uom = $payload['uom'];
            $name = $uom['name'] ?? (is_string($uom) ? $uom : null);
            $code = $uom['code'] ?? (is_string($uom) ? $uom : null);
            if ($name) {
                $std = FhirDictionary::standardizeDenominator($code, $name);
                if ($std == null) {
                    $this->toastWarning("Satuan denominator '{$name}' tidak dikenali dan tidak bisa distandarisasi ke HL7 OrderableDrugForm. Silakan tambahkan secara manual melalui tombol + di sebelah dropdown denominator.");
                }
                // FhirDictionary::registerDefault('OrderableDrugForm', $std['code'], $std['name'], 'hl7', \App\Services\SatuSehat\FhirDictionary::HL7 . '/v3-orderableDrugForm');
                $this->denominator_code = $std['code'];
            }
        }

        if (!empty($payload['controlled_drug'])) {
            $cd = $payload['controlled_drug'];
            $code = $cd['code'] ?? (is_string($cd) ? $cd : $cd['name'] ?? null);
            $text = $cd['name'] ?? (is_string($cd) ? $cd : $cd['code'] ?? null);
            if ($code && $text) {
                FhirDictionary::registerDefault('controlled-drug', $code, $text, 'other');
                $this->controlled_drug_code = $code;
            }
        }
    }

    public function saveMapping(): void
    {
        $this->validate(
            [
                'selectedKfaCode' => 'required|string',
                'form_code' => 'required|string',
                'route_code' => 'required|string',
                'numerator_code' => 'required|string',
                'denominator_code' => 'required|string',
            ],
            [
                'selectedKfaCode.required' => 'Kode KFA wajib dipilih',
                'form_code.required' => 'Bentuk sediaan wajib dipilih',
                'route_code.required' => 'Rute wajib dipilih',
                'numerator_code.required' => 'Numerator wajib dipilih',
                'denominator_code.required' => 'Denominator wajib dipilih',
            ],
        );

        MedicationMap::updateOrCreate(
            ['local_code' => $this->selectedCode],
            [
                'kfa_code' => $this->selectedKfaCode,
                'kfa_name' => $this->selectedKfaName,
                'system_url' => $this->selectedSystemUrl,
                'kfa_payload' => $this->kfa_payload,
                'form_code' => $this->form_code,
                'form_name' => FhirDictionary::where('source', 'kemkes')->where('type', 'medication-form')->where('system_code', $this->form_code)->value('system_term'),
                'form_display' => FhirDictionary::where('source', 'kemkes')->where('type', 'medication-form')->where('system_code', $this->form_code)->value('system_display'),
                'route_code' => $this->route_code,
                'route_name' => FhirDictionary::where('source', 'atc')->where('type', 'medication-route')->where('system_code', $this->route_code)->value('system_term'),
                'route_display' => FhirDictionary::where('source', 'atc')->where('type', 'medication-route')->where('system_code', $this->route_code)->value('system_display') ?: 'http://www.whocc.no/atc',
                'numerator_code' => $this->numerator_code,
                'numerator_name' => FhirDictionary::where('source', 'ucum')->where('type', 'numerator')->where('system_code', $this->numerator_code)->value('system_term'),
                'numerator_display' => FhirDictionary::where('source', 'ucum')->where('type', 'numerator')->where('system_code', $this->numerator_code)->value('system_display') ?: 'http://unitsofmeasure.org',
                'denominator_code' => $this->denominator_code,
                'denominator_name' => FhirDictionary::where('source', 'hl7')->where('type', 'OrderableDrugForm')->where('system_code', $this->denominator_code)->value('system_term'),
                'denominator_display' => FhirDictionary::where('source', 'hl7')->where('type', 'OrderableDrugForm')->where('system_code', $this->denominator_code)->value('system_display'),
                'controlled_drug_code' => $this->controlled_drug_code,
                'controlled_drug_name' => FhirDictionary::where('source', 'other')->where('type', 'controlled-drug')->where('system_code', $this->controlled_drug_code)->value('system_term'),
                'controlled_drug_display' => FhirDictionary::where('source', 'other')->where('type', 'controlled-drug')->where('system_code', $this->controlled_drug_code)->value('system_display'),
                'medication_type_code' => $this->medication_type_code,
                'medication_type_name' => FhirDictionary::where('source', 'kemkes')->where('type', 'medication-type')->where('system_code', $this->medication_type_code)->value('system_term'),
                'medication_type_display' => FhirDictionary::where('source', 'kemkes')->where('type', 'medication-type')->where('system_code', $this->medication_type_code)->value('system_display'),
            ],
        );

        $this->showModal = false;
        $this->toastSuccess('Mapping KFA berhasil disimpan', 'Sukses');
    }

    public function deleteMapping(string $code): void
    {
        MedicationMap::where('local_code', $code)->delete();
        $this->toastSuccess('Mapping berhasil dihapus', 'Sukses');
    }

    public function getDictionary(string $source, string $type)
    {
        return FhirDictionary::where('source', $source)->where('type', $type)->orderBy('system_term')->get();
    }

    public function openDictionaryModal(string $source, string $type, string $title): void
    {
        $this->dictSource = $source;
        $this->dictType = $type;
        $this->dictTitle = $title;
        $this->dictSystemCode = '';
        $this->dictSystemTerm = '';
        $this->showDictionaryModal = true;
    }

    public function saveDictionary(): void
    {
        $this->validate(
            [
                'dictSystemCode' => 'required|string|max:50',
                'dictSystemTerm' => 'required|string|max:255',
            ],
            [
                'dictSystemCode.required' => 'Kode wajib diisi',
                'dictSystemTerm.required' => 'Display wajib diisi',
            ],
        );

        FhirDictionary::firstOrCreate(
            [
                'source' => $this->dictSource,
                'type' => $this->dictType,
                'system_code' => $this->dictSystemCode,
            ],
            [
                'system_term' => $this->dictSystemTerm,
            ],
        );

        $this->showDictionaryModal = false;
        $this->toastSuccess("Data referensi berhasil ditambahkan untuk modul {$this->dictTitle}");
    }
}; ?>

<div>
    <x-ui.page-header title="Obat — Mapping & UUID"
        subtitle="Kelola mapping KFA, UUID BPJS, dan IHS Satu Sehat untuk data obat dalam satu tampilan">
        <x-slot name="actions">
            <x-atoms.button wire:click="$set('showSyncModal', true)" variant="outline" icon="arrow-path">
                Sync UUID BPJS
            </x-atoms.button>
        </x-slot>
    </x-ui.page-header>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <x-organisms.stat-card title="Total Obat" :value="number_format($totalSimrs)" icon="cube" color="zinc" />
        <x-organisms.stat-card title="KFA Ter-mapping" :value="number_format($totalKfa)" icon="link" color="emerald"
            :subtitle="'dari ' . number_format($totalSimrs) . ' total'" />
        <x-organisms.stat-card title="UUID BPJS" :value="number_format($totalBpjs)" icon="identification" color="blue"
            :subtitle="'dari ' . number_format($totalSimrs) . ' total'" />
        <x-organisms.stat-card title="IHS Satu Sehat" :value="number_format($totalSs)" icon="beaker" color="sky"
            :subtitle="'dari ' . number_format($totalKfa) . ' ter-mapping'" />
    </div>

    @php
        $mappedCount = collect($items->items())->filter(fn($i) => $i->kfa_code)->count();
    @endphp

    <x-organisms.data-panel>
        <x-slot:filter>
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-[200px]">
                    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                        placeholder="Cari kode atau nama obat..." clearable />
                </div>
                <flux:select wire:model.live="filterSs" class="sm:w-52">
                    <flux:select.option value="">Semua Status SS</flux:select.option>
                    <flux:select.option value="terdaftar">Terkirim ke SS</flux:select.option>
                    <flux:select.option value="belum">Belum Terkirim</flux:select.option>
                    <flux:select.option value="mapped_belum_kirim">Ter-mapping, Belum Kirim</flux:select.option>
                </flux:select>
                <flux:select wire:model.live="perPage" class="w-40 shrink-0">
                    <flux:select.option value="25">25 / halaman</flux:select.option>
                    <flux:select.option value="50">50 / halaman</flux:select.option>
                    <flux:select.option value="100">100 / halaman</flux:select.option>
                </flux:select>
                <div
                    class="hidden sm:flex items-center gap-2.5 px-3.5 py-2 text-xs font-medium bg-white dark:bg-primary-dark-800 rounded-xl border border-zinc-200 dark:border-primary-dark-700 whitespace-nowrap shadow-sm">
                    <span class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400">
                        <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 dark:bg-emerald-500"></span>
                        {{ $mappedCount }} ter-mapping
                    </span>
                    <span class="text-zinc-300 dark:text-primary-dark-600">/</span>
                    <span class="text-zinc-500 dark:text-primary-dark-400">{{ $items->count() }} di halaman ini</span>
                </div>
            </div>
        </x-slot:filter>

        <x-organisms.table>
            <x-slot:headings>
                <x-atoms.table-heading class="w-36">Kode Lokal</x-atoms.table-heading>
                <x-atoms.table-heading>Nama Obat / Alkes</x-atoms.table-heading>
                <x-atoms.table-heading class="w-24">Satuan</x-atoms.table-heading>
                <x-atoms.table-heading>Mapping KFA</x-atoms.table-heading>
                <x-atoms.table-heading>
                    <div class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-blue-400"></span>
                        UUID BPJS
                    </div>
                </x-atoms.table-heading>
                <x-atoms.table-heading>
                    <div class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-sky-400"></span>
                        IHS Satu Sehat
                    </div>
                </x-atoms.table-heading>
                <x-atoms.table-heading align="center" class="w-44">Aksi</x-atoms.table-heading>
            </x-slot:headings>

            @forelse ($items as $item)
                <x-molecules.table-row :key="$item->kode_brng">
                    <x-atoms.table-cell>
                        <span
                            class="inline-block font-mono text-xs font-bold px-2 py-1 rounded-md
                            bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300
                            ring-1 ring-primary-100 dark:ring-primary-800/40">
                            {{ $item->kode_brng }}
                        </span>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        <p class="text-sm font-medium text-zinc-800 dark:text-primary-dark-100 leading-snug">
                            {{ $item->nama_brng }}
                        </p>
                        <div class="flex flex-wrap gap-1 mt-1.5">
                            @if ($item->jenis)
                                <span
                                    class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-sky-50 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300 border border-sky-200 dark:border-sky-800/40">
                                    {{ $item->jenis->nama }}
                                </span>
                            @endif
                            @if ($item->industriFarmasi)
                                <span
                                    class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300 border border-purple-200 dark:border-purple-800/40">
                                    {{ $item->industriFarmasi->nama_industri }}
                                </span>
                            @endif
                            @if ($item->kategoriBarang)
                                <span
                                    class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300 border border-amber-200 dark:border-amber-800/40">
                                    {{ $item->kategoriBarang->nama }}
                                </span>
                            @endif
                            @if ($item->golonganBarang)
                                <span
                                    class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300 border border-rose-200 dark:border-rose-800/40">
                                    {{ $item->golonganBarang->nama }}
                                </span>
                            @endif
                        </div>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        <div class="space-y-1">
                            @if ($item->satuanBesar)
                                <div class="flex items-center gap-1.5">
                                    <span class="text-[10px] text-zinc-400 dark:text-primary-dark-500 w-12">Besar</span>
                                    <span
                                        class="font-mono text-xs font-semibold text-zinc-700 dark:text-primary-dark-200">{{ $item->satuanBesar->satuan }}</span>
                                </div>
                            @endif
                            @if ($item->satuanKecil)
                                <div class="flex items-center gap-1.5">
                                    <span class="text-[10px] text-zinc-400 dark:text-primary-dark-500 w-12">Kecil</span>
                                    <span
                                        class="font-mono text-xs font-semibold text-zinc-700 dark:text-primary-dark-200">{{ $item->satuanKecil->satuan }}</span>
                                </div>
                            @endif
                        </div>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        @if ($item->kfa_code)
                            <div class="flex items-start gap-2.5">
                                <span
                                    class="mt-1 w-2 h-2 rounded-full bg-emerald-400 dark:bg-emerald-500 shrink-0 ring-2 ring-emerald-100 dark:ring-emerald-900/50"></span>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <p class="font-mono text-xs font-bold text-emerald-700 dark:text-emerald-400">
                                            {{ $item->kfa_code }}
                                        </p>
                                        @if ($item->system_url)
                                            <flux:badge size="sm"
                                                color="{{ str_contains($item->system_url, 'kfa-v3') ? 'blue' : 'green' }}">
                                                {{ str_contains($item->system_url, 'kfa-v3') ? 'Alkes' : 'Farmasi' }}
                                            </flux:badge>
                                        @endif
                                    </div>
                                    <p
                                        class="mt-0.5 text-xs text-zinc-500 dark:text-primary-dark-400 leading-snug line-clamp-2">
                                        {{ $item->kfa_name }}
                                    </p>
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        @if ($item->form_name)
                                            <span
                                                class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-zinc-100 text-zinc-600 dark:bg-primary-dark-800 dark:text-primary-dark-300 border border-zinc-200 dark:border-primary-dark-700">Form:
                                                {{ $item->form_name }}</span>
                                        @endif
                                        @if ($item->route_name)
                                            <span
                                                class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-zinc-100 text-zinc-600 dark:bg-primary-dark-800 dark:text-primary-dark-300 border border-zinc-200 dark:border-primary-dark-700">Rute:
                                                {{ $item->route_name }}</span>
                                        @endif
                                        @if ($item->numerator_code || $item->denominator_code)
                                            <span
                                                class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-zinc-100 text-zinc-600 dark:bg-primary-dark-800 dark:text-primary-dark-300 border border-zinc-200 dark:border-primary-dark-700">Unit:
                                                {{ $item->numerator_code }}{{ $item->denominator_code ? '/' . $item->denominator_code : '' }}</span>
                                        @endif
                                        @if ($item->controlled_drug_name)
                                            <span
                                                class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400 border border-red-200 dark:border-red-800/50">Rx:
                                                {{ $item->controlled_drug_name }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="flex items-center gap-2 text-zinc-400 dark:text-primary-dark-500">
                                <span class="w-2 h-2 rounded-full bg-zinc-200 dark:bg-primary-dark-600"></span>
                                <span class="text-xs italic">Belum di-mapping</span>
                            </div>
                        @endif
                    </x-atoms.table-cell>

                    {{-- UUID BPJS --}}
                    <x-atoms.table-cell>
                        @if ($item->bpjs_uuid)
                            <span
                                class="font-mono text-xs font-bold text-blue-700 dark:text-blue-400">{{ $item->bpjs_uuid }}</span>
                        @else
                            <span class="text-xs italic text-zinc-400 dark:text-primary-dark-500">Belum terdaftar</span>
                        @endif
                    </x-atoms.table-cell>

                    {{-- IHS Satu Sehat --}}
                    <x-atoms.table-cell>
                        @if ($item->ihs_number)
                            <span
                                class="font-mono text-xs font-semibold text-sky-700 dark:text-sky-400">{{ $item->ihs_number }}</span>
                        @elseif ($item->kfa_code)
                            <span class="text-xs italic text-zinc-400 dark:text-primary-dark-500">Belum terkirim</span>
                        @else
                            <span class="text-xs text-zinc-300 dark:text-primary-dark-600">—</span>
                        @endif
                    </x-atoms.table-cell>

                    <x-atoms.table-cell :action="true" align="center">
                        {{-- Grup KFA --}}
                        <div
                            class="flex items-center gap-0.5 border-r border-zinc-200 dark:border-primary-dark-600 pr-2 mr-2">
                            <x-atoms.button
                                wire:click="openModal('{{ $item->kode_brng }}', '{{ addslashes($item->nama_brng) }}')"
                                size="sm" icon="{{ $item->kfa_code ? 'pencil-square' : 'plus' }}" variant="ghost"
                                tooltip="{{ $item->kfa_code ? 'Edit KFA' : 'Petakan KFA' }}" />
                            @if ($item->kfa_code)
                                <x-atoms.button wire:click="deleteMapping('{{ $item->kode_brng }}')" size="sm"
                                    icon="trash" variant="ghost" tooltip="Hapus KFA" class="text-red-500" />
                            @endif
                        </div>
                        {{-- Grup BPJS UUID --}}
                        <div
                            class="flex items-center gap-0.5 border-r border-zinc-200 dark:border-primary-dark-600 pr-2 mr-2">
                            @if (!$item->bpjs_uuid)
                                <x-atoms.button
                                    wire:click="generateBpjsUuid('{{ $item->kode_brng }}', '{{ addslashes($item->nama_brng) }}')"
                                    size="sm" variant="ghost" icon="plus-circle"
                                    class="text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/20"
                                    tooltip="Generate UUID BPJS" />
                            @else
                                <x-atoms.button variant="ghost" wire:click="viewBpjsDetail('{{ $item->kode_brng }}')"
                                    size="sm" icon="eye" tooltip="Lihat UUID BPJS" />
                                <x-atoms.button variant="ghost"
                                    wire:click="confirmDeleteBpjs('{{ $item->kode_brng }}', '{{ addslashes($item->nama_brng) }}')"
                                    size="sm" icon="trash" tooltip="Hapus UUID BPJS"
                                    class="text-red-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20" />
                            @endif
                        </div>
                        {{-- Grup Satu Sehat --}}
                        <div class="flex items-center gap-0.5">
                            @if ($item->ihs_number)
                                <x-atoms.button wire:click="showSsDetail('{{ $item->kfa_code }}')" size="sm"
                                    icon="eye" variant="ghost" tooltip="Detail IHS Satu Sehat" />
                                <x-atoms.button wire:click="sendToSatuSehat('{{ $item->kode_brng }}')" size="sm"
                                    icon="arrow-path" variant="ghost" tooltip="Update Satu Sehat" />
                            @elseif ($item->kfa_code)
                                <x-atoms.button wire:click="sendToSatuSehat('{{ $item->kode_brng }}')" size="sm"
                                    icon="arrow-up-tray" variant="ghost" tooltip="Kirim ke Satu Sehat"
                                    class="text-sky-600 hover:bg-sky-50 dark:hover:bg-sky-900/20" />
                            @endif
                        </div>
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @empty
                <x-molecules.table-row>
                    <x-atoms.table-cell colspan="6" align="center" class="py-16">
                        <div class="flex flex-col items-center gap-3">
                            <div
                                class="flex items-center justify-center w-14 h-14 rounded-2xl bg-zinc-100 dark:bg-primary-dark-700">
                                <flux:icon name="archive-box"
                                    class="w-7 h-7 text-zinc-300 dark:text-primary-dark-500" />
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-zinc-500 dark:text-primary-dark-400">Tidak ada
                                    data obat</p>
                                <p class="mt-0.5 text-xs text-zinc-400 dark:text-primary-dark-500">Coba ubah kata kunci
                                    pencarian</p>
                            </div>
                        </div>
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @endforelse
        </x-organisms.table>

        @if ($items->hasPages())
            <x-slot:footer>{{ $items->links() }}</x-slot:footer>
        @endif
    </x-organisms.data-panel>

    {{-- Modal KFA Mapping --}}
    <x-organisms.modal wire:model="showModal" maxWidth="3xl" title="Form Mapping Obat">
        <div class="space-y-4">
            <div class="mb-5 pb-4 border-b border-zinc-200 dark:border-primary-dark-700">

                <div class="flex items-center gap-3 mt-3 p-3 bg-amber-50 dark:bg-amber-900/20 rounded-xl">
                    <div
                        class="w-10 h-10 flex items-center justify-center bg-amber-100 dark:bg-amber-500/20 rounded-lg text-amber-600">
                        <flux:icon name="beaker" class="w-6 h-6" />
                    </div>
                    <div>
                        <p class="font-bold text-zinc-800 dark:text-primary-dark-100">{{ $selectedName }}</p>
                        <p class="text-xs text-zinc-500 font-mono mt-0.5">{{ $selectedCode }}</p>
                    </div>
                </div>
            </div>

            <div class="space-y-5">
                <div>
                    <label class="block text-sm font-semibold text-zinc-700 dark:text-primary-dark-300 mb-2">1. Kode
                        KFA (Kamus Farmasi & Alkes)</label>
                    @if ($selectedKfaCode)
                        <div
                            class="flex items-center justify-between p-3 border border-emerald-200 bg-emerald-50 dark:bg-emerald-900/20 dark:border-emerald-800/50 rounded-lg">
                            <div>
                                <p class="text-sm font-semibold text-emerald-800 dark:text-emerald-400 font-mono">
                                    {{ $selectedKfaCode }}
                                </p>
                                <p class="text-xs text-emerald-600 dark:text-emerald-500 mt-0.5">
                                    {{ $selectedKfaName }}
                                </p>
                            </div>
                            <x-atoms.button size="sm" variant="ghost" icon="x-mark"
                                wire:click="$set('selectedKfaCode', null)" class="text-emerald-700" />
                        </div>
                    @else
                        <livewire:components.kfa-search :defaultType="$kfaTypeHint" :onlyV2="true" :initialSearch="$selectedName"
                            :key="'kfa-' . ($selectedCode ?? '')" lazy />
                    @endif
                    @error('selectedKfaCode')
                        <span class="text-xs text-red-500 flex mt-1">{{ $message }}</span>
                    @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <flux:label class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">Bentuk
                                Sediaan (Form)</flux:label>
                            <x-atoms.button
                                wire:click="openDictionaryModal('kemkes', 'medication-form', 'Bentuk Sediaan')"
                                size="xs" variant="ghost" class="h-6 px-2 text-sky-600 hover:bg-sky-50">
                                <flux:icon name="plus" class="w-3 h-3 mr-1" /> Tambah
                            </x-atoms.button>
                        </div>
                        <flux:select wire:model="form_code" placeholder="Pilih Bentuk Sediaan..." searchable>
                            @foreach ($this->getDictionary('kemkes', 'medication-form') as $d)
                                <flux:select.option value="{{ $d->system_code }}">{{ $d->system_code }} -
                                    {{ $d->system_term }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        @error('form_code')
                            <span class="text-xs text-red-500">{{ $message }}</span>
                        @enderror
                    </div>
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <flux:label class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">Rute
                                Pemberian (ATC)</flux:label>
                            <x-atoms.button
                                wire:click="openDictionaryModal('atc', 'medication-route', 'Rute Pemberian')"
                                size="xs" variant="ghost" class="h-6 px-2 text-sky-600 hover:bg-sky-50">
                                <flux:icon name="plus" class="w-3 h-3 mr-1" /> Tambah
                            </x-atoms.button>
                        </div>
                        <flux:select wire:model="route_code" placeholder="Pilih Rute Pemberian..." searchable>
                            @foreach ($this->getDictionary('atc', 'medication-route') as $d)
                                <flux:select.option value="{{ $d->system_code }}">{{ $d->system_code }} -
                                    {{ $d->system_term }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        @error('route_code')
                            <span class="text-xs text-red-500">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <flux:label class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">Satuan
                                Numerator (Kekuatan)</flux:label>
                            <x-atoms.button wire:click="openDictionaryModal('ucum', 'numerator', 'Satuan Numerator')"
                                size="xs" variant="ghost" class="h-6 px-2 text-sky-600 hover:bg-sky-50">
                                <flux:icon name="plus" class="w-3 h-3 mr-1" /> Tambah
                            </x-atoms.button>
                        </div>
                        <flux:select wire:model="numerator_code" placeholder="Pilih Satuan Numerator..." searchable>
                            @foreach ($this->getDictionary('ucum', 'numerator') as $d)
                                <flux:select.option value="{{ $d->system_code }}">{{ $d->system_code }} -
                                    {{ $d->system_term }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        <span class="block mt-1 text-xs text-zinc-500 dark:text-primary-dark-400">Contoh:
                            <strong>MG</strong>, G, ML</span>
                        @error('numerator_code')
                            <span class="text-xs text-red-500">{{ $message }}</span>
                        @enderror
                    </div>
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <flux:label class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">Satuan
                                Denominator (Penyajian)</flux:label>
                            <x-atoms.button
                                wire:click="openDictionaryModal('hl7', 'OrderableDrugForm', 'Satuan Denominator')"
                                size="xs" variant="ghost" class="h-6 px-2 text-sky-600 hover:bg-sky-50">
                                <flux:icon name="plus" class="w-3 h-3 mr-1" /> Tambah
                            </x-atoms.button>
                        </div>
                        <flux:select wire:model="denominator_code" placeholder="Pilih Satuan Denominator..."
                            searchable>
                            @foreach ($this->getDictionary('hl7', 'OrderableDrugForm') as $d)
                                <flux:select.option value="{{ $d->system_code }}">{{ $d->system_code }} -
                                    {{ $d->system_term }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        <span class="block mt-1 text-xs text-zinc-500 dark:text-primary-dark-400">Contoh:
                            <strong>TAB</strong>, SYR, KAP</span>
                        @error('denominator_code')
                            <span class="text-xs text-red-500">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div
                    class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4 pt-4 border-t border-zinc-200 dark:border-primary-dark-700">
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <flux:label class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">Kelompok
                                Obat/Pengawasan</flux:label>
                            <x-atoms.button
                                wire:click="openDictionaryModal('other', 'controlled-drug', 'Kelompok Obat/Pengawasan')"
                                size="xs" variant="ghost" class="h-6 px-2 text-sky-600 hover:bg-sky-50">
                                <flux:icon name="plus" class="w-3 h-3 mr-1" /> Tambah
                            </x-atoms.button>
                        </div>
                        <flux:select wire:model="controlled_drug_code" placeholder="Pilih Pengawasan (Opsional)..."
                            searchable>
                            <flux:select.option value="">[Tidak Ada]</flux:select.option>
                            @foreach ($this->getDictionary('other', 'controlled-drug') as $d)
                                <flux:select.option value="{{ $d->system_code }}">{{ $d->system_code }} -
                                    {{ $d->system_term }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <flux:label class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">Tipe Obat
                            </flux:label>
                        </div>
                        <flux:select wire:model="medication_type_code" placeholder="Pilih Tipe Obat..." searchable>
                            @foreach ($this->getDictionary('kemkes', 'medication-type') as $d)
                                <flux:select.option value="{{ $d->system_code }}">{{ $d->system_code }} -
                                    {{ $d->system_term }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                </div>
            </div>


            <x-slot:footer>
                <div class="flex justify-end gap-2 pt-4 mt-6 border-t border-zinc-200 dark:border-primary-dark-700">
                    <x-atoms.button wire:click="$set('showModal', false)" variant="ghost">Batal</x-atoms.button>
                    <x-atoms.button wire:click="saveMapping" variant="primary" icon="check">Simpan
                        Mapping</x-atoms.button>
                </div>
            </x-slot:footer>
        </div>
    </x-organisms.modal>

    {{-- Modal Detail IHS Satu Sehat --}}
    <x-organisms.modal wire:model="showSsDetailModal" maxWidth="2xl" title="">
        @if ($selectedSs)
            <div class="space-y-5">
                <div class="flex items-center gap-4 pb-4 border-b border-zinc-200 dark:border-primary-dark-700">
                    <div
                        class="flex items-center justify-center w-12 h-12 rounded-xl bg-sky-100 dark:bg-sky-900/40 shrink-0">
                        <flux:icon name="beaker" class="w-6 h-6 text-sky-600 dark:text-sky-400" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <h2 class="text-base font-bold text-zinc-900 dark:text-primary-dark-100 truncate">
                            {{ $selectedSs->kfa_display ?? 'Medication' }}
                        </h2>
                        <p class="font-mono text-sm text-sky-600 dark:text-sky-400">{{ $selectedSs->ihs_number }}</p>
                    </div>
                    <flux:badge :color="$selectedSs->status === 'active' ? 'green' : 'zinc'" size="sm">
                        {{ $selectedSs->status }}
                    </flux:badge>
                </div>

                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div class="p-4 space-y-2.5 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                        <h4 class="text-xs font-semibold uppercase text-zinc-400">Identitas Obat</h4>
                        <div class="flex gap-2">
                            <dt class="w-28 text-zinc-500 shrink-0">KFA Code</dt>
                            <dd class="font-mono text-zinc-900 dark:text-primary-dark-100">
                                {{ $selectedSs->kfa_code ?? '-' }}
                            </dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="w-28 text-zinc-500 shrink-0">Display</dt>
                            <dd class="text-zinc-900 dark:text-primary-dark-100">{{ $selectedSs->kfa_display ?? '-' }}
                            </dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="w-28 text-zinc-500 shrink-0">Tipe</dt>
                            <dd class="text-zinc-900 dark:text-primary-dark-100">
                                {{ $selectedSs->medication_type ?? '-' }}
                            </dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="w-28 text-zinc-500 shrink-0">Identifier</dt>
                            <dd class="font-mono text-xs text-zinc-700 dark:text-primary-dark-300">
                                {{ $selectedSs->identifier ?? '-' }}
                            </dd>
                        </div>
                    </div>
                    <div class="p-4 space-y-2.5 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                        <h4 class="text-xs font-semibold uppercase text-zinc-400">Sediaan</h4>
                        <div class="flex gap-2">
                            <dt class="w-28 text-zinc-500 shrink-0">Form Code</dt>
                            <dd class="font-mono text-zinc-900 dark:text-primary-dark-100">
                                {{ $selectedSs->form_code ?? '-' }}
                            </dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="w-28 text-zinc-500 shrink-0">Form Display</dt>
                            <dd class="text-zinc-900 dark:text-primary-dark-100">
                                {{ $selectedSs->form_display ?? '-' }}
                            </dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="w-28 text-zinc-500 shrink-0">Terakhir Sync</dt>
                            <dd class="text-zinc-900 dark:text-primary-dark-100">
                                {{ $selectedSs->synced_at?->format('d M Y H:i') ?? '-' }}
                            </dd>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end pt-2 border-t border-zinc-200 dark:border-primary-dark-700">
                    <x-atoms.button variant="ghost"
                        wire:click="$set('showSsDetailModal', false)">Tutup</x-atoms.button>
                </div>
            </div>
        @endif

    </x-organisms.modal>

    {{-- Modal Sync UUID BPJS --}}
    <x-organisms.modal wire:model="showSyncModal" title="Sync Semua UUID BPJS Obat" maxWidth="md">
        <div class="space-y-5">
            <div class="flex items-center gap-4">
                <div
                    class="flex items-center justify-center w-12 h-12 rounded-full bg-primary-100 dark:bg-primary-900/30">
                    <flux:icon name="arrow-path" class="w-6 h-6 text-primary-600 dark:text-primary-400" />
                </div>
                <p class="text-sm text-zinc-500 dark:text-primary-dark-400">
                    {{ number_format($unsyncedBpjs) }} data obat belum memiliki UUID BPJS
                </p>
            </div>
            <div
                class="p-3.5 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-[11px] text-amber-800 dark:text-amber-200 leading-relaxed italic">
                <flux:icon name="exclamation-triangle" class="inline w-3.5 h-3.5 mr-1 text-amber-500" />
                UUID baru akan di-generate untuk semua obat yang belum terdaftar. Proses berjalan di background.
            </div>
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-atoms.button variant="ghost" wire:click="$set('showSyncModal', false)">Batal</x-atoms.button>
                <x-atoms.button variant="primary" icon="arrow-path" wire:click="syncAllBpjs">Mulai
                    Sync</x-atoms.button>
            </div>
        </x-slot>
    </x-organisms.modal>

    {{-- Modal Detail UUID BPJS --}}
    <x-organisms.modal wire:model="showBpjsDetailModal" title="Detail UUID BPJS Obat" maxWidth="md">
        @if ($selectedBpjsItem)
            <dl class="space-y-4">
                <div>
                    <dt class="text-[10px] uppercase font-bold text-zinc-400 dark:text-primary-dark-500 mb-1">Nama Obat
                    </dt>
                    <dd class="text-sm font-semibold text-zinc-800 dark:text-white">{{ $selectedBpjsItem->name }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] uppercase font-bold text-zinc-400 dark:text-primary-dark-500 mb-1">Kode
                        Lokal</dt>
                    <dd class="font-mono text-sm font-bold text-primary-600 dark:text-primary-400">
                        {{ $selectedBpjsItem->local_code }}
                    </dd>
                </div>
                <div>
                    <dt class="text-[10px] uppercase font-bold text-zinc-400 dark:text-primary-dark-500 mb-1">Resource
                        ID (UUID)</dt>
                    <dd class="font-mono text-sm font-bold text-emerald-600 dark:text-emerald-400 break-all">
                        {{ $selectedBpjsItem->id }}
                    </dd>
                </div>
                <div>
                    <dt class="text-[10px] uppercase font-bold text-zinc-400 dark:text-primary-dark-500 mb-1">Dibuat
                    </dt>
                    <dd class="text-sm text-zinc-600 dark:text-primary-dark-300">
                        {{ $selectedBpjsItem->created_at?->format('d M Y, H:i') }}
                    </dd>
                </div>
            </dl>
            <x-slot name="footer">
                <div class="flex justify-end">
                    <x-atoms.button variant="ghost"
                        wire:click="$set('showBpjsDetailModal', false)">Tutup</x-atoms.button>
                </div>
            </x-slot>
        @endif
    </x-organisms.modal>

    {{-- Modal Hapus UUID BPJS --}}
    <x-organisms.modal wire:model="showDeleteBpjsModal" title="Hapus UUID BPJS?" maxWidth="md">
        <div class="space-y-5">
            <div class="flex items-center gap-4">
                <div
                    class="flex items-center justify-center w-12 h-12 rounded-2xl bg-red-50 dark:bg-red-900/20 shrink-0">
                    <flux:icon name="trash" class="w-6 h-6 text-red-600 dark:text-red-400" />
                </div>
                <p class="text-sm text-zinc-500 dark:text-primary-dark-400">
                    UUID BPJS untuk obat <strong class="text-zinc-800 dark:text-white">{{ $deleteBpjsName }}</strong>
                    akan dihapus.
                </p>
            </div>
            <div
                class="p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-[11px] text-amber-700 dark:text-amber-300 leading-relaxed italic">
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

    {{-- Modal Tambah Dictionary --}}
    <x-organisms.modal wire:model="showDictionaryModal" title="Tambah Data: {{ $dictTitle }}" maxWidth="sm">
        <div class="space-y-4">
            <div>
                <flux:input wire:model="dictSystemCode" label="System Code" placeholder="Cth: MG, TAB, PO, dll"
                    required />
            </div>
            <div>
                <flux:input wire:model="dictSystemTerm" label="System Term / Display"
                    placeholder="Nama entri / Deskripsi..." required />
            </div>
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-atoms.button variant="ghost" wire:click="$set('showDictionaryModal', false)">Batal</x-atoms.button>
                <x-atoms.button variant="primary" icon="check" wire:click="saveDictionary">Simpan</x-atoms.button>
            </div>
        </x-slot>
    </x-organisms.modal>
</div>
