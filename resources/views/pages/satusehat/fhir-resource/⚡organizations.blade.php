<?php

use App\Models\Simrs\Departemen;
use App\Models\SatuSehat\SatuSehatOrganization;
use App\Services\SatuSehat\Resources\OrganizationService;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('Satu Sehat - Organization')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterStatus = '';

    // Modal detail
    public bool $showDetailModal = false;
    public ?SatuSehatOrganization $selectedOrg = null;

    // Modal konfirmasi hapus
    public bool $showDeleteModal = false;
    public ?string $deleteDeptId = null;
    public string $deleteDeptName = '';

    // Modal tarik dari Satu Sehat
    public bool $showPullModal = false;
    public ?string $pullDeptId = null;
    public string $pullDeptName = '';
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

    /** Buka modal detail */
    public function viewDetail(string $depId): void
    {
        $this->selectedOrg = SatuSehatOrganization::where('identifier', $depId)->first();
        $this->showDetailModal = true;
    }

    /** Update organization ke Satu Sehat dengan status active = true */
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

        // Refresh agar modal menampilkan data terbaru
        $this->selectedOrg = $this->selectedOrg->fresh();

        $this->toastSuccess("Organization berhasil diperbarui di Satu Sehat. IHS: {$this->selectedOrg->ihs_number}");
    }

    /** Konfirmasi hapus */
    public function confirmDelete(string $depId, string $depNama): void
    {
        $this->deleteDeptId = $depId;
        $this->deleteDeptName = $depNama;
        $this->showDeleteModal = true;
    }

    public function deleteMapping(): void
    {
        if (!$this->deleteDeptId) {
            return;
        }

        $org = SatuSehatOrganization::where('identifier', $this->deleteDeptId)->first();

        if ($org) {
            // Nonaktifkan di Satu Sehat sebelum hapus mapping lokal
            if ($org->ihs_number) {
                try {
                    app(OrganizationService::class)->updateOrganization($org->ihs_number, $org->name, $org->identifier, false);
                } catch (\Exception $e) {
                    $this->toastError('Gagal menonaktifkan di Satu Sehat: ' . $e->getMessage());
                    $this->showDeleteModal = false;
                    $this->reset(['deleteDeptId', 'deleteDeptName']);
                    return;
                }
            }

            $org->delete();
        }

        $this->showDeleteModal = false;
        $this->reset(['deleteDeptId', 'deleteDeptName']);
        $this->toastSuccess('Organization dinonaktifkan di Satu Sehat dan mapping lokal dihapus.');
    }

    public function cancelDelete(): void
    {
        $this->showDeleteModal = false;
        $this->reset(['deleteDeptId', 'deleteDeptName']);
    }

    /** Buka modal pencarian organization dari Satu Sehat */
    public function openPullModal(string $depId, string $depNama): void
    {
        $this->pullDeptId = $depId;
        $this->pullDeptName = $depNama;
        $this->pullSearch = $depNama;
        $this->pullResults = [];
        $this->pullError = null;
        $this->showPullModal = true;
    }

    /** Cari organization berdasarkan nama di Satu Sehat */
    public function searchOrganization(): void
    {
        if (blank($this->pullSearch)) {
            return;
        }

        $this->pullResults = [];
        $this->pullError = null;

        try {
            $response = app(OrganizationService::class)->searchByName(trim($this->pullSearch));

            if (!$response->success) {
                $this->pullError = $response->error ?? 'Pencarian gagal.';
                return;
            }

            $resources = $response->getResources();

            if (empty($resources)) {
                $this->pullError = 'Tidak ada organization ditemukan dengan nama tersebut.';
                return;
            }

            $this->pullResults = $resources;
        } catch (\Exception $e) {
            $this->pullError = 'Gagal menghubungi Satu Sehat: ' . $e->getMessage();
        }
    }

    /** Pilih organization dari hasil pencarian dan simpan ke database */
    public function selectOrganization(string $ihsNumber): void
    {
        if (!$this->pullDeptId) {
            return;
        }

        $resource = collect($this->pullResults)->firstWhere('id', $ihsNumber);
        if (!$resource) {
            return;
        }

        // Cek konflik: IHS number sudah dipakai departemen lain
        $existingByIhs = SatuSehatOrganization::where('ihs_number', $ihsNumber)->first();
        $existingByDep = SatuSehatOrganization::where('identifier', $this->pullDeptId)->first();

        if ($existingByIhs && ($existingByDep === null || $existingByIhs->id !== $existingByDep->id)) {
            $label = $existingByIhs->identifier ? "departemen '{$existingByIhs->identifier}'" : 'entri lain';
            $this->pullError = "IHS Number {$ihsNumber} sudah terdaftar untuk {$label}.";
            return;
        }

        SatuSehatOrganization::updateOrCreate(
            ['identifier' => $this->pullDeptId],
            array_merge($this->extractOrgData($resource), [
                'ihs_number' => $ihsNumber,
                'raw_response' => $resource,
                'synced_at' => now(),
            ]),
        );

        $this->showPullModal = false;
        $this->reset(['pullDeptId', 'pullDeptName', 'pullSearch', 'pullResults', 'pullError']);
        $this->toastSuccess("Organization berhasil ditarik dari Satu Sehat. IHS: {$ihsNumber}");
    }

    /** Ekstrak field yang tersedia di tabel dari FHIR Organization resource */
    private function extractOrgData(array $resource): array
    {
        $partOfRef = $resource['partOf']['reference'] ?? null;
        $partOf = $partOfRef ? last(explode('/', $partOfRef)) : null;

        $status = $resource['active'] ?? true ? 'active' : 'inactive';

        return [
            'name' => $resource['name'] ?? null,
            'status' => $status,
            'part_of' => $partOf,
        ];
    }

    /**
     * Kirim department ke Satu Sehat via API dan simpan IHS number yang dikembalikan.
     * Hanya untuk departemen yang belum memiliki IHS number (belum terdaftar di Satu Sehat).
     */
    public function sendToSatuSehat(string $depId, string $depNama): void
    {
        $existing = SatuSehatOrganization::where('identifier', $depId)->first();

        if ($existing && $existing->ihs_number) {
            $this->toastWarning("Departemen ini sudah memiliki IHS Number: {$existing->ihs_number}. Gunakan Tarik dari SS untuk mengganti mapping.");
            return;
        }

        try {
            $response = app(OrganizationService::class)->createOrganization($depNama, $depId);
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

    public function with(): array
    {
        // Semua organization yang sudah di-mapping (identifier tidak null)
        $allOrgs = SatuSehatOrganization::whereNotNull('identifier')->get()->keyBy('identifier');
        $mappedDepIds = $allOrgs->keys()->toArray();

        $departemens = collect();
        $simrsError = false;
        $totalDep = 0;

        try {
            $query = Departemen::query();

            if ($this->search) {
                $query->where(function ($q) {
                    $q->where('dep_id', 'like', "%{$this->search}%")->orWhere('nama', 'like', "%{$this->search}%");
                });
            }

            if ($this->filterStatus === 'mapped') {
                $query->whereIn('dep_id', $mappedDepIds);
            } elseif ($this->filterStatus === 'unmapped') {
                $query->whereNotIn('dep_id', $mappedDepIds);
            }

            $totalDep = Departemen::count();
            $departemens = $query->orderBy('nama')->paginate(25);
        } catch (\Exception) {
            $simrsError = true;
        }

        return [
            'departemens' => $departemens,
            'allOrgs' => $allOrgs,
            'totalDep' => $totalDep,
            'totalMapped' => $allOrgs->count(),
            'simrsError' => $simrsError,
        ];
    }
};
?>

<div>
    <x-ui.page-header title="Satu Sehat — Organization" subtitle="Mapping departemen SIMRS ke Organization Satu Sehat" />

    {{-- Error SIMRS --}}
    @if ($simrsError)
        <div
            class="flex items-center gap-3 p-4 mb-6 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
            <flux:icon name="exclamation-triangle" class="w-5 h-5 text-red-500 shrink-0" />
            <p class="text-sm text-red-700 dark:text-red-300">Koneksi ke database SIMRS gagal. Data departemen tidak
                dapat ditampilkan.</p>
        </div>
    @endif

    {{-- Toolbar --}}
    <div class="flex flex-col gap-3 mb-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center flex-1">
            <div class="flex-1 max-w-sm">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                    placeholder="Cari ID atau nama departemen..." clearable />
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
            <span class="text-zinc-500 dark:text-primary-dark-400">{{ $totalDep }} departemen</span>
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
                            ID</th>
                        <th
                            class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500">
                            Nama Departemen</th>
                        <th
                            class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500">
                            Mapping Organization</th>
                        <th
                            class="px-5 py-3.5 text-xs font-semibold tracking-wider text-center uppercase text-zinc-400 dark:text-primary-dark-500 w-32">
                            Status</th>
                        <th
                            class="px-5 py-3.5 text-xs font-semibold tracking-wider text-center uppercase text-zinc-400 dark:text-primary-dark-500 w-40">
                            Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100/80 dark:divide-primary-dark-700/40">
                    @forelse ($departemens as $dep)
                        @php $org = $allOrgs[$dep->dep_id] ?? null; @endphp
                        <tr :key="$dep->dep_id"
                            class="group transition-colors hover:bg-zinc-50/60 dark:hover:bg-primary-dark-700/20">
                            <td class="px-5 py-4 whitespace-nowrap">
                                <span
                                    class="inline-block font-mono text-xs font-bold px-2 py-1 rounded-md
                                    bg-zinc-100 dark:bg-primary-dark-700 text-zinc-600 dark:text-primary-dark-300
                                    ring-1 ring-zinc-200 dark:ring-primary-dark-600">
                                    {{ $dep->dep_id }}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <p class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">
                                    {{ $dep->nama }}
                                </p>
                            </td>
                            <td class="px-5 py-4">
                                @if ($org)
                                    <div class="flex items-start gap-2.5">
                                        <span
                                            class="mt-1 w-2 h-2 rounded-full bg-emerald-400 dark:bg-emerald-500 shrink-0 ring-2 ring-emerald-100 dark:ring-emerald-900/50"></span>
                                        <div class="min-w-0">
                                            <p
                                                class="font-mono text-xs font-bold text-emerald-700 dark:text-emerald-400">
                                                {{ $org->ihs_number }}</p>
                                            <p
                                                class="mt-0.5 text-xs text-zinc-500 dark:text-primary-dark-400 leading-snug">
                                                {{ $org->name }}</p>
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
                                @if ($org)
                                    @php
                                        $badgeColor = match ($org->status) {
                                            'active' => 'green',
                                            'suspended' => 'amber',
                                            default => 'zinc',
                                        };
                                        $badgeLabel = match ($org->status) {
                                            'active' => 'Aktif',
                                            'suspended' => 'Ditangguhkan',
                                            default => 'Nonaktif',
                                        };
                                    @endphp
                                    <flux:badge :color="$badgeColor" size="sm">{{ $badgeLabel }}</flux:badge>
                                @else
                                    <span class="text-xs text-zinc-300 dark:text-primary-dark-600">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-center whitespace-nowrap">
                                <div class="flex items-center justify-center gap-1.5">
                                    @if (!$org)
                                        {{-- Belum dipetakan: 2 tombol utama --}}
                                        <x-atoms.button
                                            wire:click="sendToSatuSehat('{{ $dep->dep_id }}', '{{ addslashes($dep->nama) }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="sendToSatuSehat('{{ $dep->dep_id }}', '{{ addslashes($dep->nama) }}')"
                                            size="sm" variant="ghost" icon="paper-airplane"
                                            title="Buat baru di Satu Sehat" />
                                        <x-atoms.button
                                            wire:click="openPullModal('{{ $dep->dep_id }}', '{{ addslashes($dep->nama) }}')"
                                            size="sm" variant="ghost" icon="arrow-down-tray"
                                            title="Cari dan tarik dari Satu Sehat" />
                                    @else
                                        <x-atoms.button variant="ghost" wire:click="viewDetail('{{ $dep->dep_id }}')"
                                            size="sm" icon="eye" title="Lihat detail" />

                                        <x-atoms.button variant="ghost"
                                            wire:click="confirmDelete('{{ $dep->dep_id }}', '{{ addslashes($dep->nama) }}')"
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
                                        <flux:icon name="building-office"
                                            class="w-7 h-7 text-zinc-300 dark:text-primary-dark-500" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-zinc-500 dark:text-primary-dark-400">
                                            {{ $simrsError ? 'Koneksi SIMRS gagal' : 'Tidak ada departemen ditemukan' }}
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

        @if ($departemens instanceof \Illuminate\Pagination\LengthAwarePaginator && $departemens->hasPages())
            <div
                class="px-5 py-4 border-t border-zinc-100 dark:border-primary-dark-700/60 bg-zinc-50/50 dark:bg-primary-dark-900/20">
                {{ $departemens->links() }}
            </div>
        @endif
    </div>

    {{-- Modal Detail --}}
    <x-organisms.modal wire:model="showDetailModal" maxWidth="xl" title="">
        @if ($selectedOrg)
            @php
                $statusColor = match ($selectedOrg->status) {
                    'active' => 'green',
                    'suspended' => 'amber',
                    default => 'zinc',
                };
                $statusLabel = match ($selectedOrg->status) {
                    'active' => 'Aktif',
                    'suspended' => 'Ditangguhkan',
                    default => 'Nonaktif',
                };
            @endphp
            <div class="space-y-5">
                {{-- Header --}}
                <div class="flex items-start gap-4 pb-4 border-b border-zinc-200 dark:border-primary-dark-700">
                    <div
                        class="flex items-center justify-center flex-shrink-0 w-12 h-12 rounded-xl bg-primary-100 dark:bg-primary-900/30">
                        <flux:icon name="building-office" class="w-6 h-6 text-primary-600 dark:text-primary-400" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <h2 class="text-base font-bold text-zinc-900 dark:text-white truncate">{{ $selectedOrg->name }}
                        </h2>
                        <div class="flex flex-wrap items-center gap-2 mt-1.5">
                            <flux:badge :color="$statusColor" size="sm">{{ $statusLabel }}</flux:badge>
                            @if ($selectedOrg->part_of)
                                <span class="text-xs text-zinc-400 dark:text-primary-dark-500 font-mono">
                                    partOf: {{ $selectedOrg->part_of }}
                                </span>
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
                                {{ $selectedOrg->ihs_number }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-500 dark:text-primary-dark-400">Identifier (Dep ID)</dt>
                            <dd class="mt-0.5 font-mono text-zinc-700 dark:text-primary-dark-300">
                                {{ $selectedOrg->identifier ?? '-' }}</dd>
                        </div>
                    </dl>
                </div>

                {{-- Kontak --}}
                @php
                    $detailPhone = $selectedOrg->getTelecom('phone');
                    $detailEmail = $selectedOrg->getTelecom('email');
                    $detailWebsite = $selectedOrg->getTelecom('url');
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

                {{-- Alamat --}}
                @php
                    $detailAddr = $selectedOrg->getAddress();
                    $detailLine = implode(', ', $detailAddr['line'] ?? []) ?: '-';
                    $detailCity = $detailAddr['city'] ?? '-';
                    $detailState = $detailAddr['state'] ?? '-';
                    $detailPostal = $detailAddr['postalCode'] ?? '-';
                @endphp
                <div>
                    <p
                        class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-primary-dark-500">
                        Alamat</p>
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                        <div class="col-span-2">
                            <dt class="text-xs text-zinc-500 dark:text-primary-dark-400">Alamat</dt>
                            <dd class="mt-0.5 text-zinc-700 dark:text-primary-dark-300">{{ $detailLine }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-500 dark:text-primary-dark-400">Kota</dt>
                            <dd class="mt-0.5 text-zinc-700 dark:text-primary-dark-300">{{ $detailCity }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-500 dark:text-primary-dark-400">Provinsi</dt>
                            <dd class="mt-0.5 text-zinc-700 dark:text-primary-dark-300">{{ $detailState }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-500 dark:text-primary-dark-400">Kode Pos</dt>
                            <dd class="mt-0.5 font-mono text-zinc-700 dark:text-primary-dark-300">{{ $detailPostal }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-500 dark:text-primary-dark-400">Disinkron</dt>
                            <dd class="mt-0.5 text-zinc-700 dark:text-primary-dark-300">
                                {{ $selectedOrg->synced_at?->format('d/m/Y H:i') ?? '-' }}</dd>
                        </div>
                    </dl>
                </div>

                {{-- Raw FHIR (opsional, collapsed) --}}
                @if ($selectedOrg->raw_response)
                    <details class="group">
                        <summary
                            class="cursor-pointer text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-primary-dark-500
                                        hover:text-zinc-600 dark:hover:text-primary-dark-300 transition-colors select-none">
                            <span class="group-open:hidden">Lihat FHIR Resource</span>
                            <span class="hidden group-open:inline">Sembunyikan</span>
                        </summary>
                        <x-atoms.code-block language="json" maxHeight="max-h-52" class="mt-2">{{ json_encode($selectedOrg->raw_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</x-atoms.code-block>
                    </details>
                @endif

                <div class="flex justify-between items-center pt-1">
                    <x-atoms.button wire:click="updateOrganization" wire:loading.attr="disabled"
                        wire:target="updateOrganization" variant="primary" icon="arrow-path">
                        <span wire:loading.remove wire:target="updateOrganization">Update ke SS</span>
                        <span wire:loading wire:target="updateOrganization">Memperbarui...</span>
                    </x-atoms.button>
                    <x-atoms.button variant="ghost" wire:click="$set('showDetailModal', false)">Tutup</x-atoms.button>
                </div>
            </div>
        @endif
    
    </x-organisms.modal>

    {{-- Modal Tarik dari Satu Sehat --}}
    <x-organisms.modal wire:model="showPullModal" maxWidth="xl" title="">
        <div class="space-y-4">
            {{-- Header --}}
            <div>
                <flux:heading size="lg">Tarik Organization dari Satu Sehat</flux:heading>
                @if ($pullDeptId)
                    <flux:text class="mt-0.5">
                        Departemen: <span class="font-semibold">{{ $pullDeptName }}</span>
                        <span class="font-mono text-xs text-zinc-400">({{ $pullDeptId }})</span>
                    </flux:text>
                @endif
            </div>

            {{-- Search --}}
            <div class="flex gap-2">
                <div class="flex-1">
                    <flux:input wire:model="pullSearch" wire:keydown.enter="searchOrganization"
                        placeholder="Nama organization di Satu Sehat..." icon="magnifying-glass" />
                </div>
                <x-atoms.button wire:click="searchOrganization" wire:loading.attr="disabled"
                    wire:target="searchOrganization" variant="primary" icon="magnifying-glass">
                    <span wire:loading.remove wire:target="searchOrganization">Cari</span>
                    <span wire:loading wire:target="searchOrganization">Mencari...</span>
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
                                $resActive = (bool) ($resource['active'] ?? true);
                            @endphp
                            <li wire:click="selectOrganization('{{ $resIhs }}')"
                                wire:loading.class="opacity-50 pointer-events-none" wire:target="selectOrganization"
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
                    <p class="text-sm">Ketik nama organization lalu klik <strong>Cari</strong></p>
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
                    <flux:text class="mt-0.5">Organization untuk <strong>{{ $deleteDeptName }}</strong> akan dihapus
                        dari Satu Sehat.</flux:text>
                </div>
            </div>

            <div
                class="p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-xs text-amber-700 dark:text-amber-300">
                <flux:icon name="exclamation-triangle" class="inline w-3.5 h-3.5 mr-1" />
                Organization akan dinonaktifkan di platform Satu Sehat, kemudian mapping lokal akan dihapus.
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
