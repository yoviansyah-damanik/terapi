<?php

use App\Jobs\SyncBpjsIcd10Job;
use App\Models\Bpjs\BpjsIcd10;
use App\Models\Terminology\Icd10;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('BPJS — ICD-10')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterStatus = '';

    public bool $showSyncModal = false;

    // Modal detail
    public bool $showDetailModal = false;
    public ?BpjsIcd10 $selectedIcd = null;

    // Modal hapus
    public bool $showDeleteModal = false;
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

    public function generateUuid(string $code, string $display): void
    {
        if (BpjsIcd10::where('code', $code)->exists()) {
            $this->toastWarning('Kode ICD-10 ini sudah memiliki UUID BPJS.');
            return;
        }

        BpjsIcd10::create(['code' => $code, 'display' => $display]);
        $this->toastSuccess("UUID BPJS berhasil di-generate untuk {$code}: {$display}");
    }

    public function syncAll(): void
    {
        SyncBpjsIcd10Job::dispatch();
        $this->showSyncModal = false;
        $this->toastSuccess('Sync semua ICD-10 dijadwalkan. Proses berjalan di background.');
    }

    public function viewDetail(string $code): void
    {
        $this->selectedIcd = BpjsIcd10::where('code', $code)->first();
        $this->showDetailModal = true;
    }

    public function confirmDelete(string $code, string $display): void
    {
        $this->deleteCode = $code;
        $this->deleteName = $display;
        $this->showDeleteModal = true;
    }

    public function deleteEntry(): void
    {
        if (!$this->deleteCode) {
            return;
        }

        BpjsIcd10::where('code', $this->deleteCode)->delete();
        $this->showDeleteModal = false;
        $this->reset(['deleteCode', 'deleteName']);
        $this->toastSuccess('UUID BPJS ICD-10 berhasil dihapus.');
    }

    public function with(): array
    {
        $registered = BpjsIcd10::pluck('id', 'code');

        $query = Icd10::query()->select('code', \DB::raw('MAX(display) as display'));

        if ($this->search) {
            $query->where(fn($q) => $q->where('code', 'like', "%{$this->search}%")->orWhere('display', 'like', "%{$this->search}%"));
        }

        if ($this->filterStatus === 'registered') {
            $query->whereIn('code', $registered->keys()->toArray());
        } elseif ($this->filterStatus === 'unregistered') {
            $query->whereNotIn('code', $registered->keys()->toArray());
        }

        $records = $query->groupBy('code')->orderBy('code')->paginate(25);
        $total = Icd10::distinct('code')->count('code');
        $totalRegistered = BpjsIcd10::count();

        return [
            'records' => $records,
            'registered' => $registered,
            'total' => $total,
            'totalRegistered' => $totalRegistered,
            'unsyncedCount' => max(0, $total - $totalRegistered),
        ];
    }
}; ?>

<div>
    <x-ui.page-header title="BPJS — ICD-10" subtitle="Registry UUID FHIR Condition untuk kode diagnosa ICD-10 BPJS">
        <x-slot name="actions">
            <x-atoms.button wire:click="$set('showSyncModal', true)" variant="primary" icon="arrow-path">
                Sync Semua
            </x-atoms.button>
        </x-slot>
    </x-ui.page-header>

    {{-- Tabel --}}
    <x-organisms.data-panel :padding="false">
        {{-- Toolbar --}}
        <x-slot:filter>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center flex-1">
                    <div class="flex-1 max-w-sm">
                        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                            placeholder="Cari kode atau nama diagnosa..." clearable />
                    </div>
                    <flux:select wire:model.live="filterStatus" class="sm:w-48">
                        <flux:select.option value="">Semua Status</flux:select.option>
                        <flux:select.option value="registered">Sudah Terdaftar</flux:select.option>
                        <flux:select.option value="unregistered">Belum Terdaftar</flux:select.option>
                    </flux:select>
                </div>
                <div
                    class="hidden sm:flex items-center gap-2.5 px-3.5 py-2.5 text-xs font-medium bg-white dark:bg-primary-dark-800 rounded-xl border border-zinc-200 dark:border-primary-dark-700 whitespace-nowrap shadow-sm">
                    <span class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400">
                        <span class="inline-block w-2 h-2 rounded-full bg-emerald-400"></span>
                        {{ number_format($totalRegistered) }} / {{ number_format($total) }} terdaftar
                    </span>
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
                    Nama Diagnosa</th>
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
            @forelse ($records as $icd)
                @php
                    $uuid = $registered[$icd->code] ?? null;
                @endphp
                <x-molecules.table-row wire:key="icd10-{{ $icd->code }}">
                    <x-atoms.table-cell>
                        <span
                            class="inline-block font-mono text-xs font-bold px-2 py-1 rounded-md
                            bg-zinc-100 dark:bg-primary-dark-700 text-zinc-600 dark:text-primary-dark-300
                            ring-1 ring-zinc-200 dark:ring-primary-dark-600">
                            {{ $icd->code }}
                        </span>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        <p class="text-sm text-zinc-800 dark:text-primary-dark-100">{{ $icd->display }}</p>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        @if ($uuid)
                            <span
                                class="font-mono text-xs font-bold text-emerald-700 dark:text-emerald-400">{{ $uuid }}</span>
                        @else
                            <span class="text-xs italic text-zinc-400 dark:text-primary-dark-500">Belum
                                terdaftar</span>
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
                                wire:click="generateUuid('{{ addslashes($icd->code) }}', '{{ addslashes($icd->display) }}')"
                                size="sm" variant="primary" icon="plus-circle"
                                wire:target="generateUuid('{{ addslashes($icd->code) }}', '{{ addslashes($icd->display) }}')">
                                Generate
                            </x-atoms.button>
                        @else
                            <x-atoms.button variant="ghost" wire:click="viewDetail('{{ addslashes($icd->code) }}')"
                                size="sm" icon="eye" title="Lihat detail" />
                            <x-atoms.button variant="ghost"
                                wire:click="confirmDelete('{{ addslashes($icd->code) }}', '{{ addslashes($icd->display) }}')"
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
                                <flux:icon name="document-magnifying-glass"
                                    class="w-7 h-7 text-zinc-300 dark:text-primary-dark-500" />
                            </div>
                            <p class="text-sm font-semibold text-zinc-500 dark:text-primary-dark-400">Tidak ada
                                data
                                ICD-10 ditemukan</p>
                        </div>
                    </td>
                </tr>
            @endforelse
        </x-organisms.table>

        @if ($records->hasPages())
            <div
                class="px-5 py-4 border-t border-zinc-100 dark:border-primary-dark-700/60 bg-zinc-50/50 dark:bg-primary-dark-900/20">
                {{ $records->links() }}
            </div>
        @endif
    </x-organisms.data-panel>

    {{-- Modal Sync Semua --}}
    <x-organisms.modal wire:model="showSyncModal" title="Sync Semua ICD-10" maxWidth="md">
        <div class="space-y-5">
            <div class="flex items-center gap-4">
                <div
                    class="flex items-center justify-center w-12 h-12 rounded-full bg-primary-100 dark:bg-primary-900/30">
                    <flux:icon name="arrow-path" class="w-6 h-6 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <p class="text-sm text-zinc-500 dark:text-primary-dark-400">
                        {{ number_format($unsyncedCount) }} kode ICD-10 belum memiliki UUID BPJS
                    </p>
                </div>
            </div>
            <div
                class="p-3.5 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-xs text-amber-800 dark:text-amber-200">
                <flux:icon name="exclamation-triangle" class="inline w-4 h-4 mr-1 text-amber-500" />
                UUID baru akan di-generate untuk semua kode yang belum terdaftar. Proses berjalan di background (queue
                worker).
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
    <x-organisms.modal wire:model="showDetailModal" title="Detail ICD-10 BPJS" maxWidth="lg">
        @if ($selectedIcd)
            <x-slot name="description">
                <div class="flex items-center gap-2 mt-1">
                    <span class="font-mono text-sm font-bold text-zinc-500 dark:text-primary-dark-400">
                        {{ $selectedIcd->code }}
                    </span>
                    <flux:badge color="green" size="sm">Terdaftar</flux:badge>
                </div>
            </x-slot>

            <div class="space-y-5">
                <div>
                    <h2 class="text-base font-bold text-zinc-900 dark:text-white leading-tight">
                        {{ $selectedIcd->display }}
                    </h2>
                </div>

                <div class="pt-4 border-t border-zinc-100 dark:border-primary-dark-700/60">
                    <p
                        class="mb-3 text-[10px] font-bold uppercase tracking-widest text-zinc-400 dark:text-primary-dark-500">
                        Identitas FHIR</p>
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-[10px] uppercase font-bold text-zinc-400 dark:text-primary-dark-500 mb-1">
                                UUID BPJS</dt>
                            <dd
                                class="font-mono text-sm font-bold text-emerald-600 dark:text-emerald-400 break-all leading-relaxed">
                                {{ $selectedIcd->id }}</dd>
                        </div>
                        <div>
                            <dt class="text-[10px] uppercase font-bold text-zinc-400 dark:text-primary-dark-500 mb-1">
                                Dibuat</dt>
                            <dd class="text-sm text-zinc-700 dark:text-primary-dark-300">
                                {{ $selectedIcd->created_at?->format('d M Y, H:i') }}</dd>
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
    <x-organisms.modal wire:model="showDeleteModal" title="Hapus UUID?" maxWidth="md">
        <div class="space-y-5">
            <div class="flex items-center gap-4">
                <div
                    class="flex items-center justify-center w-12 h-12 rounded-2xl bg-red-50 dark:bg-red-900/20 shrink-0">
                    <flux:icon name="trash" class="w-6 h-6 text-red-600 dark:text-red-400" />
                </div>
                <div>
                    <p class="text-sm text-zinc-500 dark:text-primary-dark-400">
                        UUID BPJS untuk <strong class="text-zinc-800 dark:text-white">{{ $deleteCode }}</strong>
                        akan dihapus.
                    </p>
                </div>
            </div>

            <div
                class="p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-[11px] text-amber-700 dark:text-amber-300 leading-relaxed italic">
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
