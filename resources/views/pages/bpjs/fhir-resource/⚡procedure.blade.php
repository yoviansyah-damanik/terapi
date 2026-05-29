<?php

use App\Jobs\SyncBpjsProceduresJob;
use App\Models\Bpjs\BpjsProcedure;
use App\Models\Simrs\JnsPerawatan;
use App\Models\Simrs\JnsPerawatanInap;
use App\Models\Simrs\JnsPerawatanLab;
use App\Models\Simrs\JnsPerawatanRadiologi;
use App\Models\Simrs\TemplateLaboratorium;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('BPJS — Procedure')] class extends Component {
    use WithPagination;

    #[Url]
    public string $tab = 'ralan';

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterStatus = '';

    public bool $showSyncModal = false;
    public string $syncType = 'all';

    // Modal detail
    public bool $showDetailModal = false;
    public ?BpjsProcedure $selectedProcedure = null;

    // Modal hapus
    public bool $showDeleteModal = false;
    public ?string $deleteType = null;
    public ?string $deleteCode = null;
    public string $deleteName = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }
    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function switchTab(string $tab): void
    {
        $this->tab = $tab;
        $this->search = '';
        $this->filterStatus = '';
        $this->resetPage();
    }

    public function openSyncModal(string $type = 'all'): void
    {
        $this->syncType = $type;
        $this->showSyncModal = true;
    }

    public function syncAll(): void
    {
        SyncBpjsProceduresJob::dispatch($this->syncType);
        $this->showSyncModal = false;
        $label = $this->syncType === 'all' ? 'semua tipe' : $this->syncType;
        $this->toastSuccess("Sync procedure ({$label}) dijadwalkan. Proses berjalan di background.");
    }

    public function generateUuid(string $type, string $localCode, string $name): void
    {
        if (BpjsProcedure::where('type', $type)->where('local_code', $localCode)->exists()) {
            $this->toastWarning('Prosedur ini sudah memiliki UUID BPJS.');
            return;
        }

        BpjsProcedure::create(['type' => $type, 'local_code' => $localCode, 'name' => $name]);
        $this->toastSuccess("UUID BPJS berhasil di-generate untuk: {$name}");
    }

    public function viewDetail(string $type, string $localCode): void
    {
        $this->selectedProcedure = BpjsProcedure::where('type', $type)->where('local_code', $localCode)->first();
        $this->showDetailModal = true;
    }

    public function confirmDelete(string $type, string $localCode, string $name): void
    {
        $this->deleteType = $type;
        $this->deleteCode = $localCode;
        $this->deleteName = $name;
        $this->showDeleteModal = true;
    }

    public function deleteEntry(): void
    {
        if (!$this->deleteCode || !$this->deleteType) {
            return;
        }

        BpjsProcedure::where('type', $this->deleteType)->where('local_code', $this->deleteCode)->delete();

        $this->showDeleteModal = false;
        $this->reset(['deleteType', 'deleteCode', 'deleteName']);
        $this->toastSuccess('UUID BPJS berhasil dihapus.');
    }

    public function with(): array
    {
        $tab = $this->tab;

        // Jumlah terdaftar per tipe (satu query)
        $counts = BpjsProcedure::selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type');

        // UUID terdaftar untuk tab aktif
        $registered = BpjsProcedure::where('type', $tab)->get()->keyBy('local_code');

        $items = collect();
        $simrsError = false;

        try {
            $registeredKeys = $registered->keys()->toArray();

            if ($tab === 'item_lab') {
                $query = TemplateLaboratorium::query()->select('id_template', \DB::raw('MAX(Pemeriksaan) as Pemeriksaan'));

                if ($this->search) {
                    $query->where(fn($q) => $q->where('id_template', 'like', "%{$this->search}%")->orWhere('Pemeriksaan', 'like', "%{$this->search}%"));
                }

                if ($this->filterStatus === 'registered') {
                    $query->whereIn('id_template', $registeredKeys);
                } elseif ($this->filterStatus === 'unregistered') {
                    $query->whereNotIn('id_template', $registeredKeys);
                }

                $items = $query->groupBy('id_template')->orderBy('id_template')->paginate(25);
            } else {
                $modelClass = match ($tab) {
                    'ralan' => JnsPerawatan::class,
                    'ranap' => JnsPerawatanInap::class,
                    'lab' => JnsPerawatanLab::class,
                    'rad' => JnsPerawatanRadiologi::class,
                };

                $query = $modelClass::active();

                if ($this->search) {
                    $query->where(fn($q) => $q->where('kd_jenis_prw', 'like', "%{$this->search}%")->orWhere('nm_perawatan', 'like', "%{$this->search}%"));
                }

                if ($this->filterStatus === 'registered') {
                    $query->whereIn('kd_jenis_prw', $registeredKeys);
                } elseif ($this->filterStatus === 'unregistered') {
                    $query->whereNotIn('kd_jenis_prw', $registeredKeys);
                }

                $items = $query->orderBy('nm_perawatan')->paginate(25);
            }
        } catch (\Exception) {
            $simrsError = true;
        }

        // Jumlah per tipe dari SIMRS untuk modal sync
        $simrsCounts = [];
        if (!$simrsError) {
            try {
                $simrsCounts = [
                    'ralan' => JnsPerawatan::active()->count(),
                    'ranap' => JnsPerawatanInap::active()->count(),
                    'lab' => JnsPerawatanLab::active()->count(),
                    'item_lab' => TemplateLaboratorium::groupBy('id_template')->toBase()->getCountForPagination(),
                    'rad' => JnsPerawatanRadiologi::active()->count(),
                ];
            } catch (\Exception) {
            }
        }

        $registeredCounts = BpjsProcedure::selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type')->toArray();
        $unsyncedCount = array_sum(array_map(fn($t) => max(0, ($simrsCounts[$t] ?? 0) - ($registeredCounts[$t] ?? 0)), ['ralan', 'ranap', 'lab', 'item_lab', 'rad']));
        $unsyncedTabCount = max(0, ($simrsCounts[$tab] ?? 0) - ($registeredCounts[$tab] ?? 0));

        $tabLabel = match ($this->tab) {
            'ralan'    => 'Rawat Jalan',
            'ranap'    => 'Rawat Inap',
            'lab'      => 'Lab',
            'item_lab' => 'Item Lab',
            'rad'      => 'Radiologi',
            default    => $this->tab,
        };

        return [
            'items' => $items,
            'registered' => $registered,
            'counts' => $counts,
            'simrsError' => $simrsError,
            'simrsCounts' => $simrsCounts,
            'unsyncedCount' => $unsyncedCount,
            'unsyncedTabCount' => $unsyncedTabCount,
            'tabLabel' => $tabLabel,
            'tabTotal' => $simrsCounts[$this->tab] ?? 0,
            'tabRegistered' => $counts[$this->tab] ?? 0,
        ];
    }
}; ?>

<div>
    <x-ui.page-header title="BPJS — Procedure"
        subtitle="Registry UUID FHIR Procedure untuk tindakan ralan, ranap, lab, dan radiologi BPJS">
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

    {{-- Stat cards per tab --}}
    @if (!$simrsError)
        @php
            $tabPct = $tabTotal > 0 ? min(100, round(($tabRegistered / $tabTotal) * 100)) : 0;
            $tabColors = [
                'ralan' => ['color' => 'blue',    'icon' => 'user'],
                'ranap' => ['color' => 'violet',  'icon' => 'building-office'],
                'lab'   => ['color' => 'emerald', 'icon' => 'beaker'],
                'rad'   => ['color' => 'sky',     'icon' => 'photo'],
            ];
        @endphp

        @if ($tab === 'item_lab')
            {{-- Item Lab: template, tidak punya filter status aktif --}}
            <div class="grid grid-cols-3 gap-4 mb-5">
                <x-organisms.stat-card
                    title="Total Template Lab"
                    :value="number_format($tabTotal)"
                    color="amber"
                    icon="list-bullet"
                    subtitle="Template pemeriksaan di SIMRS" />
                <x-organisms.stat-card
                    title="UUID Terdaftar"
                    :value="number_format($tabRegistered)"
                    color="emerald"
                    icon="check-circle"
                    :subtitle="$tabPct . '% dari total template'" />
                <x-organisms.stat-card
                    title="Coverage"
                    :value="$tabPct . '%'"
                    :color="$tabPct >= 80 ? 'emerald' : ($tabPct >= 40 ? 'amber' : 'red')"
                    icon="chart-bar"
                    :subtitle="number_format($unsyncedTabCount) . ' template belum terdaftar'" />
            </div>
        @else
            {{-- Jenis pemeriksaan: ralan / ranap / lab / rad — punya filter status aktif --}}
            @php $tc = $tabColors[$tab]; @endphp
            <div class="grid grid-cols-3 gap-4 mb-5">
                <x-organisms.stat-card
                    title="Jenis {{ $tabLabel }} Aktif"
                    :value="number_format($tabTotal)"
                    :color="$tc['color']"
                    :icon="$tc['icon']"
                    subtitle="Status aktif di SIMRS" />
                <x-organisms.stat-card
                    title="UUID Terdaftar"
                    :value="number_format($tabRegistered)"
                    color="emerald"
                    icon="check-circle"
                    :subtitle="$tabPct . '% dari total aktif'" />
                <x-organisms.stat-card
                    title="Belum Terdaftar"
                    :value="number_format($unsyncedTabCount)"
                    :color="$unsyncedTabCount > 0 ? 'amber' : 'zinc'"
                    icon="arrow-path"
                    :subtitle="$unsyncedTabCount > 0 ? 'Perlu generate UUID' : 'Semua sudah terdaftar'" />
            </div>
        @endif
    @endif

    {{-- Tabs --}}
    <div class="flex gap-1 p-1 mb-5 bg-zinc-100 dark:bg-primary-dark-800/60 rounded-xl w-fit overflow-x-auto">
        @foreach ([
        'ralan' => ['label' => 'Rawat Jalan', 'icon' => 'user'],
        'ranap' => ['label' => 'Rawat Inap', 'icon' => 'building-office'],
        'lab' => ['label' => 'Lab', 'icon' => 'beaker'],
        'item_lab' => ['label' => 'Item Lab', 'icon' => 'list-bullet'],
        'rad' => ['label' => 'Radiologi', 'icon' => 'photo'],
    ] as $key => $meta)
            <x-atoms.button wire:click="switchTab('{{ $key }}')"
                class="flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg transition-all whitespace-nowrap
                    {{ $tab === $key
                        ? 'bg-white dark:bg-primary-dark-700 text-zinc-900 dark:text-white shadow-sm'
                        : 'text-zinc-500 dark:text-primary-dark-400 hover:text-zinc-700 dark:hover:text-primary-dark-200' }}">
                <flux:icon name="{{ $meta['icon'] }}" class="w-4 h-4" />
                {{ $meta['label'] }}
                @if (($counts[$key] ?? 0) > 0)
                    <span
                        class="text-xs font-semibold px-1.5 py-0.5 rounded-md
                        {{ $tab === $key ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400' : 'bg-zinc-200 dark:bg-primary-dark-700 text-zinc-500 dark:text-primary-dark-400' }}">
                        {{ $counts[$key] ?? 0 }}
                    </span>
                @endif
            </x-atoms.button>
        @endforeach
    </div>

    <x-organisms.data-panel :padding="false">
        {{-- Filter bar --}}
        <x-slot:filter>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <div class="max-w-sm">
                        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                            placeholder="Cari kode atau nama..." clearable />
                    </div>
                    <flux:select wire:model.live="filterStatus" class="w-48">
                        <flux:select.option value="">Semua Status</flux:select.option>
                        <flux:select.option value="registered">Sudah Terdaftar</flux:select.option>
                        <flux:select.option value="unregistered">Belum Terdaftar</flux:select.option>
                    </flux:select>
                </div>
                <div
                    class="hidden sm:flex items-center gap-2.5 px-3.5 py-2.5 text-xs font-medium bg-white dark:bg-primary-dark-800 rounded-xl border border-zinc-200 dark:border-primary-dark-700 whitespace-nowrap shadow-sm">
                    <span class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400">
                        <span class="inline-block w-2 h-2 rounded-full bg-emerald-400"></span>
                        {{ number_format($counts[$tab] ?? 0) }} terdaftar
                    </span>
                    @if (!$simrsError && ($simrsCounts[$tab] ?? 0) > 0)
                        <span class="text-zinc-300 dark:text-primary-dark-600">/</span>
                        <span
                            class="text-zinc-500 dark:text-primary-dark-400">{{ number_format($simrsCounts[$tab] ?? 0) }}
                            total</span>
                    @endif
                </div>
            </div>
        </x-slot:filter>
        <x-organisms.table>
            <x-slot:headings>
                <th
                    class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500 w-36">
                    Kode</th>
                <th
                    class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500">
                    Nama Prosedur</th>
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
            @forelse ($items as $item)
                @php
                    $isItemLab = $tab === 'item_lab';
                    $code = $isItemLab ? $item->id_template : $item->kd_jenis_prw;
                    $name = $isItemLab ? $item->Pemeriksaan : $item->nm_perawatan;
                    $entry = $registered[$code] ?? null;
                @endphp
                <x-molecules.table-row wire:key="{{ $tab }}-{{ $code }}">
                    <x-atoms.table-cell class="whitespace-nowrap">
                        <span
                            class="inline-block font-mono text-xs font-bold px-2 py-1 rounded-md
                            bg-zinc-100 dark:bg-primary-dark-700 text-zinc-600 dark:text-primary-dark-300
                            ring-1 ring-zinc-200 dark:ring-primary-dark-600 shadow-sm leading-none">
                            {{ $code }}
                        </span>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        <p class="text-sm font-medium text-zinc-800 dark:text-primary-dark-100">
                            {{ $name }}</p>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        @if ($entry)
                            <span
                                class="font-mono text-xs font-bold text-emerald-700 dark:text-emerald-400 leading-none">{{ $entry->id }}</span>
                        @else
                            <span class="text-xs italic text-zinc-400 dark:text-primary-dark-500 leading-none">Belum
                                terdaftar</span>
                        @endif
                    </x-atoms.table-cell>
                    <x-atoms.table-cell align="center">
                        @if ($entry)
                            <flux:badge color="green" size="sm" inset="top bottom">Terdaftar</flux:badge>
                        @else
                            <span class="text-xs text-zinc-300 dark:text-primary-dark-600">—</span>
                        @endif
                    </x-atoms.table-cell>
                    <x-atoms.table-cell align="center" action>
                        @if (!$entry)
                            <x-atoms.button
                                wire:click="generateUuid('{{ $tab }}', '{{ addslashes($code) }}', '{{ addslashes($name) }}')"
                                wire:target="generateUuid('{{ $tab }}', '{{ addslashes($code) }}', '{{ addslashes($name) }}')"
                                size="sm" variant="primary" icon="plus-circle">
                                Generate
                            </x-atoms.button>
                        @else
                            <x-atoms.button variant="ghost"
                                wire:click="viewDetail('{{ $tab }}', '{{ addslashes($code) }}')"
                                size="sm" icon="eye" title="Lihat detail" />
                            <x-atoms.button variant="ghost"
                                wire:click="confirmDelete('{{ $tab }}', '{{ addslashes($code) }}', '{{ addslashes($name) }}')"
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
                                <flux:icon name="clipboard-document-list"
                                    class="w-7 h-7 text-zinc-300 dark:text-primary-dark-500" />
                            </div>
                            <p class="text-sm font-semibold text-zinc-500 dark:text-primary-dark-400">
                                {{ $simrsError ? 'Koneksi SIMRS gagal' : 'Tidak ada data ditemukan' }}
                            </p>
                        </div>
                    </td>
                </tr>
            @endforelse
        </x-organisms.table>

        @if ($items instanceof \Illuminate\Pagination\LengthAwarePaginator && $items->hasPages())
            <div
                class="px-5 py-4 border-t border-zinc-100 dark:border-primary-dark-700/60 bg-zinc-50/50 dark:bg-primary-dark-900/20">
                {{ $items->links() }}
            </div>
        @endif
    </x-organisms.data-panel>

    {{-- Modal Sync --}}
    <x-organisms.modal wire:model="showSyncModal"
        title="{{ $syncType === 'all' ? 'Sync Semua Procedure' : 'Sync Tab ' . ucfirst(str_replace('_', ' ', $syncType)) }}"
        maxWidth="md">
        <div class="space-y-5">
            <div class="flex items-center gap-4">
                <div
                    class="flex items-center justify-center w-12 h-12 rounded-full bg-primary-100 dark:bg-primary-900/30 shrink-0">
                    <flux:icon name="arrow-path" class="w-6 h-6 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <p class="text-sm text-zinc-500 dark:text-primary-dark-400">
                        {{ number_format($syncType === 'all' ? $unsyncedCount : $unsyncedTabCount) }} prosedur belum
                        memiliki UUID BPJS
                    </p>
                </div>
            </div>
            <div
                class="p-3.5 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-[11px] text-amber-800 dark:text-amber-200 leading-relaxed italic">
                <flux:icon name="exclamation-triangle" class="inline w-3.5 h-3.5 mr-1 text-amber-500" />
                UUID baru akan di-generate untuk semua prosedur yang belum terdaftar. Proses berjalan di background
                (queue worker).
            </div>
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-atoms.button variant="ghost" wire:click="$set('showSyncModal', false)">Batal</x-atoms.button>
                <x-atoms.button variant="primary" icon="arrow-path" wire:click="syncAll" wire:target="syncAll">
                    Mulai Sync
                </x-atoms.button>
            </div>
        </x-slot>
    </x-organisms.modal>

    {{-- Modal Detail --}}
    <x-organisms.modal wire:model="showDetailModal" title="Detail Procedure BPJS" maxWidth="lg">
        @if ($selectedProcedure)
            @php
                $typeLabel = match ($selectedProcedure->type) {
                    'ralan' => 'Rawat Jalan',
                    'ranap' => 'Rawat Inap',
                    'lab' => 'Laboratorium',
                    'item_lab' => 'Item Lab',
                    'rad' => 'Radiologi',
                    default => $selectedProcedure->type,
                };
            @endphp
            <x-slot name="description">
                <div class="flex items-center gap-2 mt-1">
                    <flux:badge color="green" size="sm">Terdaftar</flux:badge>
                    <flux:badge color="zinc" size="sm">{{ $typeLabel }}</flux:badge>
                </div>
            </x-slot>

            <div class="space-y-6">
                <div>
                    <h2 class="text-base font-bold text-zinc-900 dark:text-white leading-tight">
                        {{ $selectedProcedure->name }}
                    </h2>
                </div>

                <div class="pt-5 border-t border-zinc-100 dark:border-primary-dark-700/60">
                    <p
                        class="mb-4 text-[10px] font-bold uppercase tracking-widest text-zinc-400 dark:text-primary-dark-500">
                        Procedure Identities
                    </p>
                    <dl class="space-y-5">
                        <div>
                            <dt class="text-[10px] uppercase font-bold text-zinc-400 dark:text-primary-dark-500 mb-1">
                                Resource ID (UUID)</dt>
                            <dd
                                class="font-mono text-sm font-bold text-emerald-600 dark:text-emerald-400 break-all leading-relaxed">
                                {{ $selectedProcedure->id }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[10px] uppercase font-bold text-zinc-400 dark:text-primary-dark-500 mb-1">
                                Local Code</dt>
                            <dd class="font-mono text-sm text-zinc-600 dark:text-primary-dark-300">
                                {{ $selectedProcedure->local_code }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[10px] uppercase font-bold text-zinc-400 dark:text-primary-dark-500 mb-1">
                                Registered At</dt>
                            <dd class="text-sm text-zinc-600 dark:text-primary-dark-300">
                                {{ $selectedProcedure->created_at?->format('d/m/Y H:i') }}
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
    <x-organisms.modal wire:model="showDeleteModal" title="Hapus UUID Procedure?" maxWidth="md">
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
                <x-atoms.button variant="danger" wire:click="deleteEntry" icon="trash">Hapus UUID</x-atoms.button>
            </div>
        </x-slot>
    </x-organisms.modal>
</div>
