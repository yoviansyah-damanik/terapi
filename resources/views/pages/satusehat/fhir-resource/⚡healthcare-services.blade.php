<?php

use App\Models\Simrs\Poliklinik;
use App\Models\SatuSehat\SatuSehatHealthcareService;
use App\Services\SatuSehat\Resources\HealthcareServiceService;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('Satu Sehat — Healthcare Service')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterStatus = '';

    // Modal detail & update
    public bool $showDetailModal = false;
    public ?SatuSehatHealthcareService $selectedService = null;

    // Modal konfirmasi hapus
    public bool $showDeleteModal = false;
    public ?string $deletePoliId = null;
    public string $deletePoliName = '';

    // Modal tarik dari Satu Sehat
    public bool $showPullModal = false;
    public ?string $pullPoliId = null;
    public string $pullPoliName = '';
    public string $pullSearch = '';
    public array $pullResults = [];
    public ?string $pullError = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }
    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function viewDetail(string $kdPoli): void
    {
        $this->selectedService = SatuSehatHealthcareService::findByIdentifier($kdPoli);
        $this->showDetailModal = true;
    }

    /** Kirim poliklinik baru ke Satu Sehat — hanya jika belum punya IHS number */
    public function sendToSatuSehat(string $kdPoli, string $nmPoli): void
    {
        $existing = SatuSehatHealthcareService::findByIdentifier($kdPoli);

        if ($existing?->ihs_number) {
            $this->toastWarning("Poliklinik ini sudah memiliki IHS Number: {$existing->ihs_number}. Gunakan Tarik dari SS untuk mengganti mapping.");
            return;
        }

        try {
            $response = app(HealthcareServiceService::class)->createHealthcareService($nmPoli, $kdPoli);
        } catch (\Exception $e) {
            $this->toastError('Gagal menghubungi Satu Sehat: ' . $e->getMessage());
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

    /** Update HealthcareService ke Satu Sehat */
    public function updateService(): void
    {
        if (!$this->selectedService?->ihs_number) {
            return;
        }

        try {
            $response = app(HealthcareServiceService::class)->updateHealthcareService($this->selectedService->ihs_number, $this->selectedService->name, $this->selectedService->identifier, true);
        } catch (\Exception $e) {
            $this->toastError('Gagal menghubungi Satu Sehat: ' . $e->getMessage());
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

    public function confirmDelete(string $kdPoli, string $nmPoli): void
    {
        $this->deletePoliId = $kdPoli;
        $this->deletePoliName = $nmPoli;
        $this->showDeleteModal = true;
    }

    public function deleteMapping(): void
    {
        if (!$this->deletePoliId) {
            return;
        }

        $service = SatuSehatHealthcareService::findByIdentifier($this->deletePoliId);

        if ($service) {
            if ($service->ihs_number) {
                try {
                    app(HealthcareServiceService::class)->updateHealthcareService($service->ihs_number, $service->name, $service->identifier, false);
                } catch (\Exception $e) {
                    $this->toastError('Gagal menonaktifkan di Satu Sehat: ' . $e->getMessage());
                    $this->showDeleteModal = false;
                    $this->reset(['deletePoliId', 'deletePoliName']);
                    return;
                }
            }

            $service->delete();
        }

        $this->showDeleteModal = false;
        $this->reset(['deletePoliId', 'deletePoliName']);
        $this->toastSuccess('HealthcareService dinonaktifkan di Satu Sehat dan mapping lokal dihapus.');
    }

    public function cancelDelete(): void
    {
        $this->showDeleteModal = false;
        $this->reset(['deletePoliId', 'deletePoliName']);
    }

    public function openPullModal(string $kdPoli, string $nmPoli): void
    {
        $this->pullPoliId = $kdPoli;
        $this->pullPoliName = $nmPoli;
        $this->pullSearch = $nmPoli;
        $this->pullResults = [];
        $this->pullError = null;
        $this->showPullModal = true;
    }

    public function searchService(): void
    {
        if (blank($this->pullSearch)) {
            return;
        }

        $this->pullResults = [];
        $this->pullError = null;

        try {
            $response = app(HealthcareServiceService::class)->searchByName(trim($this->pullSearch));

            if (!$response->success) {
                $this->pullError = $response->error ?? 'Pencarian gagal.';
                return;
            }

            $resources = $response->getResources();

            if (empty($resources)) {
                $this->pullError = 'Tidak ada HealthcareService ditemukan dengan nama tersebut.';
                return;
            }

            $this->pullResults = $resources;
        } catch (\Exception $e) {
            $this->pullError = 'Gagal menghubungi Satu Sehat: ' . $e->getMessage();
        }
    }

    public function selectService(string $ihsNumber): void
    {
        if (!$this->pullPoliId) {
            return;
        }

        $resource = collect($this->pullResults)->firstWhere('id', $ihsNumber);
        if (!$resource) {
            return;
        }

        $existingByIhs = SatuSehatHealthcareService::where('ihs_number', $ihsNumber)->first();
        $existingByPoli = SatuSehatHealthcareService::findByIdentifier($this->pullPoliId);

        if ($existingByIhs && ($existingByPoli === null || $existingByIhs->id !== $existingByPoli->id)) {
            $label = $existingByIhs->identifier ? "poliklinik '{$existingByIhs->identifier}'" : 'entri lain';
            $this->pullError = "IHS Number {$ihsNumber} sudah terdaftar untuk {$label}.";
            return;
        }

        SatuSehatHealthcareService::updateOrCreate(
            ['identifier' => $this->pullPoliId],
            [
                'ihs_number' => $ihsNumber,
                'name' => $resource['name'] ?? $this->pullPoliName,
                'status' => $resource['active'] ?? true ? 'active' : 'inactive',
                'raw_response' => $resource,
                'synced_at' => now(),
            ],
        );

        $this->showPullModal = false;
        $this->reset(['pullPoliId', 'pullPoliName', 'pullSearch', 'pullResults', 'pullError']);
        $this->toastSuccess("HealthcareService berhasil ditarik dari Satu Sehat. IHS: {$ihsNumber}");
    }

    public function with(): array
    {
        $allServices = SatuSehatHealthcareService::whereNotNull('identifier')->get()->keyBy('identifier');
        $mappedPoliIds = $allServices->keys()->toArray();

        $polikliniks = collect();
        $simrsError = false;
        $totalPoli = 0;

        try {
            $query = Poliklinik::active()->search($this->search);

            if ($this->filterStatus === 'mapped') {
                $query->whereIn('kd_poli', $mappedPoliIds);
            } elseif ($this->filterStatus === 'unmapped') {
                $query->whereNotIn('kd_poli', $mappedPoliIds);
            }

            $totalPoli = Poliklinik::active()->count();
            $polikliniks = $query->orderBy('nm_poli')->paginate(25);
        } catch (\Exception) {
            $simrsError = true;
        }

        return [
            'polikliniks' => $polikliniks,
            'allServices' => $allServices,
            'totalPoli' => $totalPoli,
            'totalMapped' => $allServices->count(),
            'simrsError' => $simrsError,
        ];
    }
}; ?>

<div>
    <x-ui.page-header title="Satu Sehat — Healthcare Service"
        subtitle="Mapping poliklinik SIMRS ke HealthcareService Satu Sehat" />

    @if ($simrsError)
        <div
            class="flex items-center gap-3 p-4 mb-6 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
            <flux:icon name="exclamation-triangle" class="w-5 h-5 text-red-500 shrink-0" />
            <p class="text-sm text-red-700 dark:text-red-300">Koneksi ke database SIMRS gagal. Data poliklinik tidak
                dapat ditampilkan.</p>
        </div>
    @endif

    {{-- Toolbar --}}
    <div class="flex flex-col gap-3 mb-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center flex-1">
            <div class="flex-1 max-w-sm">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                    placeholder="Cari kode atau nama poliklinik..." clearable />
            </div>
            <flux:select wire:model.live="filterStatus" class="sm:w-48">
                <flux:select.option value="">Semua Status</flux:select.option>
                <flux:select.option value="mapped">Sudah Dipetakan</flux:select.option>
                <flux:select.option value="unmapped">Belum Dipetakan</flux:select.option>
            </flux:select>
        </div>

        <div
            class="hidden sm:flex items-center gap-2.5 px-3.5 py-2.5 text-xs font-medium bg-white dark:bg-primary-dark-800 rounded-xl border border-zinc-200 dark:border-primary-dark-700 whitespace-nowrap shadow-sm">
            <span class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400">
                <span class="inline-block w-2 h-2 rounded-full bg-emerald-400"></span>
                {{ $totalMapped }} terpetakan
            </span>
            <span class="text-zinc-300 dark:text-primary-dark-600">/</span>
            <span class="text-zinc-500 dark:text-primary-dark-400">{{ $totalPoli }} poliklinik</span>
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
                            Nama Poliklinik</th>
                        <th
                            class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500">
                            Healthcare Service</th>
                        <th
                            class="px-5 py-3.5 text-xs font-semibold tracking-wider text-center uppercase text-zinc-400 dark:text-primary-dark-500 w-32">
                            Status</th>
                        <th
                            class="px-5 py-3.5 text-xs font-semibold tracking-wider text-center uppercase text-zinc-400 dark:text-primary-dark-500 w-40">
                            Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100/80 dark:divide-primary-dark-700/40">
                    @forelse ($polikliniks as $poli)
                        @php $svc = $allServices[$poli->kd_poli] ?? null; @endphp
                        <tr :key="$poli->kd_poli"
                            class="group transition-colors hover:bg-zinc-50/60 dark:hover:bg-primary-dark-700/20">
                            <td class="px-5 py-4 whitespace-nowrap">
                                <span
                                    class="inline-block font-mono text-xs font-bold px-2 py-1 rounded-md
                                    bg-zinc-100 dark:bg-primary-dark-700 text-zinc-600 dark:text-primary-dark-300
                                    ring-1 ring-zinc-200 dark:ring-primary-dark-600">
                                    {{ $poli->kd_poli }}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <p class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">
                                    {{ $poli->nm_poli }}
                                </p>
                            </td>
                            <td class="px-5 py-4">
                                @if ($svc)
                                    <div class="flex items-start gap-2.5">
                                        <span
                                            class="mt-1 w-2 h-2 rounded-full bg-emerald-400 dark:bg-emerald-500 shrink-0 ring-2 ring-emerald-100 dark:ring-emerald-900/50"></span>
                                        <div class="min-w-0">
                                            <p
                                                class="font-mono text-xs font-bold text-emerald-700 dark:text-emerald-400">
                                                {{ $svc->ihs_number }}</p>
                                            <p
                                                class="mt-0.5 text-xs text-zinc-500 dark:text-primary-dark-400 leading-snug">
                                                {{ $svc->name }}</p>
                                        </div>
                                    </div>
                                @else
                                    <div class="flex items-center gap-2 text-zinc-400 dark:text-primary-dark-500">
                                        <span class="w-2 h-2 rounded-full bg-zinc-200 dark:bg-primary-dark-600"></span>
                                        <span class="text-xs italic">Belum dipetakan</span>
                                    </div>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-center">
                                @if ($svc)
                                    <flux:badge :color="$svc->status === 'active' ? 'green' : 'zinc'" size="sm">
                                        {{ $svc->status === 'active' ? 'Aktif' : 'Nonaktif' }}
                                    </flux:badge>
                                @else
                                    <span class="text-xs text-zinc-300 dark:text-primary-dark-600">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-center whitespace-nowrap">
                                <div class="flex items-center justify-center gap-1.5">
                                    @if (!$svc)
                                        <x-atoms.button
                                            wire:click="sendToSatuSehat('{{ $poli->kd_poli }}', '{{ addslashes($poli->nm_poli) }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="sendToSatuSehat('{{ $poli->kd_poli }}', '{{ addslashes($poli->nm_poli) }}')"
                                            size="sm" variant="ghost" icon="paper-airplane"
                                            title="Buat baru di Satu Sehat" />
                                        <x-atoms.button
                                            wire:click="openPullModal('{{ $poli->kd_poli }}', '{{ addslashes($poli->nm_poli) }}')"
                                            size="sm" variant="ghost" icon="arrow-down-tray"
                                            title="Cari dan tarik dari Satu Sehat" />
                                    @else
                                        <x-atoms.button variant="ghost" wire:click="viewDetail('{{ $poli->kd_poli }}')"
                                            size="sm" icon="eye" title="Lihat detail" />
                                        <x-atoms.button variant="ghost"
                                            wire:click="confirmDelete('{{ $poli->kd_poli }}', '{{ addslashes($poli->nm_poli) }}')"
                                            size="sm" icon="trash" title="Hapus Mapping" />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-16 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <div
                                        class="flex items-center justify-center w-14 h-14 rounded-2xl bg-zinc-100 dark:bg-primary-dark-700">
                                        <flux:icon name="building-office-2"
                                            class="w-7 h-7 text-zinc-300 dark:text-primary-dark-500" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-zinc-500 dark:text-primary-dark-400">
                                            {{ $simrsError ? 'Koneksi SIMRS gagal' : 'Tidak ada poliklinik ditemukan' }}
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

        @if ($polikliniks instanceof \Illuminate\Pagination\LengthAwarePaginator && $polikliniks->hasPages())
            <div
                class="px-5 py-4 border-t border-zinc-100 dark:border-primary-dark-700/60 bg-zinc-50/50 dark:bg-primary-dark-900/20">
                {{ $polikliniks->links() }}
            </div>
        @endif
    </div>

    {{-- Modal Detail --}}
    <x-organisms.modal wire:model="showDetailModal" maxWidth="xl" title="">
        @if ($selectedService)
            <div class="space-y-5">
                <div class="flex items-start gap-4 pb-4 border-b border-zinc-200 dark:border-primary-dark-700">
                    <div
                        class="flex items-center justify-center flex-shrink-0 w-12 h-12 rounded-xl bg-primary-100 dark:bg-primary-900/30">
                        <flux:icon name="building-office-2" class="w-6 h-6 text-primary-600 dark:text-primary-400" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <h2 class="text-base font-bold text-zinc-900 dark:text-white truncate">
                            {{ $selectedService->name }}</h2>
                        <div class="flex items-center gap-2 mt-1.5">
                            <flux:badge :color="$selectedService->status === 'active' ? 'green' : 'zinc'"
                                size="sm">
                                {{ $selectedService->status === 'active' ? 'Aktif' : 'Nonaktif' }}
                            </flux:badge>
                        </div>
                    </div>
                </div>

                <div>
                    <p
                        class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-primary-dark-500">
                        Identitas</p>
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                        <div>
                            <dt class="text-xs text-zinc-500 dark:text-primary-dark-400">IHS Number</dt>
                            <dd class="mt-0.5 font-mono font-bold text-emerald-700 dark:text-emerald-400">
                                {{ $selectedService->ihs_number }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-500 dark:text-primary-dark-400">Kode Poliklinik</dt>
                            <dd class="mt-0.5 font-mono text-zinc-700 dark:text-primary-dark-300">
                                {{ $selectedService->identifier ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-500 dark:text-primary-dark-400">Disinkron</dt>
                            <dd class="mt-0.5 text-zinc-700 dark:text-primary-dark-300">
                                {{ $selectedService->synced_at?->format('d/m/Y H:i') ?? '-' }}</dd>
                        </div>
                    </dl>
                </div>

                @if ($selectedService->raw_response)
                    <details class="group">
                        <summary
                            class="cursor-pointer text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-primary-dark-500
                                   hover:text-zinc-600 dark:hover:text-primary-dark-300 transition-colors select-none">
                            <span class="group-open:hidden">Lihat FHIR Resource</span>
                            <span class="hidden group-open:inline">Sembunyikan</span>
                        </summary>
                        <x-atoms.code-block language="json" maxHeight="max-h-52" class="mt-2">{{ json_encode($selectedService->raw_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</x-atoms.code-block>
                    </details>
                @endif

                <div class="flex justify-between items-center pt-1">
                    <x-atoms.button wire:click="updateService" wire:loading.attr="disabled"
                        wire:target="updateService" variant="primary" icon="arrow-path">
                        <span wire:loading.remove wire:target="updateService">Update ke SS</span>
                        <span wire:loading wire:target="updateService">Memperbarui...</span>
                    </x-atoms.button>
                    <x-atoms.button variant="ghost" wire:click="$set('showDetailModal', false)">Tutup</x-atoms.button>
                </div>
            </div>
        @endif
    
    </x-organisms.modal>

    {{-- Modal Tarik dari Satu Sehat --}}
    <x-organisms.modal wire:model="showPullModal" maxWidth="xl" title="">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Tarik Healthcare Service dari Satu Sehat</flux:heading>
                @if ($pullPoliId)
                    <flux:text class="mt-0.5">
                        Poliklinik: <span class="font-semibold">{{ $pullPoliName }}</span>
                        <span class="font-mono text-xs text-zinc-400">({{ $pullPoliId }})</span>
                    </flux:text>
                @endif
            </div>

            <div class="flex gap-2">
                <div class="flex-1">
                    <flux:input wire:model="pullSearch" wire:keydown.enter="searchService"
                        placeholder="Nama healthcare service di Satu Sehat..." icon="magnifying-glass" />
                </div>
                <x-atoms.button wire:click="searchService" wire:loading.attr="disabled" wire:target="searchService"
                    variant="primary" icon="magnifying-glass">
                    <span wire:loading.remove wire:target="searchService">Cari</span>
                    <span wire:loading wire:target="searchService">Mencari...</span>
                </x-atoms.button>
            </div>

            @if ($pullError)
                <div
                    class="flex items-center gap-2.5 px-3.5 py-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-sm text-red-700 dark:text-red-300">
                    <flux:icon name="exclamation-circle" class="w-4 h-4 shrink-0" />
                    {{ $pullError }}
                </div>
            @endif

            @if (!empty($pullResults))
                <div class="rounded-xl border border-zinc-200 dark:border-primary-dark-700 overflow-hidden">
                    <div
                        class="px-4 py-2.5 bg-zinc-50 dark:bg-primary-dark-900/40 border-b border-zinc-200 dark:border-primary-dark-700">
                        <p
                            class="text-xs font-semibold text-zinc-500 dark:text-primary-dark-400 uppercase tracking-wide">
                            {{ count($pullResults) }} hasil ditemukan</p>
                    </div>
                    <ul class="divide-y divide-zinc-100 dark:divide-primary-dark-700/50 max-h-72 overflow-y-auto">
                        @foreach ($pullResults as $resource)
                            @php
                                $resIhs = $resource['id'] ?? '-';
                                $resNama = $resource['name'] ?? '-';
                                $resActive = (bool) ($resource['active'] ?? true);
                            @endphp
                            <li wire:click="selectService('{{ $resIhs }}')"
                                wire:loading.class="opacity-50 pointer-events-none" wire:target="selectService"
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
                                        <flux:badge :color="$resActive ? 'green' : 'zinc'" size="sm">
                                            {{ $resActive ? 'Aktif' : 'Nonaktif' }}
                                        </flux:badge>
                                    </div>
                                    <p
                                        class="mt-0.5 text-sm font-medium text-zinc-800 dark:text-primary-dark-200 truncate">
                                        {{ $resNama }}
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
                    <p class="text-sm">Ketik nama healthcare service lalu klik <strong>Cari</strong></p>
                </div>
            @endif

            
        <x-slot:footer>
            <div class="flex justify-end pt-1">
                <x-atoms.button variant="ghost" wire:click="$set('showPullModal', false)">Batal</x-atoms.button>
            </div>
        </x-slot:footer>
    </div>
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
                    <flux:text class="mt-0.5">Healthcare Service untuk <strong>{{ $deletePoliName }}</strong> akan
                        dihapus dari Satu Sehat.</flux:text>
                </div>
            </div>

            <div
                class="p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-xs text-amber-700 dark:text-amber-300">
                <flux:icon name="exclamation-triangle" class="inline w-3.5 h-3.5 mr-1" />
                Healthcare Service akan dinonaktifkan di platform Satu Sehat, kemudian mapping lokal akan dihapus.
            </div>

            
        <x-slot:footer>
            <div class="flex justify-end gap-3">
                <x-atoms.button variant="ghost" wire:click="cancelDelete">Batal</x-atoms.button>
                <x-atoms.button variant="danger" wire:click="deleteMapping" icon="trash">Hapus</x-atoms.button>
            </div>
        </x-slot:footer>
    </div>
    </x-organisms.modal>
</div>
