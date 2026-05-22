<?php

use App\Models\Bpjs\BpjsOrganization;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('BPJS — Organization')] class extends Component {
    #[Url(as: 'q')]
    public string $search = '';

    public bool $showAddModal = false;
    public string $formIdentifier = '';
    public string $formName = '';
    public string $formAddress = '';

    public bool $showAutoModal = false;

    public bool $showDetailModal = false;
    public ?BpjsOrganization $selectedOrganization = null;

    public bool $showDeleteModal = false;
    public ?string $deleteId = null;
    public string $deleteName = '';

    /** Daftar preset untuk Tambah Otomatis */
    private function autoPresets(): array
    {
        return [['identifier' => 'rs', 'name' => config('hospital.name', 'RS Tk. IV 01.07.03 Padangsidimpuan')], ['identifier' => 'lab', 'name' => 'Laboratorium'], ['identifier' => 'igd', 'name' => 'Instalasi Gawat Darurat'], ['identifier' => 'far', 'name' => 'Farmasi'], ['identifier' => 'rad', 'name' => 'Radiologi'], ['identifier' => 'ri', 'name' => 'Rawat Inap'], ['identifier' => 'rj', 'name' => 'Rawat Jalan']];
    }

    public function autoGenerate(): void
    {
        $address = trim(implode(', ', array_filter([config('hospital.address'), config('hospital.city'), config('hospital.province')])));

        $inserted = 0;
        foreach ($this->autoPresets() as $preset) {
            $org = BpjsOrganization::firstOrCreate(['identifier' => $preset['identifier']], ['name' => $preset['name'], 'address' => $address ?: null]);
            if ($org->wasRecentlyCreated) {
                $inserted++;
            }
        }

        $this->showAutoModal = false;
        $this->toastSuccess("{$inserted} organisasi berhasil ditambahkan" . ($inserted < count($this->autoPresets()) ? ' (sebagian sudah ada)' : '.'));
    }

    public function openAddModal(): void
    {
        $this->reset(['formIdentifier', 'formName', 'formAddress']);
        $this->showAddModal = true;
    }

    public function saveOrganization(): void
    {
        $this->validate([
            'formIdentifier' => 'required|string|max:255',
            'formName' => 'required|string|max:255',
            'formAddress' => 'nullable|string|max:500',
        ]);

        if (BpjsOrganization::where('identifier', $this->formIdentifier)->exists()) {
            $this->addError('formIdentifier', 'Identifier sudah digunakan.');
            return;
        }

        BpjsOrganization::create([
            'identifier' => $this->formIdentifier,
            'name' => $this->formName,
            'address' => $this->formAddress ?: null,
        ]);

        $this->showAddModal = false;
        $this->reset(['formIdentifier', 'formName', 'formAddress']);
        $this->toastSuccess("UUID BPJS berhasil di-generate untuk organisasi: {$this->formName}");
    }

    public function viewDetail(string $id): void
    {
        $this->selectedOrganization = BpjsOrganization::find($id);
        $this->showDetailModal = true;
    }

    public function confirmDelete(string $id, string $name): void
    {
        $this->deleteId = $id;
        $this->deleteName = $name;
        $this->showDeleteModal = true;
    }

    public function deleteOrganization(): void
    {
        if (!$this->deleteId) {
            return;
        }

        BpjsOrganization::destroy($this->deleteId);
        $this->showDeleteModal = false;
        $this->reset(['deleteId', 'deleteName']);
        $this->toastSuccess('UUID BPJS Organization berhasil dihapus.');
    }

    public function with(): array
    {
        $query = BpjsOrganization::orderBy('name');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")->orWhere('identifier', 'like', "%{$this->search}%");
            });
        }

        return [
            'organizations' => $query->get(),
            'totalCount' => BpjsOrganization::count(),
            'autoPresets' => $this->autoPresets(),
            'existingIdentifiers' => BpjsOrganization::pluck('identifier')->toArray(),
        ];
    }
}; ?>

<div>
    <x-ui.page-header title="BPJS — Organization" subtitle="Registry UUID FHIR Organization untuk institusi BPJS">
        <x-slot name="actions">
            <x-atoms.button wire:click="$set('showAutoModal', true)" variant="ghost" icon="sparkles">
                Tambah Otomatis
            </x-atoms.button>
            <x-atoms.button wire:click="openAddModal" variant="primary" icon="plus">
                Tambah Manual
            </x-atoms.button>
        </x-slot>
    </x-ui.page-header>

    {{-- Tabel --}}
    <x-organisms.data-panel :padding="false">
        {{-- Filter & Stats bar --}}
        <x-slot:filter>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="relative w-full sm:max-w-xs">
                    <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari nama / identifier…"
                        icon="magnifying-glass" clearable />
                </div>
                <div
                    class="hidden sm:flex items-center gap-2.5 px-3.5 py-2.5 text-xs font-medium bg-white dark:bg-primary-dark-800 rounded-xl border border-zinc-200 dark:border-primary-dark-700 whitespace-nowrap shadow-sm shrink-0">
                    <span class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400">
                        <span class="inline-block w-2 h-2 rounded-full bg-emerald-400"></span>
                        {{ $totalCount }} terdaftar
                    </span>
                </div>
            </div>
        </x-slot:filter>
        <x-organisms.table>
            <x-slot:headings>
                <x-atoms.table-heading>Nama Organisasi</x-atoms.table-heading>
                <x-atoms.table-heading class="hidden md:table-cell">Identifier</x-atoms.table-heading>
                <x-atoms.table-heading>UUID BPJS</x-atoms.table-heading>
                <x-atoms.table-heading class="hidden lg:table-cell">Alamat</x-atoms.table-heading>
                <x-atoms.table-heading align="center" class="w-24">Aksi</x-atoms.table-heading>
            </x-slot:headings>
            @forelse ($organizations as $org)
                <x-molecules.table-row :key="$org->id">
                    <x-atoms.table-cell>
                        <p class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">
                            {{ $org->name }}
                        </p>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell class="hidden md:table-cell">
                        <span
                            class="font-mono text-xs text-zinc-600 dark:text-primary-dark-400">{{ $org->identifier }}</span>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        <span
                            class="font-mono text-xs font-bold text-emerald-700 dark:text-emerald-400">{{ $org->id }}</span>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell class="hidden lg:table-cell">
                        <span class="text-xs text-zinc-500 dark:text-primary-dark-400">{{ $org->address ?: '—' }}</span>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell align="center" action>
                        <x-atoms.button variant="ghost" wire:click="viewDetail('{{ $org->id }}')" size="sm"
                            icon="eye" title="Lihat detail" />
                        <x-atoms.button variant="ghost"
                            wire:click="confirmDelete('{{ $org->id }}', '{{ addslashes($org->name) }}')"
                            size="sm" icon="trash" title="Hapus" />
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @empty
                <x-molecules.table-row>
                    <x-atoms.table-cell colspan="5" class="py-16 text-center">
                        <div class="flex flex-col items-center gap-3">
                            <div
                                class="flex items-center justify-center w-14 h-14 rounded-2xl bg-zinc-100 dark:bg-primary-dark-700 mx-auto">
                                <flux:icon name="building-office"
                                    class="w-7 h-7 text-zinc-300 dark:text-primary-dark-500" />
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-zinc-500 dark:text-primary-dark-400">
                                    @if ($search)
                                        Tidak ada hasil untuk "{{ $search }}"
                                    @else
                                        Belum ada organisasi terdaftar
                                    @endif
                                </p>
                                @unless ($search)
                                    <p class="mt-0.5 text-xs text-zinc-400 dark:text-primary-dark-500">
                                        Klik "Tambah Otomatis" untuk menambahkan daftar unit RS, atau tambah manual.
                                    </p>
                                @endunless
                            </div>
                        </div>
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @endforelse
        </x-organisms.table>
    </x-organisms.data-panel>

    {{-- Modal Tambah Otomatis --}}
    <x-organisms.modal wire:model="showAutoModal" title="Tambah Otomatis" maxWidth="lg">
        <x-slot name="description">
            UUID BPJS akan di-generate untuk setiap unit. Identifier yang sudah ada akan dilewati.
        </x-slot>

        <div class="space-y-2">
            @foreach ($autoPresets as $preset)
                @php $exists = in_array($preset['identifier'], $existingIdentifiers); @endphp
                <div
                    class="flex items-center gap-3 px-4 py-3 rounded-xl border {{ $exists ? 'bg-zinc-50 dark:bg-primary-dark-900/30 border-zinc-100 dark:border-primary-dark-700/50 opacity-60' : 'bg-violet-50/30 dark:bg-violet-900/10 border-violet-100 dark:border-violet-900/30' }}">
                    <span
                        class="font-mono text-[10px] font-bold w-10 text-center px-1.5 py-1 rounded-md {{ $exists ? 'bg-zinc-200 dark:bg-primary-dark-700 text-zinc-500' : 'bg-violet-100 dark:bg-violet-900/50 text-violet-700 dark:text-violet-300' }}">
                        {{ $preset['identifier'] }}
                    </span>
                    <span
                        class="flex-1 text-sm font-medium text-zinc-700 dark:text-primary-dark-200">{{ $preset['name'] }}</span>
                    @if ($exists)
                        <flux:badge color="zinc" size="sm" inset="top bottom">Sudah ada</flux:badge>
                    @else
                        <flux:badge color="violet" size="sm" inset="top bottom">Baru</flux:badge>
                    @endif
                </div>
            @endforeach
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-3 pt-1">
                <x-atoms.button variant="ghost" wire:click="$set('showAutoModal', false)">Batal</x-atoms.button>
                <x-atoms.button variant="primary" wire:click="autoGenerate" wire:target="autoGenerate" icon="sparkles"
                    :disabled="count(array_diff(array_column($autoPresets, 'identifier'), $existingIdentifiers)) === 0">
                    Generate UUID
                </x-atoms.button>
            </div>
        </x-slot>
    </x-organisms.modal>

    {{-- Modal Tambah Manual --}}
    <x-organisms.modal wire:model="showAddModal" title="Tambah Organisasi Manual" maxWidth="lg">
        <x-slot name="description">
            UUID akan di-generate otomatis sebagai FHIR resource ID.
        </x-slot>

        <div class="space-y-6">
            <flux:field>
                <flux:label>Identifier <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model="formIdentifier" placeholder="Kode unik (contoh: rs, lab, ap)" />
                <flux:error name="formIdentifier" />
            </flux:field>
            <flux:field>
                <flux:label>Nama Organisasi <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model="formName" placeholder="Nama lengkap unit / organisasi" />
                <flux:error name="formName" />
            </flux:field>
            <flux:field>
                <flux:label>Alamat</flux:label>
                <flux:textarea wire:model="formAddress" placeholder="Alamat lengkap" rows="3" />
                <flux:error name="formAddress" />
            </flux:field>
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-3 pt-1">
                <x-atoms.button variant="ghost" wire:click="$set('showAddModal', false)">Batal</x-atoms.button>
                <x-atoms.button variant="primary" wire:click="saveOrganization" wire:target="saveOrganization"
                    icon="plus">
                    Generate UUID
                </x-atoms.button>
            </div>
        </x-slot>
    </x-organisms.modal>

    {{-- Modal Detail --}}
    <x-organisms.modal wire:model="showDetailModal" title="Detail Organization" maxWidth="lg">
        @if ($selectedOrganization)
            <x-slot name="description">
                <div class="flex items-center gap-2 mt-1">
                    <span class="font-mono text-sm font-bold text-zinc-500 dark:text-primary-dark-400">
                        {{ $selectedOrganization->identifier }}
                    </span>
                    <flux:badge color="green" size="sm">Terdaftar</flux:badge>
                </div>
            </x-slot>

            <div class="space-y-6">
                <div>
                    <h2 class="text-base font-bold text-zinc-900 dark:text-white leading-tight">
                        {{ $selectedOrganization->name }}
                    </h2>
                </div>

                <div class="pt-5 border-t border-zinc-100 dark:border-primary-dark-700/60">
                    <p
                        class="mb-4 text-[10px] font-bold uppercase tracking-widest text-zinc-400 dark:text-primary-dark-500">
                        FHIR Details
                    </p>
                    <dl class="space-y-5">
                        <div>
                            <dt class="text-[10px] uppercase font-bold text-zinc-400 dark:text-primary-dark-500 mb-1">
                                Resource ID (UUID)</dt>
                            <dd
                                class="font-mono text-sm font-bold text-emerald-600 dark:text-emerald-400 break-all leading-relaxed">
                                {{ $selectedOrganization->id }}
                            </dd>
                        </div>
                        @if ($selectedOrganization->address)
                            <div>
                                <dt
                                    class="text-[10px] uppercase font-bold text-zinc-400 dark:text-primary-dark-500 mb-1">
                                    Address</dt>
                                <dd class="text-sm text-zinc-600 dark:text-primary-dark-300 leading-relaxed italic">
                                    {{ $selectedOrganization->address }}
                                </dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-[10px] uppercase font-bold text-zinc-400 dark:text-primary-dark-500 mb-1">
                                Created At</dt>
                            <dd class="text-sm text-zinc-600 dark:text-primary-dark-300">
                                {{ $selectedOrganization->created_at?->format('d M Y, H:i') }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <x-slot name="footer">
                <div class="flex justify-end">
                    <x-atoms.button variant="ghost" wire:click="$set('showDetailModal', false)">Tutup</x-atoms.button>
                </div>
            </x-slot>
        @endif
    </x-organisms.modal>

    {{-- Modal Konfirmasi Hapus --}}
    <x-organisms.modal wire:model="showDeleteModal" title="Hapus UUID Organisasi?" maxWidth="md">
        <div class="space-y-5">
            <div class="flex items-center gap-4">
                <div
                    class="flex items-center justify-center w-12 h-12 rounded-2xl bg-red-50 dark:bg-red-900/20 shrink-0">
                    <flux:icon name="trash" class="w-6 h-6 text-red-600 dark:text-red-400" />
                </div>
                <div>
                    <p class="text-sm text-zinc-500 dark:text-primary-dark-400">
                        UUID BPJS untuk <strong class="text-zinc-800 dark:text-white">{{ $deleteName }}</strong>
                        akan dihapus.
                    </p>
                </div>
            </div>

            <div
                class="p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-[11px] text-amber-700 dark:text-amber-300 leading-relaxed italic">
                <flux:icon name="exclamation-triangle" class="inline w-3 h-3 mr-1 text-amber-500" />
                UUID yang sudah digunakan di bundle BPJS tidak boleh dihapus untuk menjaga konsistensi data.
            </div>
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-3 pt-1">
                <x-atoms.button variant="ghost" wire:click="$set('showDeleteModal', false)">Batal</x-atoms.button>
                <x-atoms.button variant="danger" wire:click="deleteOrganization" icon="trash">Hapus
                    UUID</x-atoms.button>
            </div>
        </x-slot>
    </x-organisms.modal>
</div>
