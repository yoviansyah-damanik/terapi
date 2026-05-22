<?php

use App\Models\Pegawai;
use App\Jobs\SyncSatuSehatPractitionerJob;
use App\Jobs\SyncBatchSatuSehatPractitionersJob;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('Data Pegawai')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterGender = '';

    #[Url]
    public string $filterStatus = '';

    #[Url]
    public int $perPage = 15;

    public bool $showDetailModal = false;
    public bool $showSyncModal = false;
    public ?Pegawai $selectedEmployee = null;

    public int $syncLimit = 50;

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedFilterGender()
    {
        $this->resetPage();
    }

    public function updatedFilterStatus()
    {
        $this->resetPage();
    }

    public function updatedPerPage()
    {
        $this->resetPage();
    }

    public function showDetail(string $id)
    {
        $this->selectedEmployee = Pegawai::with('satuSehatPractitioner')->find($id);
        $this->showDetailModal = true;
    }

    public function closeDetail()
    {
        $this->showDetailModal = false;
        $this->selectedEmployee = null;
    }

    public function openSyncModal()
    {
        $this->showSyncModal = true;
    }

    public function closeSyncModal()
    {
        $this->showSyncModal = false;
    }

    public function syncToSatuSehat(string $employeeId)
    {
        $employee = Pegawai::find($employeeId);

        if (!$employee) {
            $this->toastError('Pegawai tidak ditemukan');
            return;
        }

        if (!$employee->id_card_number) {
            $this->toastError('Pegawai tidak memiliki No. KTP');
            return;
        }

        SyncSatuSehatPractitionerJob::dispatch($employee->id_card_number, $employee->employee_id);
        $this->toastSuccess('Proses sync ke Satu Sehat dijadwalkan');
    }

    public function syncSatuSehatBatch()
    {
        SyncBatchSatuSehatPractitionersJob::dispatch(employeeIds: [], syncAll: true, limit: $this->syncLimit);

        $this->toastSuccess("Proses sync batch Satu Sehat dijadwalkan untuk {$this->syncLimit} pegawai");
        $this->showSyncModal = false;
    }

    public function with(): array
    {
        $query = Pegawai::query()->with('satuSehatPractitioner')->search($this->search)->gender($this->filterGender)->activeStatus($this->filterStatus)->orderBy('name');

        $unsyncedCount = Pegawai::whereNotNull('id_card_number')->where('id_card_number', '!=', '')->where('active_status', 'AKTIF')->whereDoesntHave('satuSehatPractitioner')->count();

        return [
            'employees' => $query->paginate($this->perPage),
            'unsyncedCount' => $unsyncedCount,
        ];
    }
};
?>

<div>
    {{-- Header --}}
    <x-ui.page-header title="Data Pegawai" subtitle="Data pegawai yang tersimpan di aplikasi">
        <x-slot name="actions">
            <x-atoms.button variant="primary" icon="arrow-path" wire:click="openSyncModal">
                Sync ke Satu Sehat
            </x-atoms.button>
        </x-slot>
    </x-ui.page-header>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 gap-4 mb-6 md:grid-cols-6">
        <div class="p-4 bg-white rounded-lg shadow dark:bg-primary-dark-800">
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-primary-100 dark:bg-primary-900">
                    <flux:icon name="users" class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <p class="text-xs text-zinc-500 dark:text-primary-dark-400">Total Pegawai</p>
                    <p class="text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">
                        {{ number_format(App\Models\Pegawai::count()) }}
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
                        {{ number_format(App\Models\Pegawai::where('gender', 'L')->count()) }}
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
                        {{ number_format(App\Models\Pegawai::where('gender', 'P')->count()) }}
                    </p>
                </div>
            </div>
        </div>
        <div class="p-4 bg-white rounded-lg shadow dark:bg-primary-dark-800">
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-green-100 dark:bg-green-900">
                    <flux:icon name="check-badge" class="w-5 h-5 text-green-600 dark:text-green-400" />
                </div>
                <div>
                    <p class="text-xs text-zinc-500 dark:text-primary-dark-400">Aktif</p>
                    <p class="text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">
                        {{ number_format(App\Models\Pegawai::where('active_status', 'AKTIF')->count()) }}
                    </p>
                </div>
            </div>
        </div>
        <div class="p-4 bg-white rounded-lg shadow dark:bg-primary-dark-800">
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-emerald-100 dark:bg-emerald-900">
                    <flux:icon name="check-circle" class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                </div>
                <div>
                    <p class="text-xs text-zinc-500 dark:text-primary-dark-400">Satu Sehat</p>
                    <p class="text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">
                        {{ number_format(App\Models\Pegawai::whereHas('satuSehatPractitioner')->count()) }}
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
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari ID, NIK, nama, jabatan..."
                    icon="magnifying-glass" />
            </div>
            <flux:select wire:model.live="filterGender">
                <flux:select.option value="">Semua Jenis Kelamin</flux:select.option>
                <flux:select.option value="L">Laki-laki</flux:select.option>
                <flux:select.option value="P">Perempuan</flux:select.option>
            </flux:select>
            <flux:select wire:model.live="filterStatus">
                <flux:select.option value="">Semua Status</flux:select.option>
                <flux:select.option value="AKTIF">Aktif</flux:select.option>
                <flux:select.option value="CUTI">Cuti</flux:select.option>
                <flux:select.option value="KELUAR">Keluar</flux:select.option>
            </flux:select>
            <flux:select wire:model.live="perPage">
                <flux:select.option value="15">15 per halaman</flux:select.option>
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
                            ID
                        </th>
                        <th
                            class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Pegawai
                        </th>
                        <th
                            class="hidden px-4 py-3 text-xs font-medium tracking-wider text-left uppercase md:table-cell text-zinc-500 dark:text-primary-dark-400">
                            No. KTP
                        </th>
                        <th
                            class="hidden px-4 py-3 text-xs font-medium tracking-wider text-left uppercase lg:table-cell text-zinc-500 dark:text-primary-dark-400">
                            Jabatan
                        </th>
                        <th
                            class="hidden px-4 py-3 text-xs font-medium tracking-wider text-left uppercase sm:table-cell text-zinc-500 dark:text-primary-dark-400">
                            JK
                        </th>
                        <th
                            class="hidden px-4 py-3 text-xs font-medium tracking-wider text-left uppercase lg:table-cell text-zinc-500 dark:text-primary-dark-400">
                            Departemen
                        </th>
                        <th
                            class="hidden px-4 py-3 text-xs font-medium tracking-wider text-center uppercase xl:table-cell text-zinc-500 dark:text-primary-dark-400">
                            Status
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
                    @forelse ($employees as $employee)
                        <tr :key="$employee->employee_id" class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="font-mono text-sm font-medium text-primary-600 dark:text-primary-400">
                                    {{ $employee->employee_id }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex-shrink-0 w-9 h-9 rounded-full flex items-center justify-center {{ $employee->gender === 'L' ? 'bg-blue-100 dark:bg-blue-900/50' : 'bg-pink-100 dark:bg-pink-900/50' }}">
                                        <span
                                            class="text-xs font-medium {{ $employee->gender === 'L' ? 'text-blue-700 dark:text-blue-300' : 'text-pink-700 dark:text-pink-300' }}">
                                            {{ strtoupper(substr($employee->name, 0, 2)) }}
                                        </span>
                                    </div>
                                    <div class="min-w-0">
                                        <p
                                            class="text-sm font-medium truncate text-zinc-900 dark:text-primary-dark-100">
                                            {{ $employee->name }}
                                        </p>
                                        <p class="text-xs text-zinc-500 dark:text-primary-dark-400">
                                            {{ $employee->nik ?: '-' }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="hidden px-4 py-3 whitespace-nowrap md:table-cell">
                                <span class="font-mono text-sm text-zinc-600 dark:text-primary-dark-400">
                                    {{ $employee->id_card_number ?: '-' }}
                                </span>
                            </td>
                            <td class="hidden px-4 py-3 lg:table-cell">
                                <p class="text-sm truncate text-zinc-600 dark:text-primary-dark-400 max-w-48">
                                    {{ $employee->position_name ?? ($employee->position ?? '-') }}
                                </p>
                            </td>
                            <td class="hidden px-4 py-3 whitespace-nowrap sm:table-cell">
                                <flux:badge :color="$employee->gender === 'L' ? 'blue' : 'pink'" size="sm">
                                    {{ $employee->gender === 'L' ? 'L' : 'P' }}
                                </flux:badge>
                            </td>
                            <td class="hidden px-4 py-3 whitespace-nowrap lg:table-cell">
                                <span class="text-sm text-zinc-600 dark:text-primary-dark-400">
                                    {{ $employee->department_name ?? ($employee->department ?? '-') }}
                                </span>
                            </td>
                            <td class="hidden px-4 py-3 text-center whitespace-nowrap xl:table-cell">
                                @if ($employee->active_status === 'AKTIF')
                                    <flux:badge color="green" size="sm">Aktif</flux:badge>
                                @elseif ($employee->active_status === 'CUTI')
                                    <flux:badge color="amber" size="sm">Cuti</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm">{{ $employee->active_status ?: '-' }}
                                    </flux:badge>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                @if ($employee->satuSehatPractitioner)
                                    <x-atoms.button variant="ghost" size="sm"
                                        wire:click="syncToSatuSehat('{{ $employee->employee_id }}')"
                                        title="Resync ke Satu Sehat - IHS: {{ $employee->satuSehatPractitioner->ihs_number }}"
                                        class="text-green-600 hover:text-green-700 dark:text-green-400">
                                        <flux:icon name="check-circle" class="w-4 h-4" />
                                    </x-atoms.button>
                                @elseif ($employee->id_card_number)
                                    <x-atoms.button variant="ghost" size="sm"
                                        wire:click="syncToSatuSehat('{{ $employee->employee_id }}')"
                                        title="Sync ke Satu Sehat"
                                        class="text-orange-600 hover:text-orange-700 dark:text-orange-400">
                                        <flux:icon name="arrow-path" class="w-4 h-4" />
                                    </x-atoms.button>
                                @else
                                    <span class="text-xs text-zinc-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <x-atoms.button variant="ghost" size="sm" icon="eye"
                                    wire:click="showDetail('{{ $employee->employee_id }}')" title="Lihat Detail" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <flux:icon name="users"
                                        class="w-12 h-12 text-zinc-300 dark:text-primary-dark-600" />
                                    <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">
                                        Tidak ada data pegawai ditemukan
                                    </p>
                                    <p class="mt-1 text-xs text-zinc-400 dark:text-primary-dark-500">
                                        Sync data dari SIMRS untuk menambahkan pegawai
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($employees->hasPages())
            <div class="px-4 py-4 border-t border-zinc-200 dark:border-primary-dark-700">
                {{ $employees->links() }}
            </div>
        @endif
    </div>

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
                        {{ number_format($unsyncedCount) }} pegawai belum tersinkronisasi
                    </p>
                </div>
            </div>

            <div class="p-4 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
                <p class="text-sm text-amber-800 dark:text-amber-200">
                    <flux:icon name="exclamation-triangle" class="inline w-4 h-4 mr-1" />
                    Proses sync akan berjalan di background menggunakan No. KTP pegawai. Pastikan queue worker sudah
                    berjalan.
                </p>
            </div>

            <flux:field>
                <flux:label>Jumlah Pegawai</flux:label>
                <flux:select wire:model="syncLimit">
                    <flux:select.option value="10">10 pegawai</flux:select.option>
                    <flux:select.option value="25">25 pegawai</flux:select.option>
                    <flux:select.option value="50">50 pegawai</flux:select.option>
                    <flux:select.option value="100">100 pegawai</flux:select.option>
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

    {{-- Detail Modal --}}
    <x-organisms.modal wire:model="showDetailModal" maxWidth="4xl" title="">
        @if ($selectedEmployee)
            <div class="space-y-6">
                {{-- Header --}}
                <div class="flex items-start gap-4 pb-4 border-b border-zinc-200 dark:border-primary-dark-700">
                    <div
                        class="flex items-center justify-center flex-shrink-0 w-16 h-16 rounded-xl {{ $selectedEmployee->gender === 'L' ? 'bg-blue-100 dark:bg-blue-900/50' : 'bg-pink-100 dark:bg-pink-900/50' }}">
                        <span
                            class="text-xl font-bold {{ $selectedEmployee->gender === 'L' ? 'text-blue-700 dark:text-blue-300' : 'text-pink-700 dark:text-pink-300' }}">
                            {{ strtoupper(substr($selectedEmployee->name, 0, 2)) }}
                        </span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h2 class="text-xl font-bold text-zinc-900 dark:text-primary-dark-100">
                            {{ $selectedEmployee->name }}
                        </h2>
                        <div class="flex flex-wrap items-center gap-2 mt-2">
                            <flux:badge color="zinc" size="sm">
                                <flux:icon name="identification" class="w-3 h-3 mr-1" />
                                {{ $selectedEmployee->employee_id }}
                            </flux:badge>
                            <flux:badge :color="$selectedEmployee->gender === 'L' ? 'blue' : 'pink'"
                                size="sm">
                                {{ $selectedEmployee->gender_label }}
                            </flux:badge>
                            @if ($selectedEmployee->active_status === 'AKTIF')
                                <flux:badge color="green" size="sm">Aktif</flux:badge>
                            @elseif ($selectedEmployee->active_status === 'CUTI')
                                <flux:badge color="amber" size="sm">Cuti</flux:badge>
                            @elseif ($selectedEmployee->active_status)
                                <flux:badge color="zinc" size="sm">{{ $selectedEmployee->active_status }}
                                </flux:badge>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Content --}}
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {{-- Data Pribadi --}}
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
                                    {{ $selectedEmployee->nik ?: '-' }}</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">No. KTP</dt>
                                <dd class="font-mono text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedEmployee->id_card_number ?: '-' }}</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">Tempat Lahir
                                </dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedEmployee->birth_place ?: '-' }}</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">Tanggal Lahir
                                </dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedEmployee->birth_date?->format('d F Y') ?? '-' }}
                                    @if ($selectedEmployee->age_years)
                                        <span class="text-zinc-500">({{ $selectedEmployee->age_years }})</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">Pendidikan</dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedEmployee->education ?: '-' }}
                                </dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">Alamat</dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedEmployee->address ?: '-' }}
                                </dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">Kota</dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedEmployee->city ?: '-' }}</dd>
                            </div>
                        </dl>
                    </div>

                    {{-- Data Kepegawaian --}}
                    <div class="p-4 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                        <h3
                            class="flex items-center gap-2 mb-4 text-sm font-semibold text-zinc-900 dark:text-primary-dark-100">
                            <flux:icon name="briefcase" class="w-4 h-4" />
                            Data Kepegawaian
                        </h3>
                        <dl class="space-y-2.5 text-sm">
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">Jabatan</dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedEmployee->position_name ?? ($selectedEmployee->position ?? '-') }}
                                    @if ($selectedEmployee->position_code && $selectedEmployee->position_name)
                                        <span class="text-zinc-500">({{ $selectedEmployee->position_code }})</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">Kelompok</dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedEmployee->group_name ?? ($selectedEmployee->group_code ?? '-') }}
                                    @if ($selectedEmployee->group_index)
                                        <span class="text-zinc-500">(Indeks:
                                            {{ $selectedEmployee->group_index }})</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">Departemen</dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedEmployee->department_name ?? ($selectedEmployee->department ?? '-') }}
                                </dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">Bidang</dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedEmployee->division_name ?? ($selectedEmployee->division ?? '-') }}</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">Status Kerja
                                </dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedEmployee->employment_status_category ?? ($selectedEmployee->employment_status ?? '-') }}
                                </dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">Mulai Kerja
                                </dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedEmployee->start_work_date?->format('d F Y') ?? '-' }}
                                    @if ($selectedEmployee->masa_kerja)
                                        <span class="text-zinc-500">({{ $selectedEmployee->masa_kerja }})</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">Wajib Masuk
                                </dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedEmployee->required_attendance ?? '-' }} hari</dd>
                            </div>
                        </dl>
                    </div>

                    {{-- Data Keuangan --}}
                    <div class="p-4 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                        <h3
                            class="flex items-center gap-2 mb-4 text-sm font-semibold text-zinc-900 dark:text-primary-dark-100">
                            <flux:icon name="banknotes" class="w-4 h-4" />
                            Data Keuangan
                        </h3>
                        <dl class="space-y-2.5 text-sm">
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">Gaji Pokok</dt>
                                <dd class="font-mono text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedEmployee->base_salary ? 'Rp ' . number_format($selectedEmployee->base_salary, 0, ',', '.') : '-' }}
                                </dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">NPWP</dt>
                                <dd class="font-mono text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedEmployee->npwp ?: '-' }}</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">Bank</dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedEmployee->bank ?: '-' }}</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">No. Rekening
                                </dt>
                                <dd class="font-mono text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedEmployee->account_number ?: '-' }}</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">Status WP</dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedEmployee->tax_status ?: '-' }}</dd>
                            </div>
                        </dl>
                    </div>

                    {{-- Data Tambahan --}}
                    <div class="p-4 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                        <h3
                            class="flex items-center gap-2 mb-4 text-sm font-semibold text-zinc-900 dark:text-primary-dark-100">
                            <flux:icon name="document-text" class="w-4 h-4" />
                            Data Tambahan
                        </h3>
                        <dl class="space-y-2.5 text-sm">
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">Resiko Kerja
                                </dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedEmployee->risk_name ?? ($selectedEmployee->risk_code ?? '-') }}
                                    @if ($selectedEmployee->risk_index)
                                        <span class="text-zinc-500">(Indeks:
                                            {{ $selectedEmployee->risk_index }})</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">Emergency</dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedEmployee->emergency_name ?? ($selectedEmployee->emergency_code ?? '-') }}
                                    @if ($selectedEmployee->emergency_index)
                                        <span class="text-zinc-500">(Indeks:
                                            {{ $selectedEmployee->emergency_index }})</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">Index Insentif
                                </dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedEmployee->insurance_percent ? $selectedEmployee->insurance_percent . '%' : $selectedEmployee->insurance_index ?? '-' }}
                                </dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">Indek</dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedEmployee->index ?: '-' }}
                                </dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">Cuti Diambil
                                </dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedEmployee->leave_taken ?? '0' }} hari</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">Dana Kesehatan
                                </dt>
                                <dd class="font-mono text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedEmployee->health_fund ? 'Rp ' . number_format($selectedEmployee->health_fund, 0, ',', '.') : '-' }}
                                </dd>
                            </div>
                            @if ($selectedEmployee->contract_start_date)
                                <div class="flex gap-2">
                                    <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">Mulai
                                        Kontrak</dt>
                                    <dd class="text-zinc-900 dark:text-primary-dark-100">
                                        {{ $selectedEmployee->contract_start_date->format('d F Y') }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                </div>

                {{-- Footer Info --}}
                <div
                    class="flex items-center justify-between pt-4 text-xs border-t border-zinc-200 dark:border-primary-dark-700 text-zinc-500 dark:text-primary-dark-400">
                    <span>
                        <flux:icon name="arrow-path" class="inline w-3 h-3 mr-1" />
                        Terakhir sync: {{ $selectedEmployee->synced_at?->format('d F Y H:i') ?? '-' }}
                    </span>
                </div>

                {{-- Actions --}}
                <div class="flex justify-end gap-3">
                    <x-atoms.button variant="ghost" wire:click="closeDetail">
                        Tutup
                    </x-atoms.button>
                </div>
            </div>
        @endif
    
    </x-organisms.modal>
</div>
