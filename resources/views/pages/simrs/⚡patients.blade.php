<?php

use App\Models\Patient;
use App\Models\SatuSehat\SatuSehatPatient;
use App\Jobs\SyncSatuSehatPatientJob;
use App\Jobs\SyncBatchSatuSehatPatientsJob;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('Data Master Pasien')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterGender = '';

    #[Url]
    public string $filterSatuSehat = '';

    #[Url]
    public int $perPage = 25;

    public bool $showDetailModal = false;
    public bool $showSyncModal = false;
    public ?Patient $selectedPatient = null;

    public int $syncLimit = 50;

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedFilterGender()
    {
        $this->resetPage();
    }

    public function updatedFilterSatuSehat()
    {
        $this->resetPage();
    }

    public function showDetail(string $medicalRecordNumber)
    {
        $this->selectedPatient = Patient::with('satuSehatPatient')->find($medicalRecordNumber);
        $this->showDetailModal = true;
    }

    public function closeDetail()
    {
        $this->showDetailModal = false;
        $this->selectedPatient = null;
    }

    public function openSyncModal()
    {
        $this->showSyncModal = true;
    }

    public function closeSyncModal()
    {
        $this->showSyncModal = false;
    }

    public function syncSatuSehatSingle(string $medicalRecordNumber)
    {
        $patient = Patient::find($medicalRecordNumber);

        if (!$patient || !$patient->nik) {
            $this->toastError('NIK pasien tidak tersedia');
            return;
        }

        SyncSatuSehatPatientJob::dispatch($patient->nik, $medicalRecordNumber);
        $this->toastSuccess('Proses sync Satu Sehat dijadwalkan');
    }

    public function syncSatuSehatBatch()
    {
        SyncBatchSatuSehatPatientsJob::dispatch(patientIds: [], syncAll: true, limit: $this->syncLimit);

        $this->toastSuccess("Proses sync batch Satu Sehat dijadwalkan untuk {$this->syncLimit} pasien");
        $this->showSyncModal = false;
    }

    public function with(): array
    {
        $query = Patient::query()
            ->with('satuSehatPatient')
            ->search($this->search)
            ->gender($this->filterGender)
            ->when($this->filterSatuSehat === 'synced', fn($q) => $q->whereHas('satuSehatPatient'))
            ->when($this->filterSatuSehat === 'not_synced', fn($q) => $q->whereDoesntHave('satuSehatPatient'))
            ->latest('synced_at');

        $unsyncedCount = Patient::whereNotNull('nik')->where('nik', '!=', '')->whereDoesntHave('satuSehatPatient')->count();

        return [
            'patients' => $query->paginate($this->perPage),
            'unsyncedCount' => $unsyncedCount,
        ];
    }
};
?>

<div>
    {{-- Header --}}
    <x-ui.page-header title="Data Pasien" subtitle="Data pasien yang tersimpan di aplikasi">
        <x-slot name="actions">
            <x-atoms.button variant="primary" icon="arrow-path" wire:click="openSyncModal">
                Sync ke Satu Sehat
            </x-atoms.button>
        </x-slot>
    </x-ui.page-header>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 gap-4 mb-6 md:grid-cols-5">
        <div class="p-4 bg-white rounded-lg shadow dark:bg-primary-dark-800">
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-primary-100 dark:bg-primary-900">
                    <flux:icon name="users" class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <p class="text-xs text-zinc-500 dark:text-primary-dark-400">Total Pasien</p>
                    <p class="text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">
                        {{ number_format(App\Models\Patient::count()) }}
                    </p>
                </div>
            </div>
        </div>
        <div class="p-4 bg-white rounded-lg shadow dark:bg-primary-dark-800">
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-blue-100 dark:bg-blue-900">
                    <flux:icon name="user" class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <p class="text-xs text-zinc-500 dark:text-primary-dark-400">Laki-laki</p>
                    <p class="text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">
                        {{ number_format(App\Models\Patient::where('gender', 'L')->count()) }}
                    </p>
                </div>
            </div>
        </div>
        <div class="p-4 bg-white rounded-lg shadow dark:bg-primary-dark-800">
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-pink-100 dark:bg-pink-900">
                    <flux:icon name="user" class="w-5 h-5 text-pink-600 dark:text-pink-400" />
                </div>
                <div>
                    <p class="text-xs text-zinc-500 dark:text-primary-dark-400">Perempuan</p>
                    <p class="text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">
                        {{ number_format(App\Models\Patient::where('gender', 'P')->count()) }}
                    </p>
                </div>
            </div>
        </div>
        <div class="p-4 bg-white rounded-lg shadow dark:bg-primary-dark-800">
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-green-100 dark:bg-green-900">
                    <flux:icon name="check-circle" class="w-5 h-5 text-green-600 dark:text-green-400" />
                </div>
                <div>
                    <p class="text-xs text-zinc-500 dark:text-primary-dark-400">Satu Sehat</p>
                    <p class="text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">
                        {{ number_format(App\Models\Patient::whereHas('satuSehatPatient')->count()) }}
                    </p>
                </div>
            </div>
        </div>
        <div class="p-4 bg-white rounded-lg shadow dark:bg-primary-dark-800">
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-amber-100 dark:bg-amber-900">
                    <flux:icon name="clock" class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                </div>
                <div>
                    <p class="text-xs text-zinc-500 dark:text-primary-dark-400">Belum Sync</p>
                    <p class="text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">
                        {{ number_format($unsyncedCount) }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="p-4 mb-6 bg-white rounded-lg shadow dark:bg-primary-dark-800">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari No. RM, nama, NIK, telepon..."
                    icon="magnifying-glass" />
            </div>
            <flux:select wire:model.live="filterGender">
                <flux:select.option value="">Semua Jenis Kelamin</flux:select.option>
                <flux:select.option value="L">Laki-laki</flux:select.option>
                <flux:select.option value="P">Perempuan</flux:select.option>
            </flux:select>
            <flux:select wire:model.live="filterSatuSehat">
                <flux:select.option value="">Semua Status Satu Sehat</flux:select.option>
                <flux:select.option value="synced">Sudah Terdaftar</flux:select.option>
                <flux:select.option value="not_synced">Belum Terdaftar</flux:select.option>
            </flux:select>
            <flux:select wire:model.live="perPage">
                <flux:select.option value="25">25 per halaman</flux:select.option>
                <flux:select.option value="50">50 per halaman</flux:select.option>
                <flux:select.option value="100">100 per halaman</flux:select.option>
            </flux:select>
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-hidden bg-white rounded-lg shadow dark:bg-primary-dark-800">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-primary-dark-700">
                <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                    <tr>
                        <th
                            class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            No. RM
                        </th>
                        <th
                            class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Pasien
                        </th>
                        <th
                            class="hidden px-4 py-3 text-xs font-medium tracking-wider text-left uppercase md:table-cell text-zinc-500 dark:text-primary-dark-400">
                            NIK
                        </th>
                        <th
                            class="hidden px-4 py-3 text-xs font-medium tracking-wider text-left uppercase sm:table-cell text-zinc-500 dark:text-primary-dark-400">
                            JK
                        </th>
                        <th
                            class="hidden px-4 py-3 text-xs font-medium tracking-wider text-left uppercase lg:table-cell text-zinc-500 dark:text-primary-dark-400">
                            Tgl Lahir
                        </th>
                        <th
                            class="px-4 py-3 text-xs font-medium tracking-wider text-center uppercase text-zinc-500 dark:text-primary-dark-400">
                            Satu Sehat
                        </th>
                        <th
                            class="px-4 py-3 text-xs font-medium tracking-wider text-center uppercase text-zinc-500 dark:text-primary-dark-400">
                            Aksi
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-zinc-200 dark:bg-primary-dark-800 dark:divide-primary-dark-700">
                    @forelse ($patients as $patient)
                        <tr :key="$patient->medical_record_number"
                            class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="font-mono text-sm font-medium text-primary-600 dark:text-primary-400">
                                    {{ $patient->medical_record_number }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex-shrink-0 w-9 h-9 rounded-full flex items-center justify-center {{ $patient->gender === 'L' ? 'bg-blue-100 dark:bg-blue-900/50' : 'bg-pink-100 dark:bg-pink-900/50' }}">
                                        <span
                                            class="text-xs font-medium {{ $patient->gender === 'L' ? 'text-blue-700 dark:text-blue-300' : 'text-pink-700 dark:text-pink-300' }}">
                                            {{ strtoupper(substr($patient->name ?? '', 0, 2)) }}
                                        </span>
                                    </div>
                                    <div class="min-w-0">
                                        <p
                                            class="text-sm font-medium truncate text-zinc-900 dark:text-primary-dark-100">
                                            {{ $patient->name }}
                                        </p>
                                        <p class="text-xs text-zinc-500 dark:text-primary-dark-400">
                                            {{ $patient->age_years ?? '-' }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="hidden px-4 py-3 whitespace-nowrap md:table-cell">
                                <span class="font-mono text-sm text-zinc-600 dark:text-primary-dark-400">
                                    {{ $patient->nik ?? '-' }}
                                </span>
                            </td>
                            <td class="hidden px-4 py-3 whitespace-nowrap sm:table-cell">
                                <flux:badge :color="$patient->gender === 'L' ? 'blue' : 'pink'" size="sm">
                                    {{ $patient->gender === 'L' ? 'L' : 'P' }}
                                </flux:badge>
                            </td>
                            <td
                                class="hidden px-4 py-3 text-sm whitespace-nowrap lg:table-cell text-zinc-600 dark:text-primary-dark-400">
                                {{ $patient->birth_date?->format('d/m/Y') ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                @if ($patient->satuSehatPatient)
                                    <div class="flex items-center justify-center"
                                        title="IHS: {{ $patient->satuSehatPatient->ihs_number }}">
                                        <span
                                            class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-green-100 dark:bg-green-900/50">
                                            <flux:icon name="check"
                                                class="w-4 h-4 text-green-600 dark:text-green-400" />
                                        </span>
                                    </div>
                                @elseif ($patient->nik)
                                    <x-atoms.button variant="ghost" size="sm"
                                        wire:click="syncSatuSehatSingle('{{ $patient->medical_record_number }}')"
                                        wire:loading.attr="disabled"
                                        wire:target="syncSatuSehatSingle('{{ $patient->medical_record_number }}')"
                                        title="Sync Satu Sehat"
                                        class="text-orange-600 hover:text-orange-700 dark:text-orange-400">
                                        <span wire:loading.remove
                                            wire:target="syncSatuSehatSingle('{{ $patient->medical_record_number }}')">
                                            <flux:icon name="arrow-path" class="w-4 h-4" />
                                        </span>
                                        <span wire:loading
                                            wire:target="syncSatuSehatSingle('{{ $patient->medical_record_number }}')">
                                            <flux:icon name="arrow-path" class="w-4 h-4 animate-spin" />
                                        </span>
                                    </x-atoms.button>
                                @else
                                    <span class="text-xs text-zinc-400" title="NIK tidak tersedia">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <x-atoms.button variant="ghost" size="sm" icon="eye"
                                    wire:click="showDetail('{{ $patient->medical_record_number }}')"
                                    title="Lihat Detail" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <flux:icon name="users"
                                        class="w-12 h-12 text-zinc-300 dark:text-primary-dark-600" />
                                    <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">
                                        Tidak ada data pasien
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($patients->hasPages())
            <div class="px-4 py-4 border-t border-zinc-200 dark:border-primary-dark-700">
                {{ $patients->links() }}
            </div>
        @endif
    </div>

    {{-- Detail Modal --}}
    <x-organisms.modal wire:model="showDetailModal" maxWidth="4xl" title="">
        @if ($selectedPatient)
            <div class="space-y-6">
                {{-- Header --}}
                <div class="flex items-start gap-4 pb-4 border-b border-zinc-200 dark:border-primary-dark-700">
                    <div
                        class="flex items-center justify-center flex-shrink-0 w-16 h-16 rounded-xl {{ $selectedPatient->gender === 'L' ? 'bg-blue-100 dark:bg-blue-900/50' : 'bg-pink-100 dark:bg-pink-900/50' }}">
                        <span
                            class="text-xl font-bold {{ $selectedPatient->gender === 'L' ? 'text-blue-700 dark:text-blue-300' : 'text-pink-700 dark:text-pink-300' }}">
                            {{ strtoupper(substr($selectedPatient->name ?? '', 0, 2)) }}
                        </span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h2 class="text-xl font-bold text-zinc-900 dark:text-primary-dark-100">
                            {{ $selectedPatient->name }}
                        </h2>
                        <div class="flex flex-wrap items-center gap-2 mt-2">
                            <flux:badge color="zinc" size="sm">
                                {{ $selectedPatient->medical_record_number }}
                            </flux:badge>
                            <flux:badge :color="$selectedPatient->gender === 'L' ? 'blue' : 'pink'"
                                size="sm">
                                {{ $selectedPatient->gender_label }}
                            </flux:badge>
                            @if ($selectedPatient->satuSehatPatient)
                                <flux:badge color="green" size="sm">
                                    <flux:icon name="check-circle" class="w-3 h-3 mr-1" />
                                    Satu Sehat
                                </flux:badge>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Content --}}
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div class="p-4 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                        <h3
                            class="flex items-center gap-2 mb-4 text-sm font-semibold text-zinc-900 dark:text-primary-dark-100">
                            <flux:icon name="user" class="w-4 h-4" />
                            Data Pribadi
                        </h3>
                        <dl class="space-y-2.5 text-sm">
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">NIK</dt>
                                <dd class="font-mono text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedPatient->nik ?? '-' }}</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">Tempat Lahir
                                </dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedPatient->birth_place ?? '-' }}</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">Tanggal Lahir
                                </dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedPatient->birth_date?->format('d F Y') ?? '-' }}</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">Agama</dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedPatient->religion ?? '-' }}
                                </dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">Status Nikah
                                </dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedPatient->marital_status_label }}</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">Pendidikan</dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedPatient->education ?? '-' }}
                                </dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">Pekerjaan</dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedPatient->occupation ?? '-' }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div class="p-4 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                        <h3
                            class="flex items-center gap-2 mb-4 text-sm font-semibold text-zinc-900 dark:text-primary-dark-100">
                            <flux:icon name="map-pin" class="w-4 h-4" />
                            Kontak & Alamat
                        </h3>
                        <dl class="space-y-2.5 text-sm">
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">Telepon</dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedPatient->phone ?? '-' }}</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">Email</dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedPatient->email ?? '-' }}</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">Alamat</dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedPatient->address ?? '-' }}
                                </dd>
                            </div>
                        </dl>

                        @if ($selectedPatient->satuSehatPatient)
                            <h3
                                class="flex items-center gap-2 mt-6 mb-4 text-sm font-semibold text-zinc-900 dark:text-primary-dark-100">
                                <flux:icon name="globe-alt" class="w-4 h-4" />
                                Satu Sehat
                            </h3>
                            <dl class="space-y-2.5 text-sm">
                                <div class="flex gap-2">
                                    <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">IHS Number
                                    </dt>
                                    <dd class="font-mono text-zinc-900 dark:text-primary-dark-100">
                                        {{ $selectedPatient->satuSehatPatient->ihs_number }}</dd>
                                </div>
                                <div class="flex gap-2">
                                    <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">Synced At
                                    </dt>
                                    <dd class="text-zinc-900 dark:text-primary-dark-100">
                                        {{ $selectedPatient->satuSehatPatient->synced_at?->format('d M Y H:i') ?? '-' }}
                                    </dd>
                                </div>
                            </dl>
                        @endif
                    </div>
                </div>

                <div
                    class="flex items-center justify-between pt-4 text-xs border-t border-zinc-200 dark:border-primary-dark-700 text-zinc-500 dark:text-primary-dark-400">
                    <span>Terakhir sync SIMRS: {{ $selectedPatient->synced_at?->format('d M Y H:i') ?? '-' }}</span>
                </div>

                <div class="flex justify-end gap-3">
                    <x-atoms.button variant="ghost" wire:click="closeDetail">
                        Tutup
                    </x-atoms.button>
                    @if (!$selectedPatient->satuSehatPatient && $selectedPatient->nik)
                        <x-atoms.button variant="primary" icon="arrow-path"
                            wire:click="syncSatuSehatSingle('{{ $selectedPatient->medical_record_number }}')">
                            Sync Satu Sehat
                        </x-atoms.button>
                    @endif
                </div>
            </div>
        @endif
    
    </x-organisms.modal>

    {{-- Sync Modal --}}
    <x-organisms.modal wire:model="showSyncModal" maxWidth="md" title="">
        <div class="space-y-6">
            <div class="flex items-center gap-4">
                <div
                    class="flex items-center justify-center w-12 h-12 rounded-full bg-primary-100 dark:bg-primary-900/30">
                    <flux:icon name="arrow-path" class="w-6 h-6 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <flux:heading size="lg">Sync ke Satu Sehat</flux:heading>
                    <p class="text-sm text-zinc-500 dark:text-primary-dark-400">
                        {{ number_format($unsyncedCount) }} pasien belum tersinkronisasi
                    </p>
                </div>
            </div>

            <div class="p-4 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
                <p class="text-sm text-amber-800 dark:text-amber-200">
                    <flux:icon name="exclamation-triangle" class="inline w-4 h-4 mr-1" />
                    Proses sync akan berjalan di background. Pastikan queue worker sudah berjalan.
                </p>
            </div>

            <flux:field>
                <flux:label>Jumlah Pasien</flux:label>
                <flux:select wire:model="syncLimit">
                    <flux:select.option value="10">10 pasien</flux:select.option>
                    <flux:select.option value="25">25 pasien</flux:select.option>
                    <flux:select.option value="50">50 pasien</flux:select.option>
                    <flux:select.option value="100">100 pasien</flux:select.option>
                </flux:select>
            </flux:field>

            
        <x-slot:footer>
            <div class="flex justify-end gap-3 pt-4">
                <x-atoms.button variant="ghost" wire:click="closeSyncModal">
                    Batal
                </x-atoms.button>
                <x-atoms.button variant="primary" icon="arrow-path" wire:click="syncSatuSehatBatch">
                    Mulai Sync
                </x-atoms.button>
            </div>
        </x-slot:footer>
    </div>
    </x-organisms.modal>
</div>
