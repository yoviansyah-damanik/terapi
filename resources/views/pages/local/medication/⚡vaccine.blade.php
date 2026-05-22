<?php

use App\Jobs\SyncBpjsVaccinesJob;
use App\Models\Bpjs\BpjsVaccine;
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

new #[Layout('layouts::app')] #[Title('Vaksin — Mapping & UUID')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterSs = '';

    public int $perPage = 25;

    // BPJS UUID
    public bool $showSyncModal = false;
    public bool $showBpjsDetailModal = false;
    public ?BpjsVaccine $selectedBpjsItem = null;
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
    public ?string $immunization_reason_code = null;
    public ?string $immunization_routine_timing_code = null;

    // Modal tambah dictionary
    public bool $showDictionaryModal = false;
    public string $dictSource = '';
    public string $dictType = '';
    public string $dictTitle = '';
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

        // Dapatkan local_codes vaksin yang sudah terkirim ke SS
        $syncedLocalCodes = MedicationMap::whereIn('kfa_code', $ssMap->filter()->keys()->toArray())
            ->pluck('local_code')
            ->toArray();

        $query = DataBarang::query()
            ->active()
            ->with(['industriFarmasi', 'kategoriBarang', 'golonganBarang', 'satuanKecil', 'satuanBesar', 'jenis'])
            ->where('nama_brng', 'like', 'vaksin%')
            ->when($this->search, fn($q) => $q->where(fn($sq) => $sq->where('kode_brng', 'like', "%{$this->search}%")->orWhere('nama_brng', 'like', "%{$this->search}%")))
            ->orderBy('nama_brng');

        if ($this->filterSs === 'terdaftar') {
            $query->whereIn('kode_brng', $syncedLocalCodes);
        } elseif ($this->filterSs === 'belum') {
            $query->whereNotIn('kode_brng', $syncedLocalCodes);
        }

        $items = $query->paginate($this->perPage);

        $codes = $items->pluck('kode_brng')->toArray();
        $mappings = MedicationMap::whereIn('local_code', $codes)->get()->keyBy('local_code');

        $bpjsRegistered = BpjsVaccine::pluck('id', 'local_code');

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
            $item->immunization_reason_name = $map->immunization_reason_name ?? null;
            $item->immunization_routine_timing_name = $map->immunization_routine_timing_name ?? null;
            $item->bpjs_uuid = $bpjsRegistered->get($item->kode_brng);
            $item->ihs_number = $item->kfa_code ? $ssMap[$item->kfa_code] ?? null : null;
            return $item;
        });

        // Hitung totalSs khusus untuk vaksin via kfa_code vaksin yang terdaftar di SS
        $allVaccineLocalCodes = DataBarang::where('nama_brng', 'like', 'vaksin%')->pluck('kode_brng')->toArray();
        $vaccineKfaCodes = MedicationMap::whereIn('local_code', $allVaccineLocalCodes)->whereNotNull('kfa_code')->pluck('kfa_code')->toArray();

        $totalSimrs = $query->count();
        $totalBpjs = BpjsVaccine::count();
        $totalKfa = MedicationMap::whereIn('local_code', DataBarang::where('nama_brng', 'like', 'vaksin%')->pluck('kode_brng')->toArray())->count();
        $totalSs = SatuSehatMedication::whereIn('kfa_code', $vaccineKfaCodes)->count();

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
        if (BpjsVaccine::where('local_code', $code)->exists()) {
            $this->toastWarning('Vaksin ini sudah memiliki UUID BPJS.');
            return;
        }
        BpjsVaccine::create(['local_code' => $code, 'name' => $name]);
        $this->toastSuccess("UUID BPJS berhasil di-generate untuk {$name}.");
    }

    public function syncAllBpjs(): void
    {
        SyncBpjsVaccinesJob::dispatch();
        $this->showSyncModal = false;
        $this->toastSuccess('Sync semua UUID BPJS dijadwalkan. Proses berjalan di background.');
    }

    public function viewBpjsDetail(string $code): void
    {
        $this->selectedBpjsItem = BpjsVaccine::where('local_code', $code)->first();
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
        BpjsVaccine::where('local_code', $this->deleteBpjsCode)->delete();
        $this->showDeleteBpjsModal = false;
        $this->reset(['deleteBpjsCode', 'deleteBpjsName']);
        $this->toastSuccess('UUID BPJS berhasil dihapus.');
    }

    // ── Satu Sehat Medication actions ────────────────────────────────────────

    public function sendToSatuSehat(string $localCode): void
    {
        $map = MedicationMap::where('local_code', $localCode)->first();
        if (!$map || !$map->kfa_code) {
            $this->toastError('Vaksin belum memiliki mapping KFA');
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
        $this->immunization_reason_code = $existing->immunization_reason_code ?? null;
        $this->immunization_routine_timing_code = $existing->immunization_routine_timing_code ?? null;

        $this->showModal = true;
    }

    #[On('kfa-selected')]
    public function setKfaSelection(string $kfa_code, string $name, string $kfa_type, string $system_url, array $payload = []): void
    {
        $this->selectedKfaCode = $kfa_code;
        $this->selectedKfaName = $name;
        $this->selectedSystemUrl = $system_url;
        $this->kfa_payload = $payload;

        // Bersihkan pilihan lama agar select re-render tanpa konflik
        $this->form_code = null;
        $this->route_code = null;
        $this->numerator_code = null;
        $this->denominator_code = null;
        $this->controlled_drug_code = null;

        // Tahap 1: daftarkan semua entry baru ke kamus FhirDictionary
        // Livewire akan re-render setelah method ini selesai → options select sudah memuat entry baru
        $pending = [];

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
            $code = $uc['code'] ?? ($uc['cd_code'] ?? (is_string($uc) ? $uc : $uc['name'] ?? null));
            $text = $uc['name'] ?? (is_string($uc) ? $uc : $code ?? null);
            if ($code && $text) {
                FhirDictionary::registerDefault(source: 'ucum', type: 'numerator', code: $code, name: $text);
                $pending['numerator_code'] = $code;
            }
        } elseif (!empty($payload['net_weight_uom_name'])) {
            $text = $payload['net_weight_uom_name'];
            $code = strtoupper(trim($text));
            FhirDictionary::registerDefault(source: 'ucum', type: 'numerator', code: $code, name: $text);
            $pending['numerator_code'] = $code;
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
                FhirDictionary::registerDefault(source: 'other', type: 'controlled-drug', code: $code, name: $text);
                $pending['controlled_drug_code'] = $code;
            }
        }

        // Tahap 2: dispatch ke diri sendiri agar kode diterapkan SETELAH render ulang
        // (sehingga options select sudah berisi entry baru dari registerDefault di atas)
        $this->dispatch('apply-kfa-codes', codes: $pending);
    }

    #[On('apply-kfa-codes')]
    public function applyKfaCodes(array $codes): void
    {
        foreach ($codes as $field => $value) {
            if (property_exists($this, $field)) {
                $this->$field = $value;
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
                'immunization_reason_code' => 'required|string',
            ],
            [
                'selectedKfaCode.required' => 'Kode KFA wajib dipilih',
                'form_code.required' => 'Bentuk sediaan wajib dipilih',
                'route_code.required' => 'Rute wajib dipilih',
                'numerator_code.required' => 'Numerator wajib dipilih',
                'denominator_code.required' => 'Denominator wajib dipilih',
                'immunization_reason_code.required' => 'Alasan imunisasi wajib dipilih',
            ],
        );

        $dict = fn(string $source, string $type, ?string $code, string $field) => $code ? FhirDictionary::where('source', $source)->where('type', $type)->where('system_code', $code)->value($field) : null;

        MedicationMap::updateOrCreate(
            ['local_code' => $this->selectedCode],
            [
                'kfa_code' => $this->selectedKfaCode,
                'kfa_name' => $this->selectedKfaName,
                'system_url' => $this->selectedSystemUrl,
                'kfa_payload' => $this->kfa_payload,
                'form_code' => $this->form_code,
                'form_name' => $dict('kemkes', 'medication-form', $this->form_code, 'system_term'),
                'form_display' => $dict('kemkes', 'medication-form', $this->form_code, 'system_display'),
                'route_code' => $this->route_code,
                'route_name' => $dict('atc', 'medication-route', $this->route_code, 'system_term'),
                'route_display' => $dict('atc', 'medication-route', $this->route_code, 'system_display') ?: 'http://www.whocc.no/atc',
                'numerator_code' => $this->numerator_code,
                'numerator_name' => $dict('ucum', 'numerator', $this->numerator_code, 'system_term'),
                'numerator_display' => $dict('ucum', 'numerator', $this->numerator_code, 'system_display') ?: 'http://unitsofmeasure.org',
                'denominator_code' => $this->denominator_code,
                'denominator_name' => $dict('hl7', 'OrderableDrugForm', $this->denominator_code, 'system_term'),
                'denominator_display' => $dict('hl7', 'OrderableDrugForm', $this->denominator_code, 'system_display'),
                'controlled_drug_code' => $this->controlled_drug_code,
                'controlled_drug_name' => $dict('other', 'controlled-drug', $this->controlled_drug_code, 'system_term'),
                'controlled_drug_display' => $dict('other', 'controlled-drug', $this->controlled_drug_code, 'system_display'),
                'medication_type_code' => $this->medication_type_code,
                'medication_type_name' => $dict('kemkes', 'medication-type', $this->medication_type_code, 'system_term'),
                'medication_type_display' => $dict('kemkes', 'medication-type', $this->medication_type_code, 'system_display'),
                'immunization_reason_code' => $this->immunization_reason_code,
                'immunization_reason_name' => $dict('kemkes', 'immunization-reason', $this->immunization_reason_code, 'system_term'),
                'immunization_routine_timing_code' => $this->immunization_routine_timing_code ?: null,
                'immunization_routine_timing_name' => $dict('kemkes', 'immunization-routine-timing', $this->immunization_routine_timing_code, 'system_term'),
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
        $this->validate(['dictSystemCode' => 'required|string', 'dictSystemTerm' => 'required|string'], ['dictSystemCode.required' => 'Kode wajib diisi', 'dictSystemTerm.required' => 'Display wajib diisi']);

        FhirDictionary::firstOrCreate(['source' => $this->dictSource, 'type' => $this->dictType, 'system_code' => $this->dictSystemCode], ['system_term' => $this->dictSystemTerm]);

        $this->showDictionaryModal = false;
        $this->toastSuccess('Data dictionary berhasil ditambahkan.');
    }
}; ?>

<div>
    <x-ui.page-header title="Vaksin — Mapping & UUID"
        subtitle="Kelola mapping KFA, UUID BPJS, dan IHS Satu Sehat untuk data vaksin dalam satu tampilan">
        <x-slot:actions>
            <x-atoms.button variant="outline" icon="arrow-path" wire:click="$set('showSyncModal', true)">
                Sync UUID BPJS
            </x-atoms.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <x-organisms.stat-card title="Total Vaksin" :value="number_format($totalSimrs)" icon="shield-check" color="zinc" />
        <x-organisms.stat-card title="KFA Ter-mapping" :value="number_format($totalKfa)" icon="link" color="emerald"
            :subtitle="'dari ' . number_format($totalSimrs) . ' total'" />
        <x-organisms.stat-card title="UUID BPJS" :value="number_format($totalBpjs)" icon="identification" color="blue"
            :subtitle="'dari ' . number_format($totalSimrs) . ' total'" />
        <x-organisms.stat-card title="IHS Satu Sehat" :value="number_format($totalSs)" icon="beaker" color="sky"
            :subtitle="'dari ' . number_format($totalKfa) . ' ter-mapping'" />
    </div>

    @php
        $mappedCount = collect($items->items())->filter(fn($i) => $i->kfa_code)->count();
        $bpjsCount = collect($items->items())->filter(fn($i) => $i->bpjs_uuid)->count();
    @endphp

    <x-organisms.data-panel>
        <x-slot:filter>
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-[200px]">
                    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                        placeholder="Cari kode atau nama vaksin..." clearable />
                </div>
                <flux:select wire:model.live="filterSs" class="sm:w-44">
                    <flux:select.option value="">Semua Status SS</flux:select.option>
                    <flux:select.option value="terdaftar">Terkirim ke SS</flux:select.option>
                    <flux:select.option value="belum">Belum Terkirim</flux:select.option>
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
                    <span class="flex items-center gap-1.5 text-blue-600 dark:text-blue-400">
                        <span class="inline-block w-2 h-2 rounded-full bg-blue-400 dark:bg-blue-500"></span>
                        {{ $bpjsCount }} UUID BPJS
                    </span>
                    <span class="text-zinc-300 dark:text-primary-dark-600">/</span>
                    <span class="text-zinc-500 dark:text-primary-dark-400">{{ $items->count() }} di halaman ini</span>
                </div>
            </div>
        </x-slot:filter>

        <x-organisms.table>
            <x-slot:headings>
                <x-atoms.table-heading class="w-36">Kode Lokal</x-atoms.table-heading>
                <x-atoms.table-heading>Nama Vaksin</x-atoms.table-heading>
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
                                    @if ($item->immunization_reason_name || $item->immunization_routine_timing_name)
                                        <div class="mt-1.5 flex flex-wrap gap-1">
                                            @if ($item->immunization_reason_name)
                                                <span
                                                    class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/50">
                                                    Alasan: {{ $item->immunization_reason_name }}
                                                </span>
                                            @else
                                                <span
                                                    class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400 border border-amber-200 dark:border-amber-800/50">
                                                    Alasan belum dipilih
                                                </span>
                                            @endif
                                            @if ($item->immunization_routine_timing_name)
                                                <span
                                                    class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-sky-50 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400 border border-sky-200 dark:border-sky-800/50">
                                                    Jadwal: {{ $item->immunization_routine_timing_name }}
                                                </span>
                                            @endif
                                        </div>
                                    @elseif ($item->kfa_code)
                                        <div class="mt-1.5">
                                            <span
                                                class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400 border border-amber-200 dark:border-amber-800/50">
                                                Alasan imunisasi belum dipilih
                                            </span>
                                        </div>
                                    @endif
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
                            @if ($item->bpjs_uuid)
                                <x-atoms.button wire:click="viewBpjsDetail('{{ $item->kode_brng }}')" size="sm"
                                    icon="eye" variant="ghost" tooltip="Detail UUID BPJS" />
                                <x-atoms.button
                                    wire:click="confirmDeleteBpjs('{{ $item->kode_brng }}', '{{ addslashes($item->nama_brng) }}')"
                                    size="sm" icon="trash" variant="ghost" tooltip="Hapus UUID BPJS"
                                    class="text-red-500" />
                            @else
                                <x-atoms.button
                                    wire:click="generateBpjsUuid('{{ $item->kode_brng }}', '{{ addslashes($item->nama_brng) }}')"
                                    size="sm" icon="sparkles" variant="ghost" tooltip="Generate UUID BPJS" />
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
                                    data vaksin</p>
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
    <x-organisms.modal wire:model="showModal" maxWidth="3xl" title="Form Mapping Vaksin">
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

            {{-- Immunization Fields --}}
            <div class="pt-4 mt-2 border-t border-zinc-200 dark:border-primary-dark-700">
                <p class="text-xs font-bold uppercase tracking-widest text-zinc-400 dark:text-primary-dark-500 mb-3">
                    Imunisasi</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <flux:label class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">
                                Alasan Imunisasi <span class="text-red-500">*</span>
                            </flux:label>
                            <x-atoms.button
                                wire:click="openDictionaryModal('kemkes', 'immunization-reason', 'Alasan Imunisasi')"
                                size="xs" variant="ghost" class="h-6 px-2 text-sky-600 hover:bg-sky-50">
                                <flux:icon name="plus" class="w-3 h-3 mr-1" /> Tambah
                            </x-atoms.button>
                        </div>
                        <flux:select wire:model="immunization_reason_code" placeholder="Pilih alasan imunisasi..."
                            searchable>
                            @foreach ($this->getDictionary('kemkes', 'immunization-reason') as $d)
                                <flux:select.option value="{{ $d->system_code }}">{{ $d->system_code }} -
                                    {{ $d->system_term }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        @error('immunization_reason_code')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <flux:label class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">
                                Jadwal Rutin <span class="text-zinc-400 text-xs font-normal">(opsional)</span>
                            </flux:label>
                            <x-atoms.button
                                wire:click="openDictionaryModal('kemkes', 'immunization-routine-timing', 'Jadwal Rutin Imunisasi')"
                                size="xs" variant="ghost" class="h-6 px-2 text-sky-600 hover:bg-sky-50">
                                <flux:icon name="plus" class="w-3 h-3 mr-1" /> Tambah
                            </x-atoms.button>
                        </div>
                        <flux:select wire:model="immunization_routine_timing_code"
                            placeholder="Pilih jadwal rutin (opsional)..." searchable>
                            <flux:select.option value="">[Tidak Ada]</flux:select.option>
                            @foreach ($this->getDictionary('kemkes', 'immunization-routine-timing') as $d)
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
                        <h4 class="text-xs font-semibold uppercase text-zinc-400">Identitas</h4>
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
    <x-organisms.modal wire:model="showSyncModal" title="Sync Semua UUID BPJS Vaksin" maxWidth="md">
        <div class="space-y-4">
            <p class="text-sm text-zinc-600 dark:text-primary-dark-300">
                Proses ini akan men-generate UUID BPJS untuk semua vaksin yang belum terdaftar.
                Saat ini terdapat <strong>{{ number_format($unsyncedBpjs) }}</strong> vaksin belum memiliki UUID BPJS.
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
    <x-organisms.modal wire:model="showBpjsDetailModal" title="Detail UUID BPJS Vaksin" maxWidth="md">
        @if ($selectedBpjsItem)
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500 dark:text-primary-dark-400 shrink-0">Nama Vaksin</dt>
                    <dd class="font-medium text-zinc-800 dark:text-primary-dark-100 text-right">
                        {{ $selectedBpjsItem->name }}
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500 dark:text-primary-dark-400 shrink-0">Kode Lokal</dt>
                    <dd class="font-mono text-xs text-zinc-700 dark:text-primary-dark-200">
                        {{ $selectedBpjsItem->local_code }}
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500 dark:text-primary-dark-400 shrink-0">UUID BPJS</dt>
                    <dd class="font-mono text-xs break-all text-blue-700 dark:text-blue-400">
                        {{ $selectedBpjsItem->id }}
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500 dark:text-primary-dark-400 shrink-0">Dibuat</dt>
                    <dd class="text-zinc-600 dark:text-primary-dark-300">
                        {{ $selectedBpjsItem->created_at?->format('d M Y H:i') }}
                    </dd>
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
            UUID BPJS untuk vaksin <strong>{{ $deleteBpjsName }}</strong> akan dihapus secara permanen.
            Data ini tidak dapat dipulihkan.
        </p>
        <x-slot:footer>
            <x-atoms.button variant="ghost" wire:click="$set('showDeleteBpjsModal', false)">Batal</x-atoms.button>
            <x-atoms.button variant="danger" wire:click="deleteBpjs" icon="trash">Hapus UUID</x-atoms.button>
        </x-slot:footer>
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
