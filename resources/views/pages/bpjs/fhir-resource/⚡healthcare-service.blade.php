<?php

use App\Jobs\SyncBpjsHealthcareServicesJob;
use App\Models\Bpjs\BpjsHealthcareService;
use App\Models\Simrs\Bangsal;
use App\Models\Simrs\Poliklinik;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('BPJS — Healthcare Service')] class extends Component {
    use WithPagination;

    #[Url]
    public string $tab = 'poliklinik';

    #[Url]
    public string $searchPoli = '';

    #[Url]
    public string $filterStatusPoli = '';

    #[Url]
    public string $searchBangsal = '';

    #[Url]
    public string $filterStatusBangsal = '';

    // Modal detail
    public bool $showDetailModal = false;
    public ?BpjsHealthcareService $selectedService = null;

    // Modal hapus
    public bool $showDeleteModal = false;
    public ?string $deleteCode = null;
    public string $deleteType = 'poliklinik';
    public string $deleteName = '';

    // Modal sync
    public bool $showSyncModal = false;
    public string $syncType = 'poliklinik';

    public function updatedSearchPoli(): void
    {
        $this->resetPage('poli_page');
    }
    public function updatedFilterStatusPoli(): void
    {
        $this->resetPage('poli_page');
    }
    public function updatedSearchBangsal(): void
    {
        $this->resetPage('bangsal_page');
    }
    public function updatedFilterStatusBangsal(): void
    {
        $this->resetPage('bangsal_page');
    }

    public function switchTab(string $tab): void
    {
        $this->tab = $tab;
        $this->resetPage('poli_page');
        $this->resetPage('bangsal_page');
    }

    public function generatePoliUuid(string $poliCode, string $name): void
    {
        if (BpjsHealthcareService::where('type', 'poliklinik')->where('local_code', $poliCode)->exists()) {
            $this->toastWarning('Poliklinik ini sudah memiliki UUID BPJS.');
            return;
        }

        BpjsHealthcareService::create(['type' => 'poliklinik', 'local_code' => $poliCode, 'name' => $name]);
        $this->toastSuccess("UUID BPJS berhasil di-generate untuk poliklinik: {$name}");
    }

    public function generateWardUuid(string $wardCode, string $name): void
    {
        if (BpjsHealthcareService::where('type', 'bangsal')->where('local_code', $wardCode)->exists()) {
            $this->toastWarning('Bangsal ini sudah memiliki UUID BPJS.');
            return;
        }

        BpjsHealthcareService::create(['type' => 'bangsal', 'local_code' => $wardCode, 'name' => $name]);
        $this->toastSuccess("UUID BPJS berhasil di-generate untuk bangsal: {$name}");
    }

    public function viewPoliDetail(string $poliCode): void
    {
        $this->selectedService = BpjsHealthcareService::where('type', 'poliklinik')->where('local_code', $poliCode)->first();
        $this->showDetailModal = true;
    }

    public function viewWardDetail(string $wardCode): void
    {
        $this->selectedService = BpjsHealthcareService::where('type', 'bangsal')->where('local_code', $wardCode)->first();
        $this->showDetailModal = true;
    }

    public function confirmDeletePoli(string $poliCode, string $name): void
    {
        $this->deleteCode = $poliCode;
        $this->deleteType = 'poliklinik';
        $this->deleteName = $name;
        $this->showDeleteModal = true;
    }

    public function confirmDeleteWard(string $wardCode, string $name): void
    {
        $this->deleteCode = $wardCode;
        $this->deleteType = 'bangsal';
        $this->deleteName = $name;
        $this->showDeleteModal = true;
    }

    public function deleteEntry(): void
    {
        if (!$this->deleteCode) {
            return;
        }

        BpjsHealthcareService::where('type', $this->deleteType)->where('local_code', $this->deleteCode)->delete();

        $this->showDeleteModal = false;
        $this->reset(['deleteCode', 'deleteName']);
        $this->toastSuccess('UUID BPJS berhasil dihapus.');
    }

    public function openSyncModal(string $type): void
    {
        $this->syncType = $type;
        $this->showSyncModal = true;
    }

    public function syncAll(): void
    {
        SyncBpjsHealthcareServicesJob::dispatch($this->syncType);
        $this->showSyncModal = false;
        $label = match ($this->syncType) {
            'poliklinik' => 'poliklinik',
            'bangsal' => 'bangsal',
            default => 'semua tipe',
        };
        $this->toastSuccess("Sync {$label} dijadwalkan. Proses berjalan di background.");
    }

    public function with(): array
    {
        $allServices = BpjsHealthcareService::where('type', 'poliklinik')->get()->keyBy('local_code');
        $allWards = BpjsHealthcareService::where('type', 'bangsal')->get()->keyBy('local_code');

        $polikliniks = collect();
        $bangsals = collect();
        $simrsError = false;
        $totalPoli = 0;
        $totalBangsal = 0;

        try {
            // Poliklinik
            $poliQuery = Poliklinik::active()->search($this->searchPoli);
            if ($this->filterStatusPoli === 'registered') {
                $poliQuery->whereIn('kd_poli', $allServices->keys()->toArray());
            } elseif ($this->filterStatusPoli === 'unregistered') {
                $poliQuery->whereNotIn('kd_poli', $allServices->keys()->toArray());
            }
            $totalPoli = Poliklinik::active()->count();
            $polikliniks = $poliQuery->orderBy('nm_poli')->paginate(25, ['*'], 'poli_page');

            // Bangsal
            $bangsalQuery = Bangsal::where('status', '1');
            if ($this->searchBangsal) {
                $bangsalQuery->where(fn($q) => $q->where('kd_bangsal', 'like', "%{$this->searchBangsal}%")->orWhere('nm_bangsal', 'like', "%{$this->searchBangsal}%"));
            }
            if ($this->filterStatusBangsal === 'registered') {
                $bangsalQuery->whereIn('kd_bangsal', $allWards->keys()->toArray());
            } elseif ($this->filterStatusBangsal === 'unregistered') {
                $bangsalQuery->whereNotIn('kd_bangsal', $allWards->keys()->toArray());
            }
            $totalBangsal = Bangsal::where('status', '1')->count();
            $bangsals = $bangsalQuery->orderBy('nm_bangsal')->paginate(25, ['*'], 'bangsal_page');
        } catch (\Exception) {
            $simrsError = true;
        }

        $totalPoliRegistered = $allServices->count();
        $totalWardRegistered = $allWards->count();

        return [
            'polikliniks' => $polikliniks,
            'bangsals' => $bangsals,
            'allServices' => $allServices,
            'allWards' => $allWards,
            'totalPoli' => $totalPoli,
            'totalBangsal' => $totalBangsal,
            'totalPoliRegistered' => $totalPoliRegistered,
            'totalWardRegistered' => $totalWardRegistered,
            'unsyncedAll' => max(0, $totalPoli - $totalPoliRegistered) + max(0, $totalBangsal - $totalWardRegistered),
            'simrsError' => $simrsError,
        ];
    }
}; ?>

<div>
    <x-ui.page-header title="BPJS — Healthcare Service"
        subtitle="Registry UUID FHIR HealthcareService untuk poliklinik dan bangsal BPJS">
        <x-slot name="actions">
            <x-atoms.button wire:click="openSyncModal('{{ $tab }}')" variant="ghost" icon="arrow-path"
                :disabled="$simrsError">
                Sync Tab Ini
            </x-atoms.button>
            <x-atoms.button wire:click="openSyncModal('all')" variant="primary" icon="arrow-path" :disabled="$simrsError">
                Sync Semua
            </x-atoms.button>
        </x-slot>
    </x-ui.page-header>

    @if ($simrsError)
        <div
            class="flex items-center gap-3 p-4 mb-6 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
            <flux:icon name="exclamation-triangle" class="w-5 h-5 text-red-500 shrink-0" />
            <p class="text-sm text-red-700 dark:text-red-300">Koneksi ke database SIMRS gagal. Data tidak dapat
                ditampilkan.</p>
        </div>
    @endif

    {{-- Tabs --}}
    <div class="flex gap-1 p-1 mb-5 bg-zinc-100 dark:bg-primary-dark-800/60 rounded-xl w-fit">
        <x-atoms.button wire:click="switchTab('poliklinik')"
            class="flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg transition-all
                {{ $tab === 'poliklinik'
                    ? 'bg-white dark:bg-primary-dark-700 text-zinc-900 dark:text-white shadow-sm'
                    : 'text-zinc-500 dark:text-primary-dark-400 hover:text-zinc-700 dark:hover:text-primary-dark-200' }}">
            <flux:icon name="building-office-2" class="w-4 h-4" />
            Poliklinik
            <span
                class="text-xs font-semibold px-1.5 py-0.5 rounded-md
                {{ $tab === 'poliklinik' ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400' : 'bg-zinc-200 dark:bg-primary-dark-700 text-zinc-500 dark:text-primary-dark-400' }}">
                {{ $totalPoliRegistered }}/{{ $totalPoli }}
            </span>
        </x-atoms.button>
        <x-atoms.button wire:click="switchTab('bangsal')"
            class="flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg transition-all
                {{ $tab === 'bangsal'
                    ? 'bg-white dark:bg-primary-dark-700 text-zinc-900 dark:text-white shadow-sm'
                    : 'text-zinc-500 dark:text-primary-dark-400 hover:text-zinc-700 dark:hover:text-primary-dark-200' }}">
            <flux:icon name="home-modern" class="w-4 h-4" />
            Bangsal / Ruang
            <span
                class="text-xs font-semibold px-1.5 py-0.5 rounded-md
                {{ $tab === 'bangsal' ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400' : 'bg-zinc-200 dark:bg-primary-dark-700 text-zinc-500 dark:text-primary-dark-400' }}">
                {{ $totalWardRegistered }}/{{ $totalBangsal }}
            </span>
        </x-atoms.button>
    </div>

    {{-- Tab: Poliklinik --}}
    @if ($tab === 'poliklinik')
        <x-organisms.data-panel :padding="false">
            <x-slot:filter>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <div class="max-w-sm">
                            <flux:input wire:model.live.debounce.300ms="searchPoli" icon="magnifying-glass"
                                placeholder="Cari kode atau nama poliklinik..." clearable />
                        </div>
                        <flux:select wire:model.live="filterStatusPoli" class="w-48">
                            <flux:select.option value="">Semua Status</flux:select.option>
                            <flux:select.option value="registered">Sudah Terdaftar</flux:select.option>
                            <flux:select.option value="unregistered">Belum Terdaftar</flux:select.option>
                        </flux:select>
                    </div>
                    <div
                        class="hidden sm:flex items-center gap-2.5 px-3.5 py-2.5 text-xs font-medium bg-white dark:bg-primary-dark-800 rounded-xl border border-zinc-200 dark:border-primary-dark-700 whitespace-nowrap shadow-sm">
                        <span class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400">
                            <span class="inline-block w-2 h-2 rounded-full bg-emerald-400"></span>
                            {{ $totalPoliRegistered }} terdaftar
                        </span>
                        @if (!$simrsError)
                            <span class="text-zinc-300 dark:text-primary-dark-600">/</span>
                            <span class="text-zinc-500 dark:text-primary-dark-400">{{ $totalPoli }} poli
                                aktif</span>
                        @endif
                    </div>
                </div>
            </x-slot:filter>
            <x-organisms.table>
                <x-slot:headings>
                    <th
                        class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500 w-28">
                        Kode</th>
                    <th
                        class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500">
                        Nama Poliklinik</th>
                    <th
                        class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500">
                        UUID BPJS</th>
                    <th
                        class="px-5 py-3.5 text-xs font-semibold tracking-wider text-center uppercase text-zinc-400 dark:text-primary-dark-500 w-32">
                        Status</th>
                    <th
                        class="px-5 py-3.5 text-xs font-semibold tracking-wider text-center uppercase text-zinc-400 dark:text-primary-dark-500 w-32">
                        Aksi</th>
                </x-slot:headings>
                @forelse ($polikliniks as $poli)
                    @php $svc = $allServices[$poli->kd_poli] ?? null; @endphp
                    <x-molecules.table-row wire:key="poli-{{ $poli->kd_poli }}">
                        <x-atoms.table-cell>
                            <span
                                class="inline-block font-mono text-xs font-bold px-2 py-1 rounded-md
                                        bg-zinc-100 dark:bg-primary-dark-700 text-zinc-600 dark:text-primary-dark-300
                                        ring-1 ring-zinc-200 dark:ring-primary-dark-600 shadow-sm leading-none">
                                {{ $poli->kd_poli }}
                            </span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            <p class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">
                                {{ $poli->nm_poli }}</p>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            @if ($svc)
                                <span
                                    class="font-mono text-xs font-bold text-emerald-700 dark:text-emerald-400 leading-none">{{ $svc->id }}</span>
                            @else
                                <span class="text-xs italic text-zinc-400 dark:text-primary-dark-500 leading-none">Belum
                                    terdaftar</span>
                            @endif
                        </x-atoms.table-cell>
                        <x-atoms.table-cell align="center">
                            @if ($svc)
                                <flux:badge color="green" size="sm" inset="top bottom">Terdaftar</flux:badge>
                            @else
                                <span class="text-xs text-zinc-300 dark:text-primary-dark-600">—</span>
                            @endif
                        </x-atoms.table-cell>
                        <x-atoms.table-cell align="center" action>
                            @if (!$svc)
                                <x-atoms.button
                                    wire:click="generatePoliUuid('{{ $poli->kd_poli }}', '{{ addslashes($poli->nm_poli) }}')"
                                    wire:target="generatePoliUuid('{{ $poli->kd_poli }}', '{{ addslashes($poli->nm_poli) }}')"
                                    size="sm" variant="primary" icon="plus-circle">
                                    Generate
                                </x-atoms.button>
                            @else
                                <x-atoms.button variant="ghost" wire:click="viewPoliDetail('{{ $poli->kd_poli }}')"
                                    size="sm" icon="eye" title="Lihat detail" />
                                <x-atoms.button variant="ghost"
                                    wire:click="confirmDeletePoli('{{ $poli->kd_poli }}', '{{ addslashes($poli->nm_poli) }}')"
                                    size="sm" icon="trash" title="Hapus UUID" />
                            @endif
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-16 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <div
                                    class="flex items-center justify-center w-14 h-14 rounded-2xl bg-zinc-100 dark:bg-primary-dark-700">
                                    <flux:icon name="building-office-2"
                                        class="w-7 h-7 text-zinc-300 dark:text-primary-dark-500" />
                                </div>
                                <p class="text-sm font-semibold text-zinc-500 dark:text-primary-dark-400">
                                    {{ $simrsError ? 'Koneksi SIMRS gagal' : 'Tidak ada poliklinik ditemukan' }}
                                </p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </x-organisms.table>

            @if ($polikliniks instanceof \Illuminate\Pagination\LengthAwarePaginator && $polikliniks->hasPages())
                <div
                    class="px-5 py-4 border-t border-zinc-100 dark:border-primary-dark-700/60 bg-zinc-50/50 dark:bg-primary-dark-900/20">
                    {{ $polikliniks->links() }}
                </div>
            @endif
        </x-organisms.data-panel>
    @endif

    {{-- Tab: Bangsal / Ruang --}}
    @if ($tab === 'bangsal')
        <x-organisms.data-panel :padding="false">
            <x-slot:filter>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <div class="max-w-sm">
                            <flux:input wire:model.live.debounce.300ms="searchBangsal" icon="magnifying-glass"
                                placeholder="Cari kode atau nama bangsal..." clearable />
                        </div>
                        <flux:select wire:model.live="filterStatusBangsal" class="w-48">
                            <flux:select.option value="">Semua Status</flux:select.option>
                            <flux:select.option value="registered">Sudah Terdaftar</flux:select.option>
                            <flux:select.option value="unregistered">Belum Terdaftar</flux:select.option>
                        </flux:select>
                    </div>
                    <div
                        class="hidden sm:flex items-center gap-2.5 px-3.5 py-2.5 text-xs font-medium bg-white dark:bg-primary-dark-800 rounded-xl border border-zinc-200 dark:border-primary-dark-700 whitespace-nowrap shadow-sm">
                        <span class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400">
                            <span class="inline-block w-2 h-2 rounded-full bg-emerald-400"></span>
                            {{ $totalWardRegistered }} terdaftar
                        </span>
                        @if (!$simrsError)
                            <span class="text-zinc-300 dark:text-primary-dark-600">/</span>
                            <span class="text-zinc-500 dark:text-primary-dark-400">{{ $totalBangsal }} bangsal
                                aktif</span>
                        @endif
                    </div>
                </div>
            </x-slot:filter>
            <x-organisms.table>
                <x-slot:headings>
                    <th
                        class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500 w-28">
                        Kode</th>
                    <th
                        class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500">
                        Nama Bangsal / Ruang</th>
                    <th
                        class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500">
                        UUID BPJS</th>
                    <th
                        class="px-5 py-3.5 text-xs font-semibold tracking-wider text-center uppercase text-zinc-400 dark:text-primary-dark-500 w-32">
                        Status</th>
                    <th
                        class="px-5 py-3.5 text-xs font-semibold tracking-wider text-center uppercase text-zinc-400 dark:text-primary-dark-500 w-32">
                        Aksi</th>
                </x-slot:headings>
                @forelse ($bangsals as $bangsal)
                    @php $ward = $allWards[$bangsal->kd_bangsal] ?? null; @endphp
                    <x-molecules.table-row wire:key="bangsal-{{ $bangsal->kd_bangsal }}">
                        <x-atoms.table-cell>
                            <span
                                class="inline-block font-mono text-xs font-bold px-2 py-1 rounded-md
                                        bg-zinc-100 dark:bg-primary-dark-700 text-zinc-600 dark:text-primary-dark-300
                                        ring-1 ring-zinc-200 dark:ring-primary-dark-600 shadow-sm leading-none">
                                {{ $bangsal->kd_bangsal }}
                            </span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            <p class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">
                                {{ $bangsal->nm_bangsal }}</p>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            @if ($ward)
                                <span
                                    class="font-mono text-xs font-bold text-emerald-700 dark:text-emerald-400 leading-none">{{ $ward->id }}</span>
                            @else
                                <span
                                    class="text-xs italic text-zinc-400 dark:text-primary-dark-500 leading-none">Belum
                                    terdaftar</span>
                            @endif
                        </x-atoms.table-cell>
                        <x-atoms.table-cell align="center">
                            @if ($ward)
                                <flux:badge color="green" size="sm" inset="top bottom">Terdaftar</flux:badge>
                            @else
                                <span class="text-xs text-zinc-300 dark:text-primary-dark-600">—</span>
                            @endif
                        </x-atoms.table-cell>
                        <x-atoms.table-cell align="center" action>
                            @if (!$ward)
                                <x-atoms.button
                                    wire:click="generateWardUuid('{{ $bangsal->kd_bangsal }}', '{{ addslashes($bangsal->nm_bangsal) }}')"
                                    wire:target="generateWardUuid('{{ $bangsal->kd_bangsal }}', '{{ addslashes($bangsal->nm_bangsal) }}')"
                                    size="sm" variant="primary" icon="plus-circle">
                                    Generate
                                </x-atoms.button>
                            @else
                                <x-atoms.button variant="ghost"
                                    wire:click="viewWardDetail('{{ $bangsal->kd_bangsal }}')" size="sm"
                                    icon="eye" title="Lihat detail" />
                                <x-atoms.button variant="ghost"
                                    wire:click="confirmDeleteWard('{{ $bangsal->kd_bangsal }}', '{{ addslashes($bangsal->nm_bangsal) }}')"
                                    size="sm" icon="trash" title="Hapus UUID" />
                            @endif
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-16 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <div
                                    class="flex items-center justify-center w-14 h-14 rounded-2xl bg-zinc-100 dark:bg-primary-dark-700">
                                    <flux:icon name="home-modern"
                                        class="w-7 h-7 text-zinc-300 dark:text-primary-dark-500" />
                                </div>
                                <p class="text-sm font-semibold text-zinc-500 dark:text-primary-dark-400">
                                    {{ $simrsError ? 'Koneksi SIMRS gagal' : 'Tidak ada bangsal ditemukan' }}
                                </p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </x-organisms.table>

            @if ($bangsals instanceof \Illuminate\Pagination\LengthAwarePaginator && $bangsals->hasPages())
                <x-slot:footer>
                    <div
                        class="px-5 py-4 border-t border-zinc-100 dark:border-primary-dark-700/60 bg-zinc-50/50 dark:bg-primary-dark-900/20">
                        {{ $bangsals->links() }}
                    </div>
                </x-slot:footer>
            @endif
        </x-organisms.data-panel>
    @endif

    {{-- Modal Detail --}}
    <x-organisms.modal wire:model="showDetailModal" :title="$selectedService?->name" maxWidth="lg">
        @if ($selectedService)
            @php
                $icon = $selectedService->type === 'poliklinik' ? 'building-office-2' : 'home-modern';
                $label = $selectedService->type === 'poliklinik' ? 'Kode Poliklinik' : 'Kode Bangsal';
            @endphp
            <div class="space-y-6">
                <div class="flex items-center gap-4">
                    <div
                        class="flex items-center justify-center flex-shrink-0 w-12 h-12 rounded-xl bg-emerald-100 dark:bg-emerald-900/30">
                        <flux:icon :name="$icon" class="w-6 h-6 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <div>
                        <h4 class="text-sm font-semibold text-zinc-900 dark:text-white">Healthcare Service</h4>
                        <flux:badge color="green" size="sm" class="mt-0.5">Terdaftar dalam FHIR</flux:badge>
                    </div>
                </div>

                <div class="space-y-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-primary-dark-500">
                        Identitas FHIR</p>
                    <dl class="grid grid-cols-1 gap-4 text-sm">
                        <div class="pb-3 border-b border-zinc-100 dark:border-primary-dark-800">
                            <dt class="text-xs text-zinc-500 dark:text-primary-dark-400 mb-1">UUID BPJS (FHIR Resource
                                ID)</dt>
                            <dd
                                class="font-mono text-sm font-bold text-emerald-700 dark:text-emerald-400 break-all bg-emerald-50 dark:bg-emerald-900/10 p-2 rounded-md border border-emerald-100 dark:border-emerald-800/50">
                                {{ $selectedService->id }}
                            </dd>
                        </div>
                        <div class="pb-3 border-b border-zinc-100 dark:border-primary-dark-800">
                            <dt class="text-xs text-zinc-500 dark:text-primary-dark-400 mb-1">{{ $label }}</dt>
                            <dd class="font-mono font-medium text-zinc-700 dark:text-primary-dark-300">
                                {{ $selectedService->local_code }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-500 dark:text-primary-dark-400 mb-1">Dibuat Pada</dt>
                            <dd class="text-zinc-700 dark:text-primary-dark-300">
                                {{ $selectedService->created_at?->format('d/m/Y H:i') }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        @endif

        <x-slot name="footer">
            <div class="flex justify-end">
                <x-atoms.button variant="ghost" wire:click="$set('showDetailModal', false)">Tutup</x-atoms.button>
            </div>
        </x-slot>
    </x-organisms.modal>

    {{-- Modal Sync Semua --}}
    @php
        $syncHeading =
            $syncType === 'poliklinik'
                ? 'Sync Poliklinik'
                : ($syncType === 'bangsal'
                    ? 'Sync Bangsal'
                    : 'Sync Semua Healthcare Service');
        $syncUnsynced =
            $syncType === 'poliklinik'
                ? max(0, ($totalPoli ?? 0) - ($totalPoliRegistered ?? 0))
                : ($syncType === 'bangsal'
                    ? max(0, ($totalBangsal ?? 0) - ($totalWardRegistered ?? 0))
                    : $unsyncedAll ?? 0);
    @endphp
    <x-organisms.modal wire:model="showSyncModal" :title="$syncHeading" maxWidth="md" :description="number_format($syncUnsynced) . ' item belum memiliki UUID BPJS'">
        <div class="space-y-5">
            <div
                class="p-4 rounded-xl bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-800/50 flex items-start gap-3">
                <flux:icon name="exclamation-triangle" class="size-5 text-amber-600 shrink-0 mt-0.5" />
                <p class="text-sm text-amber-800 dark:text-amber-200">
                    Hanya data aktif dari SIMRS yang akan disinkronkan. UUID yang sudah ada tidak akan berubah.
                </p>
            </div>
            <p class="text-sm text-zinc-600 dark:text-primary-dark-400">
                Proses ini akan mengirimkan data ke BPJS Health FHIR Server untuk mendapatkan ID sumber daya. Pastikan
                koneksi internet stabil.
            </p>
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-atoms.button variant="ghost" wire:click="$set('showSyncModal', false)">Batal</x-atoms.button>
                <x-atoms.button variant="primary" icon="arrow-path" wire:click="syncAll"
                    wire:loading.attr="disabled" wire:target="syncAll">
                    Mulai Sync
                </x-atoms.button>
            </div>
        </x-slot>
    </x-organisms.modal>

    {{-- Modal Konfirmasi Hapus --}}
    <x-organisms.modal wire:model="showDeleteModal" title="Hapus UUID?" maxWidth="sm" :description="'UUID BPJS untuk ' . $deleteName . ' akan dihapus.'">
        <div class="space-y-4">
            <div
                class="p-4 rounded-xl bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800/50 flex items-start gap-3">
                <flux:icon name="exclamation-triangle" class="size-5 text-red-600 shrink-0 mt-0.5" />
                <p class="text-xs text-red-800 dark:text-red-200">
                    UUID yang sudah digunakan di bundle BPJS tidak boleh dihapus untuk menjaga konsistensi data.
                    Pastikan data ini belum pernah dikirim.
                </p>
            </div>
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-atoms.button variant="ghost" wire:click="$set('showDeleteModal', false)">Batal</x-atoms.button>
                <x-atoms.button variant="danger" wire:click="deleteEntry" icon="trash">Hapus</x-atoms.button>
            </div>
        </x-slot>
    </x-organisms.modal>
</div>
