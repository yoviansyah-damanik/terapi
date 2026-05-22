<?php

use App\Models\Dicom\DicomModality;
use App\Models\Dicom\DicomRouter;
use App\Services\Dicom\OrthancService;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('DICOM — Infrastruktur')] class extends Component {
    use WithPagination;

    #[Url]
    public string $tab = 'router';

    #[Url]
    public string $search = '';

    // --- Common UI State ---
    public bool $showModal = false;
    public ?string $editingId = null;
    public bool $showDeleteModal = false;
    public ?string $deleteId = null;
    public string $deleteName = '';
    public bool $showEchoModal = false;
    public ?string $checkingId = null;

    // --- Router Properties ---
    public string $routerName = '';
    public string $routerAe = '';
    public string $routerHost = '';
    public int $routerPort = 104;
    public string $routerDesc = '';
    public bool $routerActive = true;

    // --- Modality Properties ---
    public ?string $modRouterId = null;
    public string $modAe = '';
    public string $modDesc = '';
    public ?string $modIp = '';
    public ?int $modPort = null;
    public string $modType = '';
    public string $modManuf = '';
    public bool $modWorklist = true;
    public bool $modActive = true;
    public string $modNotes = '';

    // --- Modality Sync State ---
    public array $orthancAes = [];
    public bool $showSyncFromOrthancModal = false;
    public array $orthancImportList = [];
    public ?string $syncRouterId = null;

    public function mount(): void
    {
        $this->loadOrthancAes();
    }

    public function updatedTab(): void
    {
        $this->reset(['search', 'editingId', 'showModal', 'showDeleteModal', 'showEchoModal']);
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function loadOrthancAes(): void
    {
        $orthanc = app(OrthancService::class);
        if (!$orthanc->isConfigured()) {
            return;
        }

        $result = $orthanc->getModalities();
        if ($result['success'] && is_array($result['data'])) {
            $this->orthancAes = $result['data'];
        }
    }

    // --- Router Actions ---

    public function openCreateRouter(): void
    {
        $this->resetRouterForm();
        $this->showModal = true;
    }

    public function applyRouterPreset(string $preset): void
    {
        $presets = [
            'orthanc' => [
                'name' => 'Orthanc PACS Lokal',
                'ae' => 'ORTHANC',
                'host' => '127.0.0.1',
                'port' => 4242,
                'description' => 'Orthanc PACS server lokal (C-STORE, C-FIND, worklist)',
            ],
            'satusehat_prod' => [
                'name' => 'SatuSehat DICOM Router',
                'ae' => 'SATUSEHAT',
                'host' => '127.0.0.1',
                'port' => 11112,
                'description' => 'DICOM Router Kemenkes — ImagingStudy',
            ],
        ];

        if (!isset($presets[$preset])) {
            return;
        }

        $p = $presets[$preset];
        $this->resetRouterForm();
        $this->routerName = $p['name'];
        $this->routerAe = $p['ae'];
        $this->routerHost = $p['host'];
        $this->routerPort = $p['port'];
        $this->routerDesc = $p['description'];
        $this->showModal = true;
    }

    public function openEditRouter(string $id): void
    {
        $d = DicomRouter::findOrFail($id);
        $this->editingId = $d->id;
        $this->routerName = $d->name;
        $this->routerAe = $d->ae_title;
        $this->routerHost = $d->host;
        $this->routerPort = $d->port;
        $this->routerDesc = $d->description ?? '';
        $this->routerActive = $d->is_active;
        $this->showModal = true;
    }

    public function saveRouter(): void
    {
        $this->validate([
            'routerName' => 'required|max:100',
            'routerAe' => 'required|max:16|regex:/^[A-Z0-9_\-]+$/i',
            'routerHost' => 'required|max:255',
            'routerPort' => 'required|integer|min:1|max:65535',
        ]);

        $data = [
            'name' => $this->routerName,
            'ae_title' => strtoupper($this->routerAe),
            'host' => $this->routerHost,
            'port' => $this->routerPort,
            'description' => $this->routerDesc ?: null,
            'is_active' => $this->routerActive,
        ];

        if ($this->editingId) {
            DicomRouter::findOrFail($this->editingId)->update($data);
            $this->dispatch('toast', type: 'success', message: 'Router diperbarui.');
        } else {
            $this->validate(['routerAe' => 'unique:dicom_routers,ae_title']);
            DicomRouter::create($data);
            $this->dispatch('toast', type: 'success', message: 'Router ditambahkan.');
        }

        $this->showModal = false;
    }

    public function confirmDeleteRouter(string $id, string $name): void
    {
        $this->deleteId = $id;
        $this->deleteName = $name;
        $this->showDeleteModal = true;
    }

    public function destroyRouter(): void
    {
        DicomRouter::findOrFail($this->deleteId)->delete();
        $this->dispatch('toast', type: 'success', message: 'Router dihapus.');
        $this->showDeleteModal = false;
    }

    // --- Modality Actions ---

    public function openCreateModality(): void
    {
        $this->resetModalityForm();
        $this->showModal = true;
    }

    public function openEditModality(string $id): void
    {
        $m = DicomModality::findOrFail($id);
        $this->editingId = $m->id;
        $this->modRouterId = $m->router_id;
        $this->modAe = $m->ae_title ?? '';
        $this->modDesc = $m->description ?? '';
        $this->modIp = $m->ip_address;
        $this->modPort = $m->port;
        $this->modType = $m->modality_type ?? '';
        $this->modManuf = $m->manufacturer ?? '';
        $this->modWorklist = $m->allow_worklist;
        $this->modActive = $m->is_active;
        $this->modNotes = $m->notes ?? '';
        $this->showModal = true;
    }

    public function saveModality(): void
    {
        $this->validate([
            'modRouterId' => ['required', fn($attr, $value, $fail) => DicomRouter::find($value) ?: $fail('Router DICOM yang dipilih tidak ditemukan.')],
            'modAe' => 'nullable|max:16|regex:/^[A-Z0-9_\-]+$/i',
            'modIp' => 'nullable|max:45',
            'modPort' => 'nullable|integer|min:1|max:65535',
            'modType' => 'nullable|max:10',
        ]);

        $data = [
            'router_id' => $this->modRouterId,
            'ae_title' => $this->modAe ? strtoupper($this->modAe) : null,
            'description' => $this->modDesc ?: null,
            'ip_address' => $this->modIp ?: null,
            'port' => $this->modPort,
            'modality_type' => $this->modType ? strtoupper($this->modType) : null,
            'manufacturer' => $this->modManuf ?: null,
            'allow_worklist' => $this->modWorklist,
            'is_active' => $this->modActive,
            'notes' => $this->modNotes ?: null,
        ];

        if ($this->editingId) {
            DicomModality::findOrFail($this->editingId)->update($data);
            $this->dispatch('toast', type: 'success', message: 'Modality diperbarui.');
        } else {
            DicomModality::create($data);
            $this->dispatch('toast', type: 'success', message: 'Modality ditambahkan.');
        }

        $this->showModal = false;
    }

    public function confirmDeleteModality(string $id, string $name): void
    {
        $this->deleteId = $id;
        $this->deleteName = $name;
        $this->showDeleteModal = true;
    }

    public function destroyModality(): void
    {
        DicomModality::findOrFail($this->deleteId)->delete();
        $this->dispatch('toast', type: 'success', message: 'Modality dihapus.');
        $this->showDeleteModal = false;
    }

    public function syncToOrthanc(string $id): void
    {
        $m = DicomModality::with('router')->findOrFail($id);
        $orthanc = app(OrthancService::class);
        if (!$orthanc->isConfigured()) {
            return;
        }

        $ae = $m->ae_title ?? $m->router->ae_title;
        $host = $m->ip_address ?? $m->router->host;
        $port = $m->port ?? $m->router->port;

        $result = $orthanc->registerModality($ae, $host, $port, $m->manufacturer ?? 'StoreScp');

        if ($result['success']) {
            $this->dispatch('toast', type: 'success', message: "Sync {$ae} berhasil.");
            $this->loadOrthancAes();
        } else {
            $this->dispatch('toast', type: 'error', message: 'Gagal sync.');
        }
    }

    // --- Sync From Orthanc ---

    public function openSyncFromOrthanc(): void
    {
        $orthanc = app(OrthancService::class);
        if (!$orthanc->isConfigured()) {
            $this->dispatch('toast', type: 'error', message: 'Orthanc belum dikonfigurasi.');
            return;
        }

        $result = $orthanc->getModalitiesWithDetails();
        if (!$result['success']) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengambil data dari Orthanc.');
            return;
        }

        $localAes = DicomModality::pluck('ae_title')->map('strtoupper')->all();

        $this->syncRouterId = DicomRouter::where('is_active', true)->value('id');

        $this->orthancImportList = collect($result['data'])
            ->map(
                fn($detail, $ae) => [
                    'ae' => $ae,
                    'host' => $detail['Host'] ?? '127.0.0.1',
                    'port' => $detail['Port'] ?? 104,
                    'manufacturer' => $detail['Manufacturer'] ?? null,
                    'exists' => in_array(strtoupper($ae), $localAes),
                    'type' => $this->guessModalityType($ae),
                    'router_id' => $this->syncRouterId,
                ],
            )
            ->values()
            ->all();

        $this->showSyncFromOrthancModal = true;
    }

    public function importFromOrthanc(): void
    {
        $toImport = array_filter($this->orthancImportList, fn($m) => !$m['exists']);
        $count = 0;

        foreach ($toImport as $m) {
            if (empty($m['router_id'])) {
                continue;
            }

            DicomModality::create([
                'router_id' => $m['router_id'],
                'ae_title' => strtoupper($m['ae']),
                'ip_address' => $m['host'] ?? null,
                'port' => $m['port'] ?? null,
                'modality_type' => $m['type'],
                'manufacturer' => $m['manufacturer'] ?? null,
                'allow_worklist' => true,
                'is_active' => true,
            ]);
            $count++;
        }

        $this->showSyncFromOrthancModal = false;
        $this->orthancImportList = [];
        $this->loadOrthancAes();
        $this->dispatch('toast', type: 'success', message: "{$count} modality berhasil diimpor dari Orthanc.");
    }

    private function guessModalityType(string $ae): string
    {
        $upper = strtoupper($ae);
        foreach (['CT', 'MR', 'DR', 'CR', 'US', 'PT', 'NM', 'DX', 'MG', 'RF', 'XA'] as $type) {
            if (str_contains($upper, $type)) {
                return $type;
            }
        }
        return 'OT';
    }

    // --- Common Actions ---

    public function openEcho(string $id): void
    {
        $this->checkingId = $id;
        $this->showEchoModal = true;
    }

    private function resetRouterForm(): void
    {
        $this->reset(['editingId', 'routerName', 'routerAe', 'routerHost', 'routerPort', 'routerDesc', 'routerActive']);
        $this->routerPort = 104;
        $this->routerActive = true;
        $this->resetValidation();
    }

    private function resetModalityForm(): void
    {
        $this->reset(['editingId', 'modRouterId', 'modAe', 'modDesc', 'modIp', 'modPort', 'modType', 'modManuf', 'modWorklist', 'modActive', 'modNotes']);
        $this->modWorklist = true;
        $this->modActive = true;
        $this->resetValidation();
    }

    public function with(): array
    {
        if ($this->tab === 'router') {
            $items = DicomRouter::query()->when($this->search, fn($q) => $q->where(fn($q) => $q->where('ae_title', 'like', "%{$this->search}%")->orWhere('name', 'like', "%{$this->search}%")))->orderBy('name')->paginate(15);
            return ['items' => $items];
        } else {
            $items = DicomModality::query()->with('router')->when($this->search, fn($q) => $q->where(fn($q) => $q->where('ae_title', 'like', "%{$this->search}%")->orWhereHas('router', fn($qr) => $qr->where('name', 'like', "%{$this->search}%"))))->orderBy('modality_type')->paginate(15);
            $routers = DicomRouter::active()->get();
            return ['items' => $items, 'routers' => $routers];
        }
    }
}; ?>

<div>
    <x-ui.page-header title="Infrastruktur DICOM" subtitle="Kelola Router dan Modality DICOM dalam satu tempat.">
        <x-slot:actions>
            @if ($tab === 'router')
                <div x-data="{ open: false }" class="relative">
                    <x-atoms.button icon="bolt" variant="ghost" @click="open = !open" @click.outside="open = false">
                        Preset
                        <flux:icon name="chevron-down" class="w-3.5 h-3.5 ml-1" />
                    </x-atoms.button>
                    <div x-show="open" x-cloak x-transition
                        class="absolute right-0 top-full mt-1 w-72 bg-white dark:bg-primary-dark-800 rounded-xl border border-zinc-200 dark:border-primary-dark-700 shadow-lg z-50 overflow-hidden">
                        <div class="px-3 py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                            <p
                                class="text-xs font-semibold text-zinc-500 dark:text-primary-dark-400 uppercase tracking-wide">
                                Preset Router</p>
                        </div>
                        <div class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
                            <button type="button" @click="open = false" wire:click="applyRouterPreset('orthanc')"
                                class="w-full flex items-start gap-3 px-4 py-3 text-left hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50 transition-colors">
                                <flux:icon name="server" class="w-4 h-4 text-blue-500 shrink-0 mt-0.5" />
                                <div>
                                    <p class="text-sm font-medium text-zinc-800 dark:text-primary-dark-200">Orthanc PACS
                                        Lokal</p>
                                    <p class="text-xs text-zinc-400 mt-0.5">127.0.0.1:4242 &middot; AE: ORTHANC</p>
                                </div>
                            </button>
                            <button type="button" @click="open = false"
                                wire:click="applyRouterPreset('satusehat_prod')"
                                class="w-full flex items-start gap-3 px-4 py-3 text-left hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50 transition-colors">
                                <flux:icon name="globe-alt" class="w-4 h-4 text-green-500 shrink-0 mt-0.5" />
                                <div>
                                    <p class="text-sm font-medium text-zinc-800 dark:text-primary-dark-200">
                                        SatuSehat Router <flux:badge color="green" size="sm">Prod</flux:badge>
                                    </p>
                                    <p class="text-xs text-zinc-400 mt-0.5">dicom-router.kemkes.go.id:11112</p>
                                </div>
                            </button>
                            <button type="button" @click="open = false" wire:click="applyRouterPreset('satusehat_stg')"
                                class="w-full flex items-start gap-3 px-4 py-3 text-left hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50 transition-colors">
                                <flux:icon name="globe-alt" class="w-4 h-4 text-amber-400 shrink-0 mt-0.5" />
                                <div>
                                    <p class="text-sm font-medium text-zinc-800 dark:text-primary-dark-200">
                                        SatuSehat Router <flux:badge color="amber" size="sm">Staging</flux:badge>
                                    </p>
                                    <p class="text-xs text-zinc-400 mt-0.5">dicom-router-stg.kemkes.go.id:11112</p>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
                <x-atoms.button icon="plus" variant="primary" wire:click="openCreateRouter">Tambah
                    Router</x-atoms.button>
            @else
                <x-atoms.button icon="arrow-down-tray" variant="ghost" wire:click="openSyncFromOrthanc">Sinkron dari
                    Orthanc</x-atoms.button>
                <x-atoms.button icon="plus" variant="primary" wire:click="openCreateModality">Tambah
                    Modality</x-atoms.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <x-molecules.tabs>
        <x-atoms.tab-item :active="$tab === 'router'" wire:click="$set('tab', 'router')">
            DICOM Routers
        </x-atoms.tab-item>
        <x-atoms.tab-item :active="$tab === 'modality'" wire:click="$set('tab', 'modality')">
            DICOM Modalities
        </x-atoms.tab-item>
    </x-molecules.tabs>

    <x-organisms.data-panel>
        <x-slot:filter>
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari..." icon="magnifying-glass" />
        </x-slot:filter>

        @if ($tab === 'router')
            {{-- Table Router --}}
            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading>Nama Router</x-atoms.table-heading>
                    <x-atoms.table-heading>AE Title</x-atoms.table-heading>
                    <x-atoms.table-heading>Host / IP</x-atoms.table-heading>
                    <x-atoms.table-heading>Port</x-atoms.table-heading>
                    <x-atoms.table-heading>Status</x-atoms.table-heading>
                    <x-atoms.table-heading>Aksi</x-atoms.table-heading>
                </x-slot:headings>
                @forelse ($items as $r)
                    <x-molecules.table-row>
                        <x-atoms.table-cell>
                            <span
                                class="font-semibold text-zinc-900 dark:text-primary-dark-100">{{ $r->name }}</span>
                            @if ($r->description)
                                <p class="text-xs text-zinc-500">{{ $r->description }}</p>
                            @endif
                        </x-atoms.table-cell>
                        <x-atoms.table-cell
                            class="font-mono text-zinc-700 dark:text-primary-dark-200">{{ $r->ae_title }}</x-atoms.table-cell>
                        <x-atoms.table-cell
                            class="font-mono text-zinc-700 dark:text-primary-dark-200">{{ $r->host }}</x-atoms.table-cell>
                        <x-atoms.table-cell
                            class="font-mono text-zinc-700 dark:text-primary-dark-200">{{ $r->port }}</x-atoms.table-cell>
                        <x-atoms.table-cell>
                            <flux:badge :color="$r->is_active ? 'green' : 'zinc'" size="sm">
                                {{ $r->is_active ? 'Aktif' : 'Nonaktif' }}
                            </flux:badge>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                <x-atoms.button size="sm" icon="signal" variant="ghost"
                                    wire:click="openEcho('{{ $r->id }}')" />
                                <x-atoms.button size="sm" icon="pencil-square" variant="ghost"
                                    wire:click="openEditRouter('{{ $r->id }}')" />
                                <x-atoms.button size="sm" icon="trash" variant="ghost" class="text-red-500"
                                    wire:click="confirmDeleteRouter('{{ $r->id }}', '{{ $r->name }}')" />
                            </div>
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @empty
                    <x-molecules.table-row><x-atoms.table-cell colspan="6" align="center"
                            class="py-12 text-zinc-400">Belum ada router.</x-atoms.table-cell></x-molecules.table-row>
                @endforelse
            </x-organisms.table>
        @else
            {{-- Table Modality --}}
            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading>Tipe</x-atoms.table-heading>
                    <x-atoms.table-heading>Router</x-atoms.table-heading>
                    <x-atoms.table-heading>AE Title</x-atoms.table-heading>
                    <x-atoms.table-heading>Host & Port</x-atoms.table-heading>
                    <x-atoms.table-heading>Sync</x-atoms.table-heading>
                    <x-atoms.table-heading>Status</x-atoms.table-heading>
                    <x-atoms.table-heading>Aksi</x-atoms.table-heading>
                </x-slot:headings>
                @forelse ($items as $m)
                    @php
                        $ae = $m->ae_title ?? ($m->router->ae_title ?? '-');
                        $host = $m->ip_address ?? ($m->router->host ?? '-');
                        $port = $m->port ?? ($m->router->port ?? '-');
                        $isSynced = in_array($ae, $orthancAes);
                    @endphp
                    <x-molecules.table-row>
                        <x-atoms.table-cell>
                            <flux:badge color="blue" size="sm" class="font-bold aspect-square">
                                {{ $m->modality_type }}
                            </flux:badge>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell><span
                                class="font-medium text-zinc-900">{{ $m->router->name ?? 'N/A' }}</span></x-atoms.table-cell>
                        <x-atoms.table-cell>
                            <span
                                class="font-mono {{ $m->ae_title ? 'text-blue-600 font-bold' : 'text-zinc-600' }}">{{ $ae }}</span>
                            @if ($m->ae_title)
                                <flux:badge size="xs" color="blue" class="ml-1">ovr</flux:badge>
                            @endif
                        </x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs">
                            <span
                                class="{{ $m->ip_address ? 'text-blue-600 font-bold' : 'text-zinc-600' }}">{{ $host }}</span>:{{ $port }}
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            <flux:badge :color="$isSynced ? 'green' : 'zinc'" size="sm"
                                :icon="$isSynced ? 'check' : 'minus'" />
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            <flux:badge :color="$m->is_active ? 'green' : 'zinc'" size="sm">
                                {{ $m->is_active ? 'Aktif' : 'Nonaktif' }}
                            </flux:badge>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                <x-atoms.button size="sm" icon="signal" variant="ghost"
                                    wire:click="openEcho('{{ $m->id }}')" />
                                <x-atoms.button size="sm" icon="arrow-path" variant="ghost"
                                    wire:click="syncToOrthanc('{{ $m->id }}')" />
                                <x-atoms.button size="sm" icon="pencil-square" variant="ghost"
                                    wire:click="openEditModality('{{ $m->id }}')" />
                                <x-atoms.button size="sm" icon="trash" variant="ghost" class="text-red-500"
                                    wire:click="confirmDeleteModality('{{ $m->id }}', '{{ $m->modality_type }}')" />
                            </div>
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @empty
                    <x-molecules.table-row><x-atoms.table-cell colspan="7" align="center"
                            class="py-12 text-zinc-400">Belum ada
                            modality.</x-atoms.table-cell></x-molecules.table-row>
                @endforelse
            </x-organisms.table>
        @endif

        @if ($items->hasPages())
            <x-slot:footer>
                <div class="px-6 py-4">{{ $items->links() }}</div>
            </x-slot:footer>
        @endif
    </x-organisms.data-panel>

    {{-- --- Modals --- --}}

    {{-- Modal Router / Modality --}}
    @php
        $modalTitle =
            $tab === 'router'
                ? ($editingId
                    ? 'Edit Router'
                    : 'Tambah Router')
                : ($editingId
                    ? 'Edit Modality'
                    : 'Tambah Modality');
    @endphp
    <x-organisms.modal wire:model="showModal" name="modal-router" title="{{ $modalTitle }}" maxWidth="lg">
        @if ($tab === 'router')
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <flux:input wire:model="routerName" label="Nama Router *" />
                </div>
                <div class="col-span-2">
                    <flux:input wire:model="routerAe" label="AE Title *" style="text-transform:uppercase"
                        maxlength="16" />
                </div>
                <div>
                    <flux:input wire:model="routerHost" label="Host / IP Address *" />
                </div>
                <div>
                    <flux:input type="number" wire:model="routerPort" label="Port *" />
                </div>
                <div class="col-span-2">
                    <flux:textarea wire:model="routerDesc" label="Keterangan" rows="2" />
                </div>
                <div class="col-span-2">
                    <flux:checkbox wire:model="routerActive" label="Aktif" />
                </div>
            </div>
        @else
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <flux:select wire:model="modRouterId" label="Router DICOM *">
                        <flux:select.option value="">-- Pilih Router --</flux:select.option>
                        @foreach ($routers as $r)
                            <flux:select.option value="{{ $r->id }}">{{ $r->name }}
                                ({{ $r->ae_title }})</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <div class="col-span-2">
                    <flux:select wire:model="modType" label="Tipe Modality">
                        <flux:select.option value="">— (tidak ada)</flux:select.option>
                        @foreach (['CT', 'MR', 'DR', 'CR', 'US', 'PT', 'NM', 'DX', 'MG', 'RF', 'XA', 'OT'] as $type)
                            <flux:select.option value="{{ $type }}">{{ $type }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <div class="col-span-2">
                    <flux:separator label="Override (Opsional)" />
                </div>
                <div class="col-span-2">
                    <flux:input wire:model="modAe" label="Override AE Title" style="text-transform:uppercase"
                        maxlength="16" />
                </div>
                <div>
                    <flux:input wire:model="modIp" label="Override IP Address" />
                </div>
                <div>
                    <flux:input type="number" wire:model="modPort" label="Override Port" />
                </div>
                <div class="col-span-2 flex gap-6">
                    <flux:checkbox wire:model="modWorklist" label="Terima Worklist" />
                    <flux:checkbox wire:model="modActive" label="Aktif" />
                </div>
            </div>
        @endif
        <x-slot:footer>
            <div class="flex justify-end gap-3">
                <x-atoms.button variant="ghost" wire:click="$set('showModal', false)">Batal</x-atoms.button>
                <x-atoms.button variant="primary"
                    wire:click="{{ $tab === 'router' ? 'saveRouter' : 'saveModality' }}">Simpan</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal Hapus --}}
    <x-organisms.modal wire:model="showDeleteModal" name="modal-delete" title="Hapus Data" maxWidth="sm">
        <p class="text-sm text-zinc-600 dark:text-primary-dark-300">
            Hapus <strong class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $deleteName }}</strong>?
        </p>
        <x-slot:footer>
            <div class="flex justify-end gap-3">
                <x-atoms.button variant="ghost" wire:click="$set('showDeleteModal', false)">Batal</x-atoms.button>
                <x-atoms.button variant="danger"
                    wire:click="{{ $tab === 'router' ? 'destroyRouter' : 'destroyModality' }}">Hapus</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal Sinkron dari Orthanc --}}
    <x-organisms.modal wire:model="showSyncFromOrthancModal" name="modal-sync-orthanc"
        title="Sinkron Modality dari Orthanc" maxWidth="4xl">
        @if (!empty($orthancImportList))
            @php $toImport = collect($orthancImportList)->where('exists', false); @endphp
            <div class="space-y-3">
                <p class="text-sm text-zinc-500 dark:text-primary-dark-400">
                    Ditemukan <strong>{{ count($orthancImportList) }}</strong> modality di Orthanc.
                    <strong>{{ $toImport->count() }}</strong> belum terdaftar secara lokal. Anda bisa mengedit nilai
                    masing-masing kolom sebelum mengimpor.
                </p>

                <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-primary-dark-700">
                    <table class="min-w-full divide-y divide-zinc-100 dark:divide-primary-dark-700 text-sm">
                        <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-zinc-500">AE Title</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-zinc-500">Host</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-zinc-500">Port</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-zinc-500">Manufacturer</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-zinc-500">Tipe</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-zinc-500">Router Tujuan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
                            @foreach ($orthancImportList as $index => $m)
                                <tr @class(['opacity-50' => $m['exists']])>
                                    <td
                                        class="px-3 py-2 font-mono font-semibold text-zinc-800 dark:text-primary-dark-200">
                                        {{ $m['ae'] }}
                                        @if ($m['exists'])
                                            <flux:badge color="zinc" size="sm" class="ml-2">Sudah ada
                                            </flux:badge>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">
                                        @if ($m['exists'])
                                            <span
                                                class="font-mono text-xs text-zinc-600 dark:text-primary-dark-300">{{ $m['host'] ?? '-' }}</span>
                                        @else
                                            <flux:input wire:model="orthancImportList.{{ $index }}.host"
                                                size="sm" placeholder="127.0.0.1" />
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">
                                        @if ($m['exists'])
                                            <span
                                                class="font-mono text-xs text-zinc-600 dark:text-primary-dark-300">{{ $m['port'] ?? '-' }}</span>
                                        @else
                                            <flux:input type="number"
                                                wire:model="orthancImportList.{{ $index }}.port"
                                                size="sm" class="w-20" placeholder="104" />
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">
                                        @if ($m['exists'])
                                            <span class="text-xs text-zinc-500">{{ $m['manufacturer'] ?? '-' }}</span>
                                        @else
                                            <flux:input
                                                wire:model="orthancImportList.{{ $index }}.manufacturer"
                                                size="sm" placeholder="STORESCP" />
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">
                                        @if ($m['exists'])
                                            <flux:badge color="blue" size="sm">{{ $m['type'] }}
                                            </flux:badge>
                                        @else
                                            <flux:select wire:model="orthancImportList.{{ $index }}.type"
                                                size="sm" class="w-24">
                                                @foreach (['CT', 'MR', 'DR', 'CR', 'US', 'PT', 'NM', 'DX', 'MG', 'RF', 'XA', 'OT'] as $type)
                                                    <flux:select.option value="{{ $type }}">
                                                        {{ $type }}</flux:select.option>
                                                @endforeach
                                            </flux:select>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">
                                        @if ($m['exists'])
                                            <span class="text-xs text-zinc-400">-</span>
                                        @else
                                            <flux:select wire:model="orthancImportList.{{ $index }}.router_id"
                                                size="sm" class="w-36">
                                                <flux:select.option value="">-- Pilih --</flux:select.option>
                                                @foreach (\App\Models\Dicom\DicomRouter::active()->get() as $r)
                                                    <flux:select.option value="{{ $r->id }}">
                                                        {{ $r->name }}</flux:select.option>
                                                @endforeach
                                            </flux:select>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <p class="text-sm text-zinc-500 text-center py-6">Semua modality di Orthanc sudah terdaftar secara lokal.
            </p>
        @endif
        <x-slot:footer>
            <div class="flex justify-end gap-3">
                <x-atoms.button variant="ghost"
                    wire:click="$set('showSyncFromOrthancModal', false)">Tutup</x-atoms.button>
                @if (!empty($orthancImportList) && collect($orthancImportList)->where('exists', false)->isNotEmpty())
                    <x-atoms.button variant="primary" icon="arrow-down-tray" wire:click="importFromOrthanc">
                        Import {{ collect($orthancImportList)->where('exists', false)->count() }} Modality
                    </x-atoms.button>
                @endif
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal Echo --}}
    <x-organisms.modal wire:model="showEchoModal" name="modal-echo" title="Diagnostik DICOM" maxWidth="lg">
        @if ($checkingId)
            @php
                if ($tab === 'router') {
                    $r = \App\Models\Dicom\DicomRouter::find($checkingId);
                    $ae = $r?->ae_title;
                    $host = $r?->host;
                    $port = $r?->port;
                    $manuf = 'STORESCP';
                } else {
                    $m = \App\Models\Dicom\DicomModality::with('router')->find($checkingId);
                    $ae = $m?->ae_title ?? $m?->router?->ae_title;
                    $host = $m?->ip_address ?? $m?->router?->host;
                    $port = $m?->port ?? $m?->router?->port;
                    $manuf = $m?->manufacturer ?? 'STORESCP';
                }
            @endphp
            @if ($ae)
                <livewire:components.dicom-checker wire:key="echo-{{ $tab }}-{{ $checkingId }}"
                    ae="{{ $ae }}" host="{{ $host }}" port="{{ $port }}"
                    manufacturer="{{ $manuf }}" lazy />
            @endif
        @endif
        <x-slot:footer>
            <div class="flex justify-end">
                <x-atoms.button variant="ghost" wire:click="$set('showEchoModal', false)">Tutup</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>
</div>
