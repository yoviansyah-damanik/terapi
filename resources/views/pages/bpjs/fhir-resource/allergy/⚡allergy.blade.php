<?php

use App\Jobs\SyncBpjsAllergiesJob;
use App\Models\Bpjs\BpjsAllergy;
use App\Models\Simrs\Alergi;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('BPJS — Allergy')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterStatus = '';

    public bool $showSyncModal = false;

    public bool $showDetailModal = false;
    public ?BpjsAllergy $selectedItem = null;

    public bool $showDeleteModal = false;
    public ?string $deleteCode = null;
    public string $deleteName = '';

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedFilterStatus(): void { $this->resetPage(); }

    public function generateUuid(string $localCode, string $name): void
    {
        if (BpjsAllergy::where('local_code', $localCode)->exists()) {
            $this->toastWarning('Alergi ini sudah memiliki UUID BPJS.');
            return;
        }

        BpjsAllergy::create(['local_code' => $localCode, 'name' => $name]);
        $this->toastSuccess("UUID BPJS berhasil di-generate untuk: {$name}");
    }

    public function syncAll(): void
    {
        SyncBpjsAllergiesJob::dispatch();
        $this->showSyncModal = false;
        $this->toastSuccess('Sync semua Alergi dijadwalkan. Proses berjalan di background.');
    }

    public function viewDetail(string $localCode): void
    {
        $this->selectedItem = BpjsAllergy::where('local_code', $localCode)->first();
        $this->showDetailModal = true;
    }

    public function confirmDelete(string $localCode, string $name): void
    {
        $this->deleteCode = $localCode;
        $this->deleteName = $name;
        $this->showDeleteModal = true;
    }

    public function deleteEntry(): void
    {
        if (!$this->deleteCode) return;

        BpjsAllergy::where('local_code', $this->deleteCode)->delete();
        $this->showDeleteModal = false;
        $this->reset(['deleteCode', 'deleteName']);
        $this->toastSuccess('UUID BPJS Alergi berhasil dihapus.');
    }

    public function with(): array
    {
        $registered = BpjsAllergy::pluck('id', 'local_code');
        $totalRegistered = BpjsAllergy::count();

        $items = collect();
        $simrsError = false;
        $total = 0;

        try {
            $query = Alergi::query();

            if ($this->search) {
                $query->where(fn($q) => $q
                    ->where('id', 'like', "%{$this->search}%")
                    ->orWhere('nama_alergi', 'like', "%{$this->search}%")
                );
            }

            if ($this->filterStatus === 'registered') {
                $query->whereIn('id', $registered->keys()->toArray());
            } elseif ($this->filterStatus === 'unregistered') {
                $query->whereNotIn('id', $registered->keys()->toArray());
            }

            $total = Alergi::count();
            $items = $query->orderBy('nama_alergi')->paginate(25);
        } catch (\Exception) {
            $simrsError = true;
        }

        return [
            'items'           => $items,
            'registered'      => $registered,
            'total'           => $total,
            'totalRegistered' => $totalRegistered,
            'simrsError'      => $simrsError,
            'unsyncedCount'   => $simrsError ? 0 : max(0, $total - $totalRegistered),
        ];
    }
}; ?>

<div>
    <x-ui.page-header title="BPJS — Allergy" subtitle="Registry UUID FHIR AllergyIntolerance untuk alergi BPJS">
        <x-slot name="actions">
            <x-atoms.button wire:click="$set('showSyncModal', true)" variant="primary" icon="arrow-path" :disabled="$simrsError">
                Sync Semua
            </x-atoms.button>
        </x-slot>
    </x-ui.page-header>

    @if ($simrsError)
        <div class="flex items-center gap-3 p-4 mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
            <flux:icon name="exclamation-triangle" class="w-5 h-5 text-red-500 shrink-0" />
            <p class="text-sm text-red-700 dark:text-red-300">Koneksi ke database SIMRS gagal. Data tidak dapat ditampilkan.</p>
        </div>
    @endif

    <x-organisms.data-panel :padding="false">
        <x-slot:filter>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center flex-1">
                    <div class="flex-1 max-w-sm">
                        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                            placeholder="Cari ID atau nama alergi..." clearable />
                    </div>
                    <flux:select wire:model.live="filterStatus" class="sm:w-48">
                        <flux:select.option value="">Semua Status</flux:select.option>
                        <flux:select.option value="registered">Sudah Terdaftar</flux:select.option>
                        <flux:select.option value="unregistered">Belum Terdaftar</flux:select.option>
                    </flux:select>
                </div>
                <div class="hidden sm:flex items-center gap-2.5 px-3.5 py-2.5 text-xs font-medium bg-white dark:bg-primary-dark-800 rounded-xl border border-zinc-200 dark:border-primary-dark-700 whitespace-nowrap shadow-sm">
                    <span class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400">
                        <span class="inline-block w-2 h-2 rounded-full bg-emerald-400"></span>
                        {{ number_format($totalRegistered) }}{{ !$simrsError ? ' / ' . number_format($total) : '' }} terdaftar
                    </span>
                </div>
            </div>
        </x-slot:filter>

        <x-organisms.table>
            <x-slot:headings>
                <th class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500 w-20">ID</th>
                <th class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500">Nama Alergi</th>
                <th class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500">UUID BPJS</th>
                <th class="px-5 py-3.5 text-xs font-semibold tracking-wider text-center uppercase text-zinc-400 dark:text-primary-dark-500 w-32">Status</th>
                <th class="px-5 py-3.5 text-xs font-semibold tracking-wider text-center uppercase text-zinc-400 dark:text-primary-dark-500 w-32">Aksi</th>
            </x-slot:headings>

            @forelse ($items as $item)
                @php $uuid = $registered[(string) $item->id] ?? null; @endphp
                <x-molecules.table-row wire:key="alg-{{ $item->id }}">
                    <x-atoms.table-cell nowrap>
                        <span class="inline-block font-mono text-xs font-bold px-2 py-1 rounded-md bg-zinc-100 dark:bg-primary-dark-700 text-zinc-600 dark:text-primary-dark-300 ring-1 ring-zinc-200 dark:ring-primary-dark-600">
                            {{ $item->id }}
                        </span>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        <p class="text-sm font-medium text-zinc-800 dark:text-primary-dark-100">{{ $item->nama_alergi }}</p>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        @if ($uuid)
                            <span class="font-mono text-xs font-bold text-emerald-700 dark:text-emerald-400">{{ $uuid }}</span>
                        @else
                            <span class="text-xs italic text-zinc-400 dark:text-primary-dark-500">Belum terdaftar</span>
                        @endif
                    </x-atoms.table-cell>
                    <x-atoms.table-cell align="center">
                        @if ($uuid)
                            <flux:badge color="green" size="sm">Terdaftar</flux:badge>
                        @else
                            <span class="text-xs text-zinc-300 dark:text-primary-dark-600">—</span>
                        @endif
                    </x-atoms.table-cell>
                    <x-atoms.table-cell align="center" action>
                        @if (!$uuid)
                            <x-atoms.button
                                wire:click="generateUuid('{{ $item->id }}', '{{ addslashes($item->nama_alergi) }}')"
                                wire:target="generateUuid('{{ $item->id }}', '{{ addslashes($item->nama_alergi) }}')"
                                size="sm" variant="primary" icon="plus-circle">
                                Generate
                            </x-atoms.button>
                        @else
                            <x-atoms.button variant="ghost" wire:click="viewDetail('{{ $item->id }}')" size="sm" icon="eye" tooltip="Lihat detail" />
                            <x-atoms.button variant="ghost" wire:click="confirmDelete('{{ $item->id }}', '{{ addslashes($item->nama_alergi) }}')" size="sm" icon="trash" tooltip="Hapus UUID" />
                        @endif
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @empty
                <tr>
                    <td colspan="5" class="px-5 py-16 text-center">
                        <div class="flex flex-col items-center gap-3">
                            <div class="flex items-center justify-center w-14 h-14 rounded-2xl bg-zinc-100 dark:bg-primary-dark-700">
                                <flux:icon name="exclamation-circle" class="w-7 h-7 text-zinc-300 dark:text-primary-dark-500" />
                            </div>
                            <p class="text-sm font-semibold text-zinc-500 dark:text-primary-dark-400">
                                {{ $simrsError ? 'Koneksi SIMRS gagal' : 'Tidak ada data alergi ditemukan' }}
                            </p>
                        </div>
                    </td>
                </tr>
            @endforelse
        </x-organisms.table>

        @if ($items instanceof \Illuminate\Pagination\LengthAwarePaginator && $items->hasPages())
            <div class="px-5 py-4 border-t border-zinc-100 dark:border-primary-dark-700/60 bg-zinc-50/50 dark:bg-primary-dark-900/20">
                {{ $items->links() }}
            </div>
        @endif
    </x-organisms.data-panel>

    {{-- Modal Sync Semua --}}
    <x-organisms.modal wire:model="showSyncModal" title="Sync Semua Alergi" maxWidth="md">
        <div class="space-y-5">
            <div class="flex items-center gap-4">
                <div class="flex items-center justify-center w-12 h-12 rounded-full bg-primary-100 dark:bg-primary-900/30">
                    <flux:icon name="arrow-path" class="w-6 h-6 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <p class="text-sm text-zinc-500 dark:text-primary-dark-400">
                        {{ number_format($unsyncedCount) }} alergi belum memiliki UUID BPJS
                    </p>
                </div>
            </div>
            <div class="p-3.5 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-[11px] text-amber-800 dark:text-amber-200 leading-relaxed italic">
                <flux:icon name="exclamation-triangle" class="inline w-3.5 h-3.5 mr-1 text-amber-500" />
                UUID baru akan di-generate untuk semua alergi yang belum terdaftar. Proses berjalan di background (queue worker).
            </div>
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-atoms.button variant="ghost" wire:click="$set('showSyncModal', false)">Batal</x-atoms.button>
                <x-atoms.button variant="primary" icon="arrow-path" wire:click="syncAll" wire:target="syncAll">Mulai Sync</x-atoms.button>
            </div>
        </x-slot>
    </x-organisms.modal>

    {{-- Modal Detail --}}
    <x-organisms.modal wire:model="showDetailModal" title="Detail Allergy BPJS" maxWidth="lg">
        @if ($selectedItem)
            <x-slot name="description">
                <div class="flex items-center gap-2 mt-1">
                    <span class="font-mono text-sm font-bold text-zinc-500 dark:text-primary-dark-400">ID: {{ $selectedItem->local_code }}</span>
                    <flux:badge color="green" size="sm">Terdaftar</flux:badge>
                </div>
            </x-slot>
            <div class="space-y-6">
                <div>
                    <h2 class="text-base font-bold text-zinc-900 dark:text-white leading-tight">{{ $selectedItem->name }}</h2>
                </div>
                <div class="pt-5 border-t border-zinc-100 dark:border-primary-dark-700/60">
                    <p class="mb-4 text-[10px] font-bold uppercase tracking-widest text-zinc-400 dark:text-primary-dark-500">FHIR Details</p>
                    <dl class="space-y-5">
                        <div>
                            <dt class="text-[10px] uppercase font-bold text-zinc-400 dark:text-primary-dark-500 mb-1">Resource ID (UUID)</dt>
                            <dd class="font-mono text-sm font-bold text-emerald-600 dark:text-emerald-400 break-all leading-relaxed">{{ $selectedItem->id }}</dd>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <dt class="text-[10px] uppercase font-bold text-zinc-400 dark:text-primary-dark-500 mb-1">Local Code (SIMRS ID)</dt>
                                <dd class="font-mono text-sm text-zinc-600 dark:text-primary-dark-300">{{ $selectedItem->local_code }}</dd>
                            </div>
                            <div>
                                <dt class="text-[10px] uppercase font-bold text-zinc-400 dark:text-primary-dark-500 mb-1">Registered At</dt>
                                <dd class="text-sm text-zinc-600 dark:text-primary-dark-300">{{ $selectedItem->created_at?->format('d M Y, H:i') }}</dd>
                            </div>
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
    <x-organisms.modal wire:model="showDeleteModal" title="Hapus UUID Alergi?" maxWidth="md">
        <div class="space-y-5">
            <div class="flex items-center gap-4">
                <div class="flex items-center justify-center w-12 h-12 rounded-2xl bg-red-50 dark:bg-red-900/20 shrink-0">
                    <flux:icon name="trash" class="w-6 h-6 text-red-600 dark:text-red-400" />
                </div>
                <div>
                    <p class="text-sm text-zinc-500 dark:text-primary-dark-400">
                        UUID BPJS untuk <strong class="text-zinc-800 dark:text-white">{{ $deleteName }}</strong> akan dihapus.
                    </p>
                </div>
            </div>
            <div class="p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-[11px] text-amber-700 dark:text-amber-300 leading-relaxed italic">
                <flux:icon name="exclamation-triangle" class="inline w-3 h-3 mr-1" />
                UUID yang sudah digunakan di bundle BPJS tidak boleh dihapus untuk menjaga konsistensi data.
            </div>
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-atoms.button variant="ghost" wire:click="$set('showDeleteModal', false)">Batal</x-atoms.button>
                <x-atoms.button variant="danger" wire:click="deleteEntry" icon="trash">Hapus UUID</x-atoms.button>
            </div>
        </x-slot>
    </x-organisms.modal>
</div>
