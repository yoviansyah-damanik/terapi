<?php

use App\Jobs\SyncBpjsPractitionersJob;
use App\Models\Bpjs\BpjsPractitioner;
use App\Models\Simrs\Pegawai;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('BPJS — Practitioner')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterStatus = '';

    #[Url]
    public string $filterDepartment = '';

    public bool $showDetailModal = false;
    public ?BpjsPractitioner $selectedPractitioner = null;

    public bool $showDeleteModal = false;
    public ?string $deleteIdentifier = null;
    public string $deleteName = '';

    public bool $showSyncModal = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }
    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }
    public function updatedFilterDepartment(): void
    {
        $this->resetPage();
    }

    public function generateUuid(string $nik, string $name): void
    {
        if (BpjsPractitioner::where('identifier', $nik)->exists()) {
            $this->toastWarning('Petugas ini sudah memiliki UUID BPJS.');
            return;
        }

        BpjsPractitioner::create(['identifier' => $nik, 'name' => $name]);
        $this->toastSuccess("UUID BPJS berhasil di-generate untuk: {$name}");
    }

    public function viewDetail(string $identifier): void
    {
        $this->selectedPractitioner = BpjsPractitioner::where('identifier', $identifier)->first();
        $this->showDetailModal = true;
    }

    public function confirmDelete(string $identifier, string $name): void
    {
        $this->deleteIdentifier = $identifier;
        $this->deleteName = $name;
        $this->showDeleteModal = true;
    }

    public function deletePractitioner(): void
    {
        if (!$this->deleteIdentifier) {
            return;
        }

        BpjsPractitioner::where('identifier', $this->deleteIdentifier)->delete();
        $this->showDeleteModal = false;
        $this->reset(['deleteIdentifier', 'deleteName']);
        $this->toastSuccess('UUID BPJS Practitioner berhasil dihapus.');
    }

    public function syncAll(): void
    {
        SyncBpjsPractitionersJob::dispatch();
        $this->showSyncModal = false;
        $this->toastSuccess('Sync semua pegawai dijadwalkan. Proses berjalan di background.');
    }

    public function with(): array
    {
        $allPractitioners = BpjsPractitioner::all()->keyBy('identifier');
        $registeredNiks = $allPractitioners->keys()->toArray();

        $pegawaiList = collect();
        $simrsError = false;
        $totalPegawai = 0;

        try {
            $query = Pegawai::where('stts_aktif', 'AKTIF')->whereNotNull('nik')->where('nik', '!=', '');

            if ($this->search) {
                $query->where(fn($q) => $q->where('nama', 'like', "%{$this->search}%")->orWhere('nik', 'like', "%{$this->search}%"));
            }

            if ($this->filterStatus === 'registered') {
                $query->whereIn('nik', $registeredNiks);
            } elseif ($this->filterStatus === 'unregistered') {
                $query->whereNotIn('nik', $registeredNiks);
            }

            if ($this->filterDepartment === 'medis') {
                $query->where('bidang', 'Medis');
            } elseif ($this->filterDepartment === 'keperawatan') {
                $query->whereIn('bidang', ['Keperawatan', 'Kebidanan']);
            } elseif ($this->filterDepartment === 'penunjang') {
                $query->where('bidang', 'Penunjang Medis');
            } elseif ($this->filterDepartment === 'non_medis') {
                $query->whereNotIn('bidang', ['Medis', 'Keperawatan', 'Kebidanan', 'Penunjang Medis']);
            }

            $totalPegawai = Pegawai::where('stts_aktif', 'AKTIF')->whereNotNull('nik')->where('nik', '!=', '')->count();
            $pegawaiList = $query->orderBy('nama')->paginate(25);
        } catch (\Exception) {
            $simrsError = true;
        }

        $unsyncedCount = $simrsError ? 0 : max(0, $totalPegawai - $allPractitioners->count());

        return [
            'pegawaiList' => $pegawaiList,
            'allPractitioners' => $allPractitioners,
            'totalPegawai' => $totalPegawai,
            'totalRegistered' => $allPractitioners->count(),
            'unsyncedCount' => $unsyncedCount,
            'simrsError' => $simrsError,
        ];
    }
}; ?>

<div>
    <x-ui.page-header title="BPJS — Practitioner" subtitle="Registry UUID FHIR Practitioner untuk petugas BPJS">
        <x-slot name="actions">
            <x-atoms.button wire:click="$set('showSyncModal', true)" variant="primary" icon="arrow-path">
                Sync Semua
            </x-atoms.button>
        </x-slot>
    </x-ui.page-header>

    @if ($simrsError)
        <div
            class="flex items-center gap-3 p-4 mb-6 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
            <flux:icon name="exclamation-triangle" class="w-5 h-5 text-red-500 shrink-0" />
            <p class="text-sm text-red-700 dark:text-red-300">Koneksi ke database SIMRS gagal. Data pegawai tidak dapat
                ditampilkan.</p>
        </div>
    @endif


    {{-- Tabel --}}
    <x-organisms.data-panel :padding="false">
        {{-- Toolbar --}}
        <x-slot:filter>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between flex-wrap">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center flex-1 flex-wrap">
                    <div class="flex-1 max-w-sm">
                        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                            placeholder="Cari NIK atau nama pegawai..." clearable />
                    </div>
                    <flux:select wire:model.live="filterDepartment" class="sm:w-44">
                        <flux:select.option value="">Semua Bidang</flux:select.option>
                        <flux:select.option value="medis">Tenaga Medis</flux:select.option>
                        <flux:select.option value="keperawatan">Keperawatan/Kebidanan</flux:select.option>
                        <flux:select.option value="penunjang">Penunjang Medis</flux:select.option>
                        <flux:select.option value="non_medis">Non Medis</flux:select.option>
                    </flux:select>
                    <flux:select wire:model.live="filterStatus" class="sm:w-44">
                        <flux:select.option value="">Semua Status</flux:select.option>
                        <flux:select.option value="registered">Sudah Terdaftar</flux:select.option>
                        <flux:select.option value="unregistered">Belum Terdaftar</flux:select.option>
                    </flux:select>
                </div>
                <div
                    class="hidden sm:flex items-center gap-2.5 px-3.5 py-2.5 text-xs font-medium bg-white dark:bg-primary-dark-800 rounded-xl border border-zinc-200 dark:border-primary-dark-700 whitespace-nowrap shadow-sm">
                    <span class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400">
                        <span class="inline-block w-2 h-2 rounded-full bg-emerald-400"></span>
                        {{ $totalRegistered }} terdaftar
                    </span>
                    <span class="text-zinc-300 dark:text-primary-dark-600">/</span>
                    <span class="text-zinc-500 dark:text-primary-dark-400">{{ $totalPegawai }} pegawai aktif</span>
                </div>
            </div>
        </x-slot:filter>
        <x-organisms.table>
            <x-slot:headings>
                <th
                    class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500">
                    Pegawai</th>
                <th
                    class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500 hidden md:table-cell">
                    NIK</th>
                <th
                    class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500 hidden sm:table-cell">
                    Bidang</th>
                <th
                    class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500">
                    UUID BPJS</th>
                <th
                    class="px-5 py-3.5 text-xs font-semibold tracking-wider text-center uppercase text-zinc-400 dark:text-primary-dark-500 w-32">
                    Aksi</th>
            </x-slot:headings>
            @forelse ($pegawaiList as $emp)
                @php $practitioner = $allPractitioners[$emp->nik] ?? null; @endphp
                <x-molecules.table-row :key="$emp->nik">
                    <x-atoms.table-cell>
                        <p class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100 leading-tight">
                            {{ $emp->nama }}
                        </p>
                        <p class="text-[10px] text-zinc-500 dark:text-primary-dark-500 mt-1 uppercase tracking-wider">
                            {{ $emp->jbtn ?: '-' }}</p>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell class="hidden md:table-cell">
                        <span
                            class="font-mono text-xs text-zinc-600 dark:text-primary-dark-400">{{ $emp->nik }}</span>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell class="hidden sm:table-cell">
                        <span class="text-xs text-zinc-600 dark:text-primary-dark-400">{{ $emp->bidang ?: '-' }}</span>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        @if ($practitioner)
                            <div class="flex items-center gap-2">
                                <span
                                    class="w-2 h-2 rounded-full bg-emerald-400 dark:bg-emerald-500 shrink-0 shadow-[0_0_8px_rgba(52,211,153,0.4)]"></span>
                                <span
                                    class="font-mono text-xs font-bold text-emerald-700 dark:text-emerald-400">{{ $practitioner->id }}</span>
                            </div>
                        @else
                            <span class="text-xs italic text-zinc-400 dark:text-primary-dark-500 leading-none">Belum
                                terdaftar</span>
                        @endif
                    </x-atoms.table-cell>
                    <x-atoms.table-cell align="center" action>
                        @if (!$practitioner)
                            <x-atoms.button
                                wire:click="generateUuid('{{ $emp->nik }}', '{{ addslashes($emp->nama) }}')"
                                wire:target="generateUuid('{{ $emp->nik }}', '{{ addslashes($emp->nama) }}')"
                                size="sm" variant="primary" icon="plus-circle">
                                Generate
                            </x-atoms.button>
                        @else
                            <x-atoms.button variant="ghost" wire:click="viewDetail('{{ $emp->nik }}')"
                                size="sm" icon="eye" title="Lihat detail" />
                            <x-atoms.button variant="ghost"
                                wire:click="confirmDelete('{{ $emp->nik }}', '{{ addslashes($emp->nama) }}')"
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
                                <flux:icon name="user-group" class="w-7 h-7 text-zinc-300 dark:text-primary-dark-500" />
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-zinc-500 dark:text-primary-dark-400">
                                    {{ $simrsError ? 'Koneksi SIMRS gagal' : 'Tidak ada pegawai ditemukan' }}
                                </p>
                                <p class="mt-0.5 text-xs text-zinc-400 dark:text-primary-dark-500">
                                    {{ $simrsError ? 'Periksa konfigurasi database SIMRS' : 'Coba ubah filter pencarian' }}
                                </p>
                            </div>
                        </div>
                    </td>
                </tr>
            @endforelse
        </x-organisms.table>

        @if ($pegawaiList instanceof \Illuminate\Pagination\LengthAwarePaginator && $pegawaiList->hasPages())
            <div
                class="px-5 py-4 border-t border-zinc-100 dark:border-primary-dark-700/60 bg-zinc-50/50 dark:bg-primary-dark-900/20">
                {{ $pegawaiList->links() }}
            </div>
        @endif
    </x-organisms.data-panel>

    {{-- Modal Detail --}}
    <x-organisms.modal wire:model="showDetailModal" title="Detail Practitioner BPJS" maxWidth="lg">
        @if ($selectedPractitioner)
            <x-slot name="description">
                <div class="flex items-center gap-2 mt-1">
                    <span class="font-mono text-sm font-bold text-zinc-500 dark:text-primary-dark-400">
                        {{ $selectedPractitioner->identifier }}
                    </span>
                    <flux:badge color="green" size="sm">Terdaftar</flux:badge>
                </div>
            </x-slot>

            <div class="space-y-6">
                <div>
                    <h2 class="text-base font-bold text-zinc-900 dark:text-white leading-tight">
                        {{ $selectedPractitioner->name }}
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
                                {{ $selectedPractitioner->id }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[10px] uppercase font-bold text-zinc-400 dark:text-primary-dark-500 mb-1">
                                NIK (Identifier)</dt>
                            <dd class="font-mono text-sm text-zinc-600 dark:text-primary-dark-300">
                                {{ $selectedPractitioner->identifier }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[10px] uppercase font-bold text-zinc-400 dark:text-primary-dark-500 mb-1">
                                Registered At</dt>
                            <dd class="text-sm text-zinc-600 dark:text-primary-dark-300">
                                {{ $selectedPractitioner->created_at?->format('d M Y, H:i') }}
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

    {{-- Modal Sync Semua --}}
    <x-organisms.modal wire:model="showSyncModal" title="Sync Semua Pegawai" maxWidth="md">
        <div class="space-y-5">
            <div class="flex items-center gap-4">
                <div
                    class="flex items-center justify-center w-12 h-12 rounded-full bg-primary-100 dark:bg-primary-900/30">
                    <flux:icon name="arrow-path" class="w-6 h-6 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <p class="text-sm text-zinc-500 dark:text-primary-dark-400">
                        {{ number_format($unsyncedCount) }} pegawai aktif belum memiliki UUID BPJS
                    </p>
                </div>
            </div>
            <div
                class="p-3.5 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-[11px] text-amber-800 dark:text-amber-200 leading-relaxed italic">
                <flux:icon name="exclamation-triangle" class="inline w-3.5 h-3.5 mr-1 text-amber-500" />
                Hanya pegawai aktif dengan NIK yang akan disinkronkan. Proses berjalan di background (queue worker).
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

    {{-- Modal Konfirmasi Hapus --}}
    <x-organisms.modal wire:model="showDeleteModal" title="Hapus UUID Practitioner?" maxWidth="md">
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
                <x-atoms.button variant="danger" wire:click="deletePractitioner" icon="trash">Hapus
                    UUID</x-atoms.button>
            </div>
        </x-slot>
    </x-organisms.modal>
</div>
