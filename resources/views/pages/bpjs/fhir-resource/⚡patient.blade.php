<?php

use App\Jobs\SyncBpjsPatientsJob;
use App\Models\Bpjs\BpjsPatient;
use App\Models\Simrs\Pasien;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('BPJS — Patient')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    // Modal daftarkan dari SIMRS
    public bool $showRegisterModal = false;
    public string $registerSearch = '';
    public array $simrsResults = [];
    public bool $simrsError = false;

    // Modal detail
    public bool $showDetailModal = false;
    public ?BpjsPatient $selectedPatient = null;

    // Modal hapus
    public bool $showDeleteModal = false;
    public ?string $deleteId = null;
    public string $deleteName = '';

    // Modal sync semua
    public bool $showSyncModal = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openRegisterModal(): void
    {
        $this->reset(['registerSearch', 'simrsResults', 'simrsError']);
        $this->showRegisterModal = true;
    }

    public function searchSimrs(): void
    {
        $this->simrsResults = [];
        $this->simrsError = false;

        if (blank($this->registerSearch)) {
            return;
        }

        try {
            $registeredNiks = BpjsPatient::pluck('nik')->toArray();

            $results = Pasien::whereNotNull('no_ktp')
                ->where('no_ktp', '!=', '')
                ->where(
                    fn($q) => $q
                        ->where('nm_pasien', 'like', "%{$this->registerSearch}%")
                        ->orWhere('no_ktp', 'like', "%{$this->registerSearch}%")
                        ->orWhere('no_rkm_medis', 'like', "%{$this->registerSearch}%"),
                )
                ->orderBy('nm_pasien')
                ->limit(30)
                ->get();

            $this->simrsResults = $results
                ->map(
                    fn($p) => [
                        'no_rkm_medis' => $p->no_rkm_medis,
                        'nm_pasien' => $p->nm_pasien,
                        'no_ktp' => $p->no_ktp,
                        'jk' => $p->jk,
                        'tgl_lahir' => $p->tgl_lahir?->format('d/m/Y'),
                        'birth_date' => $p->tgl_lahir?->format('Y-m-d'),
                        'is_registered' => in_array($p->no_ktp, $registeredNiks),
                    ],
                )
                ->toArray();
        } catch (\Exception) {
            $this->simrsError = true;
        }
    }

    public function registerPatient(string $nik, string $name): void
    {
        if (BpjsPatient::where('nik', $nik)->exists()) {
            $this->toastWarning("Pasien dengan NIK {$nik} sudah terdaftar.");
            return;
        }

        BpjsPatient::create(['nik' => $nik]);

        // Refresh hasil pencarian agar badge berubah
        $this->searchSimrs();
        $this->toastSuccess("UUID BPJS berhasil di-generate untuk pasien: {$name}");
    }

    public function viewDetail(string $id): void
    {
        $this->selectedPatient = BpjsPatient::find($id);
        $this->showDetailModal = true;
    }

    public function confirmDelete(string $id, string $name): void
    {
        $this->deleteId = $id;
        $this->deleteName = $name;
        $this->showDeleteModal = true;
    }

    public function deletePatient(): void
    {
        if (!$this->deleteId) {
            return;
        }

        BpjsPatient::destroy($this->deleteId);
        $this->showDeleteModal = false;
        $this->reset(['deleteId', 'deleteName']);
        $this->toastSuccess('UUID BPJS Patient berhasil dihapus.');
    }

    public function syncAll(): void
    {
        SyncBpjsPatientsJob::dispatch();
        $this->showSyncModal = false;
        $this->toastSuccess('Sync semua pasien dijadwalkan. Proses berjalan di background.');
    }

    public function with(): array
    {
        $patients = BpjsPatient::query()->when($this->search, fn($q) => $q->where(fn($q2) => $q2->where('nik', 'like', "%{$this->search}%")->orWhere('name', 'like', "%{$this->search}%")))->latest()->paginate(25);

        $total = BpjsPatient::count();
        $unsyncedCount = 0;
        try {
            $unsyncedCount = Pasien::whereNotNull('no_ktp')
                ->where('no_ktp', '!=', '')
                ->whereNotIn('no_ktp', BpjsPatient::pluck('nik')->toArray())
                ->count();
        } catch (\Exception) {
        }

        return [
            'patients' => $patients,
            'total' => $total,
            'unsyncedCount' => $unsyncedCount,
        ];
    }
}; ?>

<div>
    <x-ui.page-header title="BPJS — Patient" subtitle="Registry UUID FHIR Patient untuk pasien BPJS">
        <x-slot name="actions">
            <x-atoms.button wire:click="openRegisterModal" variant="ghost" icon="user-plus">
                Daftarkan Pasien
            </x-atoms.button>
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
                <div class="flex-1 max-w-sm">
                    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                        placeholder="Cari NIK atau nama pasien..." clearable />
                </div>
                <div
                    class="hidden sm:flex items-center gap-2.5 px-3.5 py-2.5 text-xs font-medium bg-white dark:bg-primary-dark-800 rounded-xl border border-zinc-200 dark:border-primary-dark-700 whitespace-nowrap shadow-sm">
                    <span class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400">
                        <span class="inline-block w-2 h-2 rounded-full bg-emerald-400"></span>
                        {{ $total }} pasien terdaftar
                    </span>
                </div>
            </div>
        </x-slot:filter>

        <x-organisms.table>
            <x-slot:headings>
                <th
                    class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500">
                    Pasien</th>
                <th
                    class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500 hidden md:table-cell">
                    NIK</th>
                <th
                    class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500 hidden sm:table-cell">
                    Tgl Lahir</th>
                <th
                    class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500">
                    UUID BPJS</th>
                <th
                    class="px-5 py-3.5 text-xs font-semibold tracking-wider text-center uppercase text-zinc-400 dark:text-primary-dark-500 w-24">
                    Aksi</th>
            </x-slot:headings>
            @forelse ($patients as $patient)
                <x-molecules.table-row :key="$patient->id">
                    <x-atoms.table-cell>
                        <div class="flex items-center gap-3">
                            <div
                                class="flex-shrink-0 w-9 h-9 rounded-full flex items-center justify-center
                                        {{ $patient->gender === 'male' ? 'bg-blue-50 dark:bg-blue-900/40' : ($patient->gender === 'female' ? 'bg-pink-50 dark:bg-pink-900/40' : 'bg-zinc-100 dark:bg-primary-dark-700') }}">
                                <flux:icon name="user"
                                    class="w-4.5 h-4.5
                                            {{ $patient->gender === 'male' ? 'text-blue-500' : ($patient->gender === 'female' ? 'text-pink-500' : 'text-zinc-400') }}" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">
                                    {{ $patient->name }}</p>
                                @if ($patient->gender)
                                    <flux:badge
                                        :color="$patient->gender === 'male' ? 'blue' : ($patient->gender === 'female' ? 'pink' : 'zinc')"
                                        size="sm" inset="top bottom">
                                        {{ $patient->gender === 'male' ? 'Laki-laki' : ($patient->gender === 'female' ? 'Perempuan' : '?') }}
                                    </flux:badge>
                                @endif
                            </div>
                        </div>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell class="hidden md:table-cell">
                        <span
                            class="font-mono text-xs text-zinc-600 dark:text-primary-dark-400">{{ $patient->nik }}</span>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell class="hidden sm:table-cell">
                        <span
                            class="text-sm text-zinc-600 dark:text-primary-dark-400">{{ $patient->birth_date?->format('d/m/Y') ?? '—' }}</span>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        <span
                            class="font-mono text-xs font-bold text-emerald-700 dark:text-emerald-400 break-all">{{ $patient->id }}</span>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell align="center" action>
                        <x-atoms.button variant="ghost" wire:click="viewDetail('{{ $patient->id }}')" size="sm"
                            icon="eye" title="Lihat detail" />
                        <x-atoms.button variant="ghost"
                            wire:click="confirmDelete('{{ $patient->id }}', '{{ addslashes($patient->name) }}')"
                            size="sm" icon="trash" title="Hapus UUID" />
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @empty
                <tr>
                    <td colspan="5" class="px-5 py-16 text-center">
                        <div class="flex flex-col items-center gap-3">
                            <div
                                class="flex items-center justify-center w-14 h-14 rounded-2xl bg-zinc-100 dark:bg-primary-dark-700">
                                <flux:icon name="users" class="w-7 h-7 text-zinc-300 dark:text-primary-dark-500" />
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-zinc-500 dark:text-primary-dark-400">Belum
                                    ada
                                    pasien terdaftar</p>
                                <p class="mt-0.5 text-xs text-zinc-400 dark:text-primary-dark-500">Klik
                                    "Daftarkan
                                    Pasien" untuk mencari dan mendaftarkan pasien dari SIMRS.</p>
                            </div>
                        </div>
                    </td>
                </tr>
            @endforelse
        </x-organisms.table>

        @if ($patients->hasPages())
            <div
                class="px-5 py-4 border-t border-zinc-100 dark:border-primary-dark-700/60 bg-zinc-50/50 dark:bg-primary-dark-900/20">
                {{ $patients->links() }}
            </div>
        @endif
    </x-organisms.data-panel>

    {{-- Modal Daftarkan Pasien --}}
    <x-organisms.modal wire:model="showRegisterModal" title="Daftarkan Pasien dari SIMRS" maxWidth="2xl">
        <x-slot name="description">
            Cari pasien dari database SIMRS, lalu klik "Generate UUID" untuk mendaftarkan.
        </x-slot>

        <div class="space-y-6">
            <div class="flex gap-2">
                <div class="flex-1">
                    <flux:input wire:model="registerSearch" wire:keydown.enter="searchSimrs" icon="magnifying-glass"
                        placeholder="Cari nama, NIK, atau No. RM..." clearable />
                </div>
                <x-atoms.button wire:click="searchSimrs" wire:target="searchSimrs" variant="primary"
                    icon="magnifying-glass">
                    Cari
                </x-atoms.button>
            </div>

            @if ($simrsError)
                <div
                    class="flex items-center gap-2.5 px-4 py-3 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-[11px] font-medium text-red-700 dark:text-red-300">
                    <flux:icon name="exclamation-circle" class="w-4 h-4 shrink-0 text-red-500" />
                    Koneksi ke database SIMRS gagal. Periksa konfigurasi database.
                </div>
            @elseif (!empty($simrsResults))
                <x-organisms.card-box :padding="false"
                    class="max-h-96 overflow-y-auto w-full border border-zinc-100 dark:border-primary-dark-700/50">
                    <x-organisms.table>
                        <x-slot:headings>
                            <th
                                class="px-4 py-2.5 text-[10px] font-bold text-left uppercase text-zinc-400 dark:text-primary-dark-500">
                                Pasien</th>
                            <th
                                class="px-4 py-2.5 text-[10px] font-bold text-left uppercase text-zinc-400 dark:text-primary-dark-500 hidden sm:table-cell">
                                NIK</th>
                            <th
                                class="px-4 py-2.5 text-[10px] font-bold text-left uppercase text-zinc-400 dark:text-primary-dark-500 hidden md:table-cell">
                                Tgl Lahir</th>
                            <th
                                class="px-4 py-2.5 text-[10px] font-bold text-center uppercase text-zinc-400 dark:text-primary-dark-500 w-32">
                                Aksi</th>
                        </x-slot:headings>
                        @foreach ($simrsResults as $item)
                            <x-molecules.table-row>
                                <x-atoms.table-cell>
                                    <p
                                        class="text-sm font-medium text-zinc-800 dark:text-primary-dark-100 leading-none">
                                        {{ $item['nm_pasien'] }}
                                    </p>
                                    <p class="text-[10px] font-mono text-zinc-400 dark:text-primary-dark-500 mt-1">
                                        {{ $item['no_rkm_medis'] }}
                                    </p>
                                </x-atoms.table-cell>
                                <x-atoms.table-cell class="hidden sm:table-cell">
                                    <span
                                        class="font-mono text-xs text-zinc-500 dark:text-primary-dark-400">{{ $item['no_ktp'] }}</span>
                                </x-atoms.table-cell>
                                <x-atoms.table-cell class="hidden md:table-cell">
                                    <span
                                        class="text-[11px] text-zinc-500 dark:text-primary-dark-400">{{ $item['tgl_lahir'] ?? '—' }}</span>
                                </x-atoms.table-cell>
                                <x-atoms.table-cell align="center">
                                    @if ($item['is_registered'])
                                        <flux:badge color="green" size="sm" inset="top bottom">Terdaftar
                                        </flux:badge>
                                    @else
                                        <x-atoms.button size="sm" variant="primary" icon="plus-circle"
                                            wire:click="registerPatient('{{ $item['no_ktp'] }}', '{{ addslashes($item['nm_pasien']) }}')">
                                            Generate
                                        </x-atoms.button>
                                    @endif
                                </x-atoms.table-cell>
                            </x-molecules.table-row>
                        @endforeach
                    </x-organisms.table>
                </x-organisms.card-box>
            @elseif (blank($registerSearch))
                <div
                    class="flex flex-col items-center gap-3 py-12 text-center text-zinc-400 dark:text-primary-dark-500">
                    <div
                        class="w-16 h-16 rounded-2xl bg-zinc-50 dark:bg-primary-dark-800/50 flex items-center justify-center">
                        <flux:icon name="magnifying-glass" class="w-8 h-8 opacity-40 text-zinc-400" />
                    </div>
                    <p class="text-sm font-medium">Ketik nama, NIK, atau No. RM lalu klik <strong>Cari</strong></p>
                </div>
            @else
                <div
                    class="flex flex-col items-center gap-3 py-12 text-center text-zinc-400 dark:text-primary-dark-500">
                    <div
                        class="w-16 h-16 rounded-2xl bg-zinc-50 dark:bg-primary-dark-800/50 flex items-center justify-center">
                        <flux:icon name="users" class="w-8 h-8 opacity-40 text-zinc-400" />
                    </div>
                    <p class="text-sm font-medium">Tidak ada pasien ditemukan dengan kata kunci tersebut.</p>
                </div>
            @endif
        </div>

        <x-slot name="footer">
            <div class="flex justify-end pt-1">
                <x-atoms.button variant="ghost" wire:click="$set('showRegisterModal', false)">Tutup</x-atoms.button>
            </div>
        </x-slot>
    </x-organisms.modal>

    {{-- Modal Detail --}}
    <x-organisms.modal wire:model="showDetailModal" title="Detail Pasien BPJS" maxWidth="lg">
        @if ($selectedPatient)
            <x-slot name="description">
                <div class="flex items-center gap-2 mt-1.5">
                    <flux:badge color="green" size="sm">Terdaftar</flux:badge>
                    @if ($selectedPatient->gender)
                        <flux:badge
                            :color="$selectedPatient->gender === 'male' ? 'blue' : ($selectedPatient->gender === 'female' ? 'pink' : 'zinc')"
                            size="sm">
                            {{ $selectedPatient->gender === 'male' ? 'Laki-laki' : ($selectedPatient->gender === 'female' ? 'Perempuan' : 'Unknown') }}
                        </flux:badge>
                    @endif
                </div>
            </x-slot>

            <div class="space-y-6">
                <div>
                    <h2 class="text-base font-bold text-zinc-900 dark:text-white leading-tight">
                        {{ $selectedPatient->name }}
                    </h2>
                </div>

                <div class="pt-5 border-t border-zinc-100 dark:border-primary-dark-700/60">
                    <p
                        class="mb-4 text-[10px] font-bold uppercase tracking-widest text-zinc-400 dark:text-primary-dark-500">
                        Patient Identities
                    </p>
                    <dl class="space-y-5">
                        <div>
                            <dt class="text-[10px] uppercase font-bold text-zinc-400 dark:text-primary-dark-500 mb-1">
                                Resource ID (UUID)</dt>
                            <dd
                                class="font-mono text-sm font-bold text-emerald-600 dark:text-emerald-400 break-all leading-relaxed">
                                {{ $selectedPatient->id }}
                            </dd>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <dt
                                    class="text-[10px] uppercase font-bold text-zinc-400 dark:text-primary-dark-500 mb-1">
                                    NIK / KTP</dt>
                                <dd class="font-mono text-sm text-zinc-600 dark:text-primary-dark-300">
                                    {{ $selectedPatient->nik }}
                                </dd>
                            </div>
                            <div>
                                <dt
                                    class="text-[10px] uppercase font-bold text-zinc-400 dark:text-primary-dark-500 mb-1">
                                    Birth Date</dt>
                                <dd class="text-sm text-zinc-600 dark:text-primary-dark-300">
                                    {{ $selectedPatient->birth_date?->format('d M Y') ?? '—' }}
                                </dd>
                            </div>
                        </div>
                        <div>
                            <dt class="text-[10px] uppercase font-bold text-zinc-400 dark:text-primary-dark-500 mb-1">
                                Registered At</dt>
                            <dd class="text-sm text-zinc-600 dark:text-primary-dark-300">
                                {{ $selectedPatient->created_at?->format('d/m/Y H:i') }}
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
    <x-organisms.modal wire:model="showSyncModal" title="Sync Semua Pasien" maxWidth="md">
        <div class="space-y-5">
            <div class="flex items-center gap-4">
                <div
                    class="flex items-center justify-center w-12 h-12 rounded-full bg-primary-100 dark:bg-primary-900/30">
                    <flux:icon name="arrow-path" class="w-6 h-6 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <p class="text-sm text-zinc-500 dark:text-primary-dark-400">
                        {{ number_format($unsyncedCount) }} pasien SIMRS belum memiliki UUID BPJS
                    </p>
                </div>
            </div>
            <div
                class="p-3.5 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-[11px] text-amber-800 dark:text-amber-200 leading-relaxed italic">
                <flux:icon name="exclamation-triangle" class="inline w-3.5 h-3.5 mr-1 text-amber-500" />
                Hanya pasien dengan NIK yang akan disinkronkan. Proses berjalan di background (queue worker).
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
    <x-organisms.modal wire:model="showDeleteModal" title="Hapus UUID Pasien?" maxWidth="md">
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
                <x-atoms.button variant="danger" wire:click="deletePatient" icon="trash">Hapus
                    UUID</x-atoms.button>
            </div>
        </x-slot>
    </x-organisms.modal>
</div>
