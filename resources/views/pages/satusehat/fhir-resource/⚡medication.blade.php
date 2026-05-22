<?php

use App\Models\SatuSehat\SatuSehatMedication;
use App\Models\Mapping\MedicationMap;
use App\Services\SatuSehat\Resources\MedicationService;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('Satu Sehat - FHIR Medication')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterStatus = '';

    #[Url]
    public int $perPage = 25;

    public bool $showDetailModal = false;
    public ?SatuSehatMedication $selected = null;

    // Modal sync
    public bool $showSyncModal = false;
    public string $syncSearch = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }
    public function updatedSyncSearch(): void {}

    public function showDetail(string $id): void
    {
        $this->selected = SatuSehatMedication::find($id);
        $this->showDetailModal = true;
    }

    public function closeDetail(): void
    {
        $this->showDetailModal = false;
        $this->selected = null;
    }

    /** Sync satu obat dari MedicationMap → SatuSehatMedication via MedicationService */
    public function syncMedication(string $localCode): void
    {
        $map = MedicationMap::where('local_code', $localCode)->first();
        if (!$map || !$map->kfa_code) {
            $this->toastError('Obat belum memiliki mapping KFA');
            return;
        }

        try {
            $service = app(MedicationService::class);

            // Bentuk sediaan
            $form = $map->form_code
                ? [
                    'coding' => [
                        [
                            'system' => 'http://terminology.kemkes.go.id/CodeSystem/medication-form',
                            'code' => $map->form_code,
                            'display' => $map->form_name,
                        ],
                    ],
                ]
                : null;

            // Ingredient / kekuatan
            $ingredient =
                $map->numerator_code && $map->denominator_code
                    ? [
                        [
                            'itemCodeableConcept' => [
                                'coding' => [
                                    [
                                        'system' => $map->system_url ?? 'http://sys-ids.kemkes.go.id/kfa',
                                        'code' => $map->kfa_code,
                                        'display' => $map->kfa_name,
                                    ],
                                ],
                            ],
                            'strength' => [
                                'numerator' => [
                                    'value' => 1,
                                    'system' => 'http://unitsofmeasure.org',
                                    'code' => $map->numerator_code,
                                    'unit' => $map->numerator_name ?? $map->numerator_code,
                                ],
                                'denominator' => [
                                    'value' => 1,
                                    'system' => 'http://terminology.hl7.org/CodeSystem/v3-orderableDrugForm',
                                    'code' => $map->denominator_code,
                                    'unit' => $map->denominator_name ?? $map->denominator_code,
                                ],
                            ],
                        ],
                    ]
                    : null;

            // Extension medication type
            $extension = [
                [
                    'url' => 'https://fhir.kemkes.go.id/r4/StructureDefinition/MedicationType',
                    'valueCodeableConcept' => [
                        'coding' => [
                            [
                                'system' => 'http://terminology.kemkes.go.id/CodeSystem/medication-type',
                                'code' => 'NC',
                                'display' => 'Non-compound',
                            ],
                        ],
                    ],
                ],
            ];

            $existing = SatuSehatMedication::findByKfaCode($map->kfa_code);

            if ($existing?->ihs_number) {
                // Update
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
                // Create
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
            $this->showSyncModal = false;
            $this->syncSearch = '';
        } catch (\Exception $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    public function with(): array
    {
        $medications = SatuSehatMedication::query()
            ->when(
                $this->search,
                fn($q) => $q
                    ->where('ihs_number', 'like', "%{$this->search}%")
                    ->orWhere('kfa_code', 'like', "%{$this->search}%")
                    ->orWhere('kfa_display', 'like', "%{$this->search}%"),
            )
            ->when($this->filterStatus, fn($q) => $q->where('status', $this->filterStatus))
            ->latest('synced_at')
            ->paginate($this->perPage);

        // Untuk modal sync: obat lokal yang sudah mapping KFA
        $syncItems = collect();
        if ($this->showSyncModal) {
            try {
                $syncedKfaCodes = SatuSehatMedication::pluck('kfa_code')->toArray();

                $syncItems = MedicationMap::query()
                    ->when(
                        $this->syncSearch,
                        fn($q) => $q
                            ->where('kfa_code', 'like', "%{$this->syncSearch}%")
                            ->orWhere('kfa_name', 'like', "%{$this->syncSearch}%")
                            ->orWhere('local_code', 'like', "%{$this->syncSearch}%"),
                    )
                    ->limit(50)
                    ->get()
                    ->map(
                        fn($m) => [
                            'local_code' => $m->local_code,
                            'kfa_code' => $m->kfa_code,
                            'kfa_name' => $m->kfa_name,
                            'system_url' => $m->system_url,
                            'form_code' => $m->form_code,
                            'form_name' => $m->form_name,
                            'route_code' => $m->route_code,
                            'route_name' => $m->route_name,
                            'numerator_code' => $m->numerator_code,
                            'denominator_code' => $m->denominator_code,
                            'is_synced' => in_array($m->kfa_code, $syncedKfaCodes),
                        ],
                    );
            } catch (\Exception) {
            }
        }

        return [
            'medications' => $medications,
            'totalCount' => SatuSehatMedication::count(),
            'syncItems' => $syncItems,
            'unsyncedCount' => MedicationMap::whereNotNull('kfa_code')
                ->whereNotIn('kfa_code', SatuSehatMedication::pluck('kfa_code')->toArray())
                ->count(),
        ];
    }
}; ?>

<div>
    {{-- Header --}}
    <x-ui.page-header title="Satu Sehat — FHIR Medication"
        subtitle="Medication yang telah dikirimkan ke platform Satu Sehat">
        <x-slot name="actions">
            <x-atoms.button variant="primary" icon="arrow-up-tray" wire:click="$set('showSyncModal', true)">
                Kirim Medication
            </x-atoms.button>
        </x-slot>
    </x-ui.page-header>

    {{-- Stats --}}
    <div class="grid grid-cols-2 gap-4 mb-6 md:grid-cols-4">
        <div class="p-4 bg-white rounded-xl shadow dark:bg-primary-dark-800">
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-emerald-100 dark:bg-emerald-900/40">
                    <flux:icon name="check-circle" class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                </div>
                <div>
                    <p class="text-xs text-zinc-500 dark:text-primary-dark-400">Terkirim</p>
                    <p class="text-lg font-bold text-zinc-900 dark:text-primary-dark-100">
                        {{ number_format($totalCount) }}</p>
                </div>
            </div>
        </div>
        <div class="p-4 bg-white rounded-xl shadow dark:bg-primary-dark-800">
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-amber-100 dark:bg-amber-900/40">
                    <flux:icon name="clock" class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                </div>
                <div>
                    <p class="text-xs text-zinc-500 dark:text-primary-dark-400">Belum Dikirim</p>
                    <p class="text-lg font-bold text-zinc-900 dark:text-primary-dark-100">
                        {{ number_format($unsyncedCount) }}</p>
                </div>
            </div>
        </div>
        <div class="p-4 bg-white rounded-xl shadow dark:bg-primary-dark-800">
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-blue-100 dark:bg-blue-900/40">
                    <flux:icon name="beaker" class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <p class="text-xs text-zinc-500 dark:text-primary-dark-400">Status Active</p>
                    <p class="text-lg font-bold text-zinc-900 dark:text-primary-dark-100">
                        {{ number_format(SatuSehatMedication::where('status', 'active')->count()) }}
                    </p>
                </div>
            </div>
        </div>
        <div class="p-4 bg-white rounded-xl shadow dark:bg-primary-dark-800">
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-zinc-100 dark:bg-zinc-700/40">
                    <flux:icon name="x-circle" class="w-5 h-5 text-zinc-500 dark:text-zinc-400" />
                </div>
                <div>
                    <p class="text-xs text-zinc-500 dark:text-primary-dark-400">Status Inactive</p>
                    <p class="text-lg font-bold text-zinc-900 dark:text-primary-dark-100">
                        {{ number_format(SatuSehatMedication::where('status', 'inactive')->count()) }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter --}}
    <div class="p-4 mb-4 bg-white rounded-xl shadow dark:bg-primary-dark-800">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <flux:input wire:model.live.debounce.300ms="search"
                    placeholder="Cari IHS Number, KFA Code, atau nama obat..." icon="magnifying-glass" clearable />
            </div>
            <flux:select wire:model.live="filterStatus">
                <flux:select.option value="">Semua Status</flux:select.option>
                <flux:select.option value="active">Active</flux:select.option>
                <flux:select.option value="inactive">Inactive</flux:select.option>
                <flux:select.option value="entered-in-error">Entered In Error</flux:select.option>
            </flux:select>
        </div>
    </div>

    {{-- Tabel --}}
    <div class="overflow-hidden bg-white rounded-xl shadow dark:bg-primary-dark-800">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-primary-dark-700">
                <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                    <tr>
                        <th
                            class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            IHS Number</th>
                        <th
                            class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Obat / KFA</th>
                        <th
                            class="hidden px-4 py-3 text-xs font-medium tracking-wider text-left uppercase md:table-cell text-zinc-500 dark:text-primary-dark-400">
                            Bentuk Sediaan</th>
                        <th
                            class="hidden px-4 py-3 text-xs font-medium tracking-wider text-left uppercase lg:table-cell text-zinc-500 dark:text-primary-dark-400">
                            Tipe</th>
                        <th
                            class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Status</th>
                        <th
                            class="hidden px-4 py-3 text-xs font-medium tracking-wider text-left uppercase xl:table-cell text-zinc-500 dark:text-primary-dark-400">
                            Terakhir Sync</th>
                        <th
                            class="px-4 py-3 text-xs font-medium tracking-wider text-center uppercase text-zinc-500 dark:text-primary-dark-400">
                            Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-zinc-200 dark:bg-primary-dark-800 dark:divide-primary-dark-700">
                    @forelse ($medications as $med)
                        <tr :key="$med->id" class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span
                                    class="font-mono text-sm font-medium text-emerald-600 dark:text-emerald-400">{{ $med->ihs_number }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">
                                    {{ $med->kfa_display ?? '-' }}</p>
                                <p class="font-mono text-xs text-zinc-400 dark:text-primary-dark-500 mt-0.5">
                                    {{ $med->kfa_code ?? '' }}</p>
                            </td>
                            <td class="hidden px-4 py-3 md:table-cell">
                                <span
                                    class="text-sm text-zinc-600 dark:text-primary-dark-400">{{ $med->form_display ?? '-' }}</span>
                            </td>
                            <td class="hidden px-4 py-3 lg:table-cell">
                                <span
                                    class="text-xs text-zinc-500 dark:text-primary-dark-400">{{ $med->medication_type ?? '-' }}</span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @php
                                    $sc = match ($med->status) {
                                        'active' => 'green',
                                        'inactive' => 'zinc',
                                        'entered-in-error' => 'red',
                                        default => 'zinc',
                                    };
                                @endphp
                                <flux:badge :color="$sc" size="sm">{{ $med->status ?? '-' }}</flux:badge>
                            </td>
                            <td
                                class="hidden px-4 py-3 text-sm whitespace-nowrap xl:table-cell text-zinc-500 dark:text-primary-dark-400">
                                {{ $med->synced_at?->diffForHumans() ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <x-atoms.button variant="ghost" size="sm" icon="eye"
                                    wire:click="showDetail('{{ $med->id }}')" title="Lihat Detail" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-16 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <div
                                        class="flex items-center justify-center w-14 h-14 rounded-2xl bg-zinc-100 dark:bg-primary-dark-700">
                                        <flux:icon name="beaker"
                                            class="w-7 h-7 text-zinc-300 dark:text-primary-dark-500" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-zinc-500">Belum ada data Medication</p>
                                        <p class="mt-1 text-xs text-zinc-400">Tekan "Kirim Medication" untuk mengirimkan
                                            data obat ke Satu Sehat</p>
                                    </div>
                                    <x-atoms.button variant="primary" size="sm" icon="arrow-up-tray"
                                        wire:click="$set('showSyncModal', true)">
                                        Kirim Medication
                                    </x-atoms.button>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($medications->hasPages())
            <div class="px-4 py-4 border-t border-zinc-200 dark:border-primary-dark-700">
                {{ $medications->links() }}
            </div>
        @endif
    </div>

    {{-- Detail Modal --}}
    <x-organisms.modal wire:model="showDetailModal" maxWidth="3xl" title="">
        @if ($selected)
            <div class="space-y-5">
                <div class="flex items-center gap-4 pb-4 border-b border-zinc-200 dark:border-primary-dark-700">
                    <div
                        class="flex items-center justify-center w-12 h-12 rounded-xl bg-emerald-100 dark:bg-emerald-900/40 shrink-0">
                        <flux:icon name="beaker" class="w-6 h-6 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <h2 class="text-base font-bold text-zinc-900 dark:text-primary-dark-100 truncate">
                            {{ $selected->kfa_display ?? 'Medication' }}</h2>
                        <p class="font-mono text-sm text-emerald-600 dark:text-emerald-400">{{ $selected->ihs_number }}
                        </p>
                    </div>
                    <flux:badge :color="$selected->status === 'active' ? 'green' : 'zinc'" size="sm">
                        {{ $selected->status }}
                    </flux:badge>
                </div>

                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div class="p-4 space-y-2.5 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                        <h4 class="text-xs font-semibold uppercase text-zinc-400">Identitas Obat</h4>
                        <div class="flex gap-2">
                            <dt class="w-28 text-zinc-500 shrink-0">KFA Code</dt>
                            <dd class="font-mono text-zinc-900 dark:text-primary-dark-100">
                                {{ $selected->kfa_code ?? '-' }}</dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="w-28 text-zinc-500 shrink-0">Display</dt>
                            <dd class="text-zinc-900 dark:text-primary-dark-100">{{ $selected->kfa_display ?? '-' }}
                            </dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="w-28 text-zinc-500 shrink-0">Tipe</dt>
                            <dd class="text-zinc-900 dark:text-primary-dark-100">
                                {{ $selected->medication_type ?? '-' }}</dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="w-28 text-zinc-500 shrink-0">Identifier</dt>
                            <dd class="font-mono text-xs text-zinc-700 dark:text-primary-dark-300">
                                {{ $selected->identifier ?? '-' }}</dd>
                        </div>
                    </div>
                    <div class="p-4 space-y-2.5 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                        <h4 class="text-xs font-semibold uppercase text-zinc-400">Sediaan</h4>
                        <div class="flex gap-2">
                            <dt class="w-28 text-zinc-500 shrink-0">Form Code</dt>
                            <dd class="font-mono text-zinc-900 dark:text-primary-dark-100">
                                {{ $selected->form_code ?? '-' }}</dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="w-28 text-zinc-500 shrink-0">Form Display</dt>
                            <dd class="text-zinc-900 dark:text-primary-dark-100">{{ $selected->form_display ?? '-' }}
                            </dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="w-28 text-zinc-500 shrink-0">Terakhir Sync</dt>
                            <dd class="text-zinc-900 dark:text-primary-dark-100">
                                {{ $selected->synced_at?->format('d M Y H:i') ?? '-' }}</dd>
                        </div>
                    </div>
                </div>

                @if (!empty($selected->ingredient))
                    <div class="p-4 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                        <h4 class="text-xs font-semibold uppercase text-zinc-400 mb-3">Ingredient</h4>
                        <div class="space-y-1.5">
                            @foreach ($selected->ingredient as $ing)
                                <div class="flex items-center gap-3 text-sm">
                                    <span
                                        class="font-mono text-xs px-1.5 py-0.5 rounded bg-zinc-200 dark:bg-primary-dark-700 text-zinc-600 dark:text-zinc-300">
                                        {{ $ing['item']['coding'][0]['code'] ?? '-' }}
                                    </span>
                                    <span
                                        class="text-zinc-700 dark:text-primary-dark-200">{{ $ing['item']['coding'][0]['display'] ?? '-' }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($selected->raw_response)
                    <div>
                        <h4 class="text-xs font-semibold uppercase text-zinc-400 mb-2">FHIR Resource</h4>
                        <x-atoms.code-block language="json" maxHeight="max-h-52">{{ json_encode($selected->raw_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</x-atoms.code-block>
                    </div>
                @endif

                <div class="flex justify-end pt-2 border-t border-zinc-200 dark:border-primary-dark-700">
                    <x-atoms.button variant="ghost" wire:click="closeDetail">Tutup</x-atoms.button>
                </div>
            </div>
        @endif
    
    </x-organisms.modal>

    {{-- Modal Kirim Medication --}}
    <x-organisms.modal wire:model="showSyncModal" maxWidth="4xl" title="Kirim Medication ke Satu Sehat">
        <div class="space-y-4">
            <div>
                
                <flux:text class="mt-0.5">Pilih obat yang sudah di-mapping ke KFA untuk dikirimkan.</flux:text>
            </div>

            @if ($unsyncedCount > 0)
                <div
                    class="flex items-center gap-2 px-3 py-2 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
                    <flux:icon name="exclamation-triangle" class="w-4 h-4 text-amber-500 shrink-0" />
                    <p class="text-xs text-amber-700 dark:text-amber-300">
                        <strong>{{ $unsyncedCount }}</strong> obat sudah mapping KFA tapi belum dikirim ke Satu Sehat.
                    </p>
                </div>
            @endif

            <flux:input wire:model.live.debounce.300ms="syncSearch" icon="magnifying-glass"
                placeholder="Cari kode lokal, KFA code, atau nama obat..." clearable />

            <div class="space-y-2 max-h-[28rem] overflow-y-auto pr-1">
                @forelse($syncItems as $item)
                    <div
                        class="rounded-xl border {{ $item['is_synced'] ? 'border-emerald-200 dark:border-emerald-800/50 bg-emerald-50/50 dark:bg-emerald-900/10' : 'border-zinc-200 dark:border-primary-dark-700 bg-white dark:bg-primary-dark-800' }} p-4">
                        {{-- Header baris --}}
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-zinc-900 dark:text-primary-dark-100 leading-snug">
                                    {{ $item['kfa_name'] }}</p>
                                <div class="flex flex-wrap items-center gap-2 mt-1">
                                    <span
                                        class="font-mono text-xs font-bold px-1.5 py-0.5 rounded bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300">{{ $item['kfa_code'] }}</span>
                                    @if ($item['system_url'])
                                        <flux:badge size="sm"
                                            color="{{ str_contains($item['system_url'], 'kfa-v3') ? 'blue' : 'green' }}">
                                            {{ str_contains($item['system_url'], 'kfa-v3') ? 'Alkes' : 'Farmasi' }}
                                        </flux:badge>
                                    @endif
                                    <span class="text-xs text-zinc-400 font-mono">lokal:
                                        {{ $item['local_code'] }}</span>
                                </div>
                            </div>
                            <div class="shrink-0">
                                @if ($item['is_synced'])
                                    <flux:badge color="green" size="sm">✓ Terkirim</flux:badge>
                                @else
                                    <x-atoms.button size="sm" variant="primary" icon="arrow-up-tray"
                                        wire:click="syncMedication('{{ $item['local_code'] }}')">
                                        Kirim
                                    </x-atoms.button>
                                @endif
                            </div>
                        </div>

                        {{-- Detail field mapping --}}
                        <div class="grid grid-cols-2 gap-x-6 gap-y-1.5 text-xs sm:grid-cols-3">
                            <div class="flex items-center gap-1.5">
                                <span class="text-zinc-400 w-24 shrink-0">Form</span>
                                <span class="font-mono text-zinc-700 dark:text-primary-dark-300">
                                    {{ $item['form_code'] ?? '-' }}
                                    @if ($item['form_name'])
                                        <span class="text-zinc-400 font-sans">· {{ $item['form_name'] }}</span>
                                    @endif
                                </span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="text-zinc-400 w-24 shrink-0">Rute</span>
                                <span class="font-mono text-zinc-700 dark:text-primary-dark-300">
                                    {{ $item['route_code'] ?? '-' }}
                                    @if ($item['route_name'])
                                        <span class="text-zinc-400 font-sans">· {{ $item['route_name'] }}</span>
                                    @endif
                                </span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="text-zinc-400 w-24 shrink-0">Numerator</span>
                                <span
                                    class="font-mono text-zinc-700 dark:text-primary-dark-300">{{ $item['numerator_code'] ?? '-' }}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="text-zinc-400 w-24 shrink-0">Denominator</span>
                                <span
                                    class="font-mono text-zinc-700 dark:text-primary-dark-300">{{ $item['denominator_code'] ?? '-' }}</span>
                            </div>
                            <div class="flex items-center gap-1.5 sm:col-span-2">
                                <span class="text-zinc-400 w-24 shrink-0">System URL</span>
                                <span
                                    class="text-zinc-500 dark:text-primary-dark-400 truncate">{{ $item['system_url'] ?? '-' }}</span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="py-10 text-center text-sm text-zinc-400">
                        {{ $syncSearch ? 'Tidak ada obat yang cocok dengan pencarian' : 'Belum ada obat yang di-mapping ke KFA' }}
                    </div>
                @endforelse
            </div>

            <div class="flex items-center justify-between pt-2 border-t border-zinc-200 dark:border-primary-dark-700">
                <p class="text-xs text-zinc-400">
                    Obat belum mapping KFA?
                    <a href="{{ route('local.medication.medicine') }}" wire:navigate
                        class="text-primary-600 dark:text-primary-400 hover:underline">
                        Atur di halaman Obat Lokal →
                    </a>
                </p>
                <x-atoms.button variant="ghost" wire:click="$set('showSyncModal', false)">Tutup</x-atoms.button>
            </div>
        </div>
    
    </x-organisms.modal>
</div>
