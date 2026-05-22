<?php

use App\Models\SatuSehat\SatuSehatPractitioner;
use App\Models\Simrs\Pegawai;
use App\Models\Configuration;
use App\Jobs\SyncSatuSehatPractitionerJob;
use Illuminate\Support\Facades\Bus;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('Satu Sehat - Practitioner')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterGender = '';

    #[Url]
    public int $perPage = 25;

    public bool $showDetailModal = false;
    public ?SatuSehatPractitioner $selectedPractitioner = null;
    public ?array $simrsData = null;

    // Modal sync SIMRS
    public bool $showSyncModal = false;

    // Modal pilih petugas
    public bool $showPickModal = false;
    public string $pickSearch = '';
    public string $pickDepartment = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterGender(): void
    {
        $this->resetPage();
    }

    public function updatedPickSearch(): void
    {
        // dipanggil reactive
    }

    public function updatedPickDepartment(): void
    {
        // dipanggil reactive
    }

    public function showDetail(string $id): void
    {
        $this->selectedPractitioner = SatuSehatPractitioner::find($id);
        $this->showDetailModal = true;
        $this->simrsData = null;
        if ($this->selectedPractitioner?->nik) {
            try {
                $emp = Pegawai::where('no_ktp', $this->selectedPractitioner->nik)->first();
                if ($emp) {
                    $this->simrsData = [
                        'id' => $emp->id,
                        'nama' => $emp->nama,
                        'jk' => $emp->jk,
                        'jbtn' => $emp->jbtn,
                        'bidang' => $emp->bidang,
                        'departemen' => $emp->departemen,
                        'stts_aktif' => $emp->stts_aktif,
                        'mulai_kerja' => $emp->mulai_kerja?->format('d M Y'),
                    ];
                }
            } catch (\Exception) {
                // SIMRS tidak terjangkau
            }
        }
    }

    public function closeDetail(): void
    {
        $this->showDetailModal = false;
        $this->selectedPractitioner = null;
        $this->simrsData = null;
    }

    public function refreshPractitioner(string $id): void
    {
        $practitioner = SatuSehatPractitioner::find($id);
        if ($practitioner && $practitioner->nik) {
            SyncSatuSehatPractitionerJob::dispatch($practitioner->nik);
            $this->toastSuccess('Proses refresh dijadwalkan');
        }
    }

    /** Sync semua pegawai aktif SIMRS yang belum ada di Satu Sehat, dipecah menjadi beberapa batch */
    public function syncSimrs(): void
    {
        try {
            $syncedNiks = SatuSehatPractitioner::pluck('nik')->toArray();

            $employees = Pegawai::where('stts_aktif', 'AKTIF')
                ->whereNotNull('no_ktp')
                ->where('no_ktp', '!=', '')
                ->when(!empty($syncedNiks), fn($q) => $q->whereNotIn('no_ktp', $syncedNiks))
                ->get();

            if ($employees->isEmpty()) {
                $this->toastSuccess('Semua pegawai sudah tersinkronisasi');
                $this->showSyncModal = false;
                return;
            }

            $batchSize = (int) (Configuration::where('key', 'satusehat.sync_batch_size')->value('value') ?: 1000);
            $chunks = $employees->chunk($batchSize);
            $total = $chunks->count();

            foreach ($chunks as $index => $chunk) {
                $jobs = $chunk->map(fn($emp) => new SyncSatuSehatPractitionerJob($emp->no_ktp, $emp->id))->all();
                Bus::batch($jobs)
                    ->name('Practitioner Sync #' . ($index + 1) . '/' . $total . ' - ' . now()->format('d/m/Y H:i'))
                    ->dispatch();
            }

            $this->toastSuccess("Sync {$employees->count()} pegawai dipecah menjadi {$total} batch");
            $this->showSyncModal = false;
        } catch (\Exception $e) {
            $this->toastError('Gagal memulai sync: ' . $e->getMessage());
        }
    }

    /** Sync petugas individual yang dipilih dari modal picker */
    public function selectAndSync(string $noKtp, string $employeeId): void
    {
        SyncSatuSehatPractitionerJob::dispatch($noKtp, $employeeId);
        $this->toastSuccess("Sync petugas dijadwalkan (NIK: {$noKtp})");
        $this->showPickModal = false;
        $this->pickSearch = '';
        $this->pickDepartment = '';
    }

    public function with(): array
    {
        $practitioners = SatuSehatPractitioner::query()
            ->when(
                $this->search,
                fn($q) => $q->where(
                    fn($q2) => $q2
                        ->where('ihs_number', 'like', "%{$this->search}%")
                        ->orWhere('nik', 'like', "%{$this->search}%")
                        ->orWhere('name', 'like', "%{$this->search}%"),
                ),
            )
            ->when($this->filterGender, fn($q) => $q->where('gender', $this->filterGender))
            ->latest('synced_at')
            ->paginate($this->perPage);

        $unsyncedSimrsCount = 0;
        try {
            $syncedNiks = SatuSehatPractitioner::pluck('nik')->toArray();

            $unsyncedSimrsCount = Pegawai::where('stts_aktif', 'AKTIF')
                ->whereNotNull('no_ktp')
                ->where('no_ktp', '!=', '')
                ->when(!empty($syncedNiks), fn($q) => $q->whereNotIn('no_ktp', $syncedNiks))
                ->count();
        } catch (\Exception) {
            // SIMRS tidak terjangkau
        }

        return [
            'practitioners' => $practitioners,
            'unsyncedSimrsCount' => $unsyncedSimrsCount,
        ];
    }
};
?>

<div>
    {{-- Header --}}
    <x-ui.page-header title="Satu Sehat - Practitioner"
        subtitle="Data practitioner yang telah terintegrasi dengan Satu Sehat">
        <x-slot name="actions">
            <x-atoms.button variant="ghost" icon="user-plus" wire:click="$set('showPickModal', true)">
                Pilih Petugas
            </x-atoms.button>
            <x-atoms.button variant="primary" icon="arrow-path" wire:click="$set('showSyncModal', true)">
                Sync SIMRS
            </x-atoms.button>
        </x-slot>
    </x-ui.page-header>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 gap-4 mb-6 md:grid-cols-4">
        <div class="p-4 bg-white rounded-lg shadow dark:bg-primary-dark-800">
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-green-100 dark:bg-green-900">
                    <flux:icon name="check-circle" class="w-5 h-5 text-green-600 dark:text-green-400" />
                </div>
                <div>
                    <p class="text-xs text-zinc-500 dark:text-primary-dark-400">Terintegrasi</p>
                    <p class="text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">
                        {{ number_format(SatuSehatPractitioner::count()) }}
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
                        {{ number_format($unsyncedSimrsCount) }}
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
                        {{ number_format(SatuSehatPractitioner::where('gender', 'male')->count()) }}
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
                        {{ number_format(SatuSehatPractitioner::where('gender', 'female')->count()) }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="p-4 mb-6 bg-white rounded-lg shadow dark:bg-primary-dark-800">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari IHS Number, NIK, atau nama..."
                    icon="magnifying-glass" />
            </div>
            <flux:select wire:model.live="filterGender">
                <flux:select.option value="">Semua Jenis Kelamin</flux:select.option>
                <flux:select.option value="male">Laki-laki</flux:select.option>
                <flux:select.option value="female">Perempuan</flux:select.option>
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
                            IHS Number</th>
                        <th
                            class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Practitioner</th>
                        <th
                            class="hidden px-4 py-3 text-xs font-medium tracking-wider text-left uppercase md:table-cell text-zinc-500 dark:text-primary-dark-400">
                            NIK</th>
                        <th
                            class="hidden px-4 py-3 text-xs font-medium tracking-wider text-left uppercase sm:table-cell text-zinc-500 dark:text-primary-dark-400">
                            JK</th>
                        <th
                            class="hidden px-4 py-3 text-xs font-medium tracking-wider text-left uppercase lg:table-cell text-zinc-500 dark:text-primary-dark-400">
                            Tgl Lahir</th>
                        <th
                            class="hidden px-4 py-3 text-xs font-medium tracking-wider text-left uppercase xl:table-cell text-zinc-500 dark:text-primary-dark-400">
                            Terakhir Sync</th>
                        <th
                            class="px-4 py-3 text-xs font-medium tracking-wider text-center uppercase text-zinc-500 dark:text-primary-dark-400">
                            Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-zinc-200 dark:bg-primary-dark-800 dark:divide-primary-dark-700">
                    @forelse ($practitioners as $practitioner)
                        <tr :key="$practitioner->id" class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="font-mono text-sm font-medium text-primary-600 dark:text-primary-400">
                                    {{ $practitioner->ihs_number }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex-shrink-0 w-9 h-9 rounded-full flex items-center justify-center {{ $practitioner->gender === 'male' ? 'bg-blue-100 dark:bg-blue-900/50' : 'bg-pink-100 dark:bg-pink-900/50' }}">
                                        <flux:icon name="user"
                                            class="w-5 h-5 {{ $practitioner->gender === 'male' ? 'text-blue-600 dark:text-blue-400' : 'text-pink-600 dark:text-pink-400' }}" />
                                    </div>
                                    <div class="min-w-0">
                                        <p
                                            class="text-sm font-medium truncate text-zinc-900 dark:text-primary-dark-100">
                                            {{ $practitioner->name ?? '-' }}</p>
                                        <p class="text-xs text-zinc-500 dark:text-primary-dark-400">
                                            {{ $practitioner->phone ?? '-' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="hidden px-4 py-3 whitespace-nowrap md:table-cell">
                                <span class="font-mono text-sm text-zinc-600 dark:text-primary-dark-400">
                                    {{ $practitioner->nik ?? '-' }}
                                </span>
                            </td>
                            <td class="hidden px-4 py-3 whitespace-nowrap sm:table-cell">
                                @if ($practitioner->gender)
                                    <flux:badge :color="$practitioner->gender === 'male' ? 'blue' : 'pink'"
                                        size="sm">
                                        {{ $practitioner->gender === 'male' ? 'L' : 'P' }}
                                    </flux:badge>
                                @else
                                    <span class="text-sm text-zinc-400">-</span>
                                @endif
                            </td>
                            <td
                                class="hidden px-4 py-3 text-sm whitespace-nowrap lg:table-cell text-zinc-600 dark:text-primary-dark-400">
                                {{ $practitioner->birth_date?->format('d/m/Y') ?? '-' }}
                            </td>
                            <td
                                class="hidden px-4 py-3 text-sm whitespace-nowrap xl:table-cell text-zinc-600 dark:text-primary-dark-400">
                                {{ $practitioner->synced_at?->diffForHumans() ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <div class="flex items-center justify-center gap-1">
                                    <x-atoms.button variant="ghost" size="sm" icon="eye"
                                        wire:click="showDetail('{{ $practitioner->id }}')" title="Lihat Detail" />
                                    <x-atoms.button variant="ghost" size="sm" icon="arrow-path"
                                        wire:click="refreshPractitioner('{{ $practitioner->id }}')"
                                        title="Refresh Data" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <flux:icon name="user-group"
                                        class="w-12 h-12 text-zinc-300 dark:text-primary-dark-600" />
                                    <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">
                                        Belum ada data practitioner terintegrasi</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($practitioners->hasPages())
            <div class="px-4 py-4 border-t border-zinc-200 dark:border-primary-dark-700">
                {{ $practitioners->links() }}
            </div>
        @endif
    </div>

    {{-- Detail Modal --}}
    <x-organisms.modal wire:model="showDetailModal" maxWidth="3xl" title="">
        @if ($selectedPractitioner)
            <div class="space-y-6">
                <div class="flex items-start gap-4 pb-4 border-b border-zinc-200 dark:border-primary-dark-700">
                    <div
                        class="flex items-center justify-center flex-shrink-0 w-14 h-14 rounded-xl {{ $selectedPractitioner->gender === 'male' ? 'bg-blue-100 dark:bg-blue-900/50' : 'bg-pink-100 dark:bg-pink-900/50' }}">
                        <flux:icon name="user"
                            class="w-7 h-7 {{ $selectedPractitioner->gender === 'male' ? 'text-blue-600 dark:text-blue-400' : 'text-pink-600 dark:text-pink-400' }}" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <h2 class="text-lg font-bold text-zinc-900 dark:text-primary-dark-100">
                            {{ $selectedPractitioner->name ?? 'Tanpa Nama' }}</h2>
                        <div class="flex flex-wrap items-center gap-2 mt-2">
                            <flux:badge color="green" size="sm">
                                <flux:icon name="check-circle" class="w-3 h-3 mr-1" />
                                Terintegrasi
                            </flux:badge>
                            @if ($selectedPractitioner->gender)
                                <flux:badge :color="$selectedPractitioner->gender === 'male' ? 'blue' : 'pink'"
                                    size="sm">
                                    {{ $selectedPractitioner->gender === 'male' ? 'Laki-laki' : 'Perempuan' }}
                                </flux:badge>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="p-4 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                        <h4 class="mb-3 text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">
                            Identitas
                        </h4>
                        <dl class="space-y-2 text-sm">
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-24 text-zinc-500 dark:text-primary-dark-400">IHS Number</dt>
                                <dd class="font-mono text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedPractitioner->ihs_number }}</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-24 text-zinc-500 dark:text-primary-dark-400">NIK</dt>
                                <dd class="font-mono text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedPractitioner->nik ?? '-' }}</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-24 text-zinc-500 dark:text-primary-dark-400">Tgl Lahir</dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedPractitioner->birth_date?->format('d F Y') ?? '-' }}</dd>
                            </div>
                        </dl>
                    </div>
                    <div class="p-4 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                        <h4 class="mb-3 text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">
                            Kontak</h4>
                        <dl class="space-y-2 text-sm">
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-24 text-zinc-500 dark:text-primary-dark-400">Telepon</dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedPractitioner->phone ?? '-' }}
                                </dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-24 text-zinc-500 dark:text-primary-dark-400">Email</dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedPractitioner->email ?? '-' }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
                {{-- Data SIMRS --}}
                @if ($simrsData)
                    <div
                        class="p-4 rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/10">
                        <h4
                            class="mb-3 text-xs font-semibold uppercase text-amber-700 dark:text-amber-400 flex items-center gap-1.5">
                            <flux:icon name="building-office" class="w-3.5 h-3.5" />
                            Data SIMRS
                        </h4>
                        <dl class="grid grid-cols-1 gap-y-2 gap-x-4 text-sm sm:grid-cols-2">
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">ID Pegawai</dt>
                                <dd class="font-mono font-bold text-zinc-900 dark:text-primary-dark-100">
                                    {{ $simrsData['id'] }}</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">Nama</dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">{{ $simrsData['nama'] }}</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">Jenis Kelamin
                                </dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $simrsData['jk'] === 'L' ? 'Laki-laki' : ($simrsData['jk'] === 'P' ? 'Perempuan' : '-') }}
                                </dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">Jabatan</dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">{{ $simrsData['jbtn'] ?: '-' }}
                                </dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">Bidang</dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">{{ $simrsData['bidang'] ?: '-' }}
                                </dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">Departemen</dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $simrsData['departemen'] ?: '-' }}
                                </dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">Status</dt>
                                <dd>
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium {{ $simrsData['stts_aktif'] === 'AKTIF' ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300' : 'bg-zinc-100 dark:bg-primary-dark-700 text-zinc-600 dark:text-primary-dark-300' }}">
                                        {{ $simrsData['stts_aktif'] ?? '-' }}
                                    </span>
                                </dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-28 text-zinc-500 dark:text-primary-dark-400">Mulai Kerja
                                </dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $simrsData['mulai_kerja'] ?? '-' }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                @endif

                @if ($selectedPractitioner->qualification)
                    <div class="p-4 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                        <h4 class="mb-3 text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">
                            Kualifikasi
                        </h4>
                        <div class="space-y-2">
                            @foreach ($selectedPractitioner->qualification as $qual)
                                <div class="p-2 text-sm rounded bg-white dark:bg-primary-dark-800">
                                    <p class="font-medium text-zinc-900 dark:text-primary-dark-100">
                                        {{ $qual['code']['coding'][0]['display'] ?? ($qual['code']['text'] ?? '-') }}
                                    </p>
                                    @if (isset($qual['issuer']['display']))
                                        <p class="text-xs text-zinc-500">{{ $qual['issuer']['display'] }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
                @if ($selectedPractitioner->raw_response)
                    <div>
                        <h4 class="mb-2 text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">FHIR
                            Resource
                        </h4>
                        <x-atoms.code-block language="json" maxHeight="max-h-48">{{ json_encode($selectedPractitioner->raw_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</x-atoms.code-block>
                    </div>
                @endif
                <div
                    class="flex items-center justify-between pt-4 text-xs border-t border-zinc-200 dark:border-primary-dark-700 text-zinc-500 dark:text-primary-dark-400">
                    <span>Terakhir sync: {{ $selectedPractitioner->synced_at?->format('d M Y H:i') ?? '-' }}</span>
                </div>
                <div class="flex justify-end gap-3">
                    <x-atoms.button variant="ghost" wire:click="closeDetail">Tutup</x-atoms.button>
                    <x-atoms.button variant="primary" icon="arrow-path"
                        wire:click="refreshPractitioner('{{ $selectedPractitioner->id }}')">Refresh
                        Data</x-atoms.button>
                </div>
            </div>
        @endif
    
    </x-organisms.modal>

    {{-- Modal Sync SIMRS --}}
    <x-organisms.modal wire:model="showSyncModal" maxWidth="md" title="">
        <div class="space-y-6">
            <div class="flex items-center gap-4">
                <div
                    class="flex items-center justify-center w-12 h-12 rounded-full bg-primary-100 dark:bg-primary-900/30">
                    <flux:icon name="arrow-path" class="w-6 h-6 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <flux:heading size="lg">Sync Petugas dari SIMRS</flux:heading>
                    <p class="text-sm text-zinc-500 dark:text-primary-dark-400">
                        {{ number_format($unsyncedSimrsCount) }} pegawai aktif belum tersinkronisasi
                    </p>
                </div>
            </div>
            <div class="p-4 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
                <p class="text-sm text-amber-800 dark:text-amber-200">
                    <flux:icon name="exclamation-triangle" class="inline w-4 h-4 mr-1" />
                    Data diambil dari kolom <span class="font-mono font-bold">no_ktp</span> tabel pegawai SIMRS.
                    Pastikan queue worker sudah berjalan.
                </p>
            </div>
            
        <x-slot:footer>
            <div class="flex justify-end gap-3 pt-4">
                <x-atoms.button variant="ghost" wire:click="$set('showSyncModal', false)">Batal</x-atoms.button>
                <x-atoms.button variant="primary" icon="arrow-path" wire:click="syncSimrs">Mulai
                    Sync</x-atoms.button>
            </div>
        </x-slot:footer>
    </div>
    </x-organisms.modal>

    {{-- Modal Pilih Petugas --}}
    <x-organisms.modal wire:model="showPickModal" maxWidth="3xl" title="Pilih Petugas dari SIMRS">
        <div class="space-y-4">
            <div>
                
                <flux:text class="mt-0.5">Pilih pegawai aktif untuk disinkronkan ke Satu Sehat.</flux:text>
            </div>

            {{-- Filter bidang + search --}}
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1">
                    <flux:input wire:model.live.debounce.300ms="pickSearch" icon="magnifying-glass"
                        placeholder="Cari nama, NIK, atau No. KTP..." clearable />
                </div>
                <flux:select wire:model.live="pickDepartment" class="sm:w-52">
                    <flux:select.option value="">Semua Bidang</flux:select.option>
                    <flux:select.option value="medis">Tenaga Medis</flux:select.option>
                    <flux:select.option value="keperawatan">Keperawatan/Kebidanan</flux:select.option>
                    <flux:select.option value="penunjang">Penunjang Medis</flux:select.option>
                    <flux:select.option value="non_medis">Non Medis</flux:select.option>
                </flux:select>
            </div>

            <div
                class="overflow-hidden border rounded-xl border-zinc-200 dark:border-primary-dark-700 max-h-96 overflow-y-auto">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-primary-dark-700">
                    <thead class="bg-zinc-50 dark:bg-primary-dark-900 sticky top-0">
                        <tr>
                            <th
                                class="px-4 py-2.5 text-xs font-medium text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                Pegawai</th>
                            <th
                                class="px-4 py-2.5 text-xs font-medium text-left uppercase text-zinc-500 dark:text-primary-dark-400 hidden sm:table-cell">
                                Bidang</th>
                            <th
                                class="px-4 py-2.5 text-xs font-medium text-left uppercase text-zinc-500 dark:text-primary-dark-400 hidden md:table-cell">
                                No. KTP</th>
                            <th
                                class="px-4 py-2.5 text-xs font-medium text-center uppercase text-zinc-500 dark:text-primary-dark-400 w-28">
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-zinc-100 dark:divide-primary-dark-700/60 bg-white dark:bg-primary-dark-800">
                        @php
                            $syncedNiksForPick = $this->showPickModal
                                ? \App\Models\SatuSehat\SatuSehatPractitioner::pluck('nik')->toArray()
                                : [];
                            try {
                                $pickItems = $this->showPickModal
                                    ? \App\Models\Simrs\Pegawai::where('stts_aktif', 'AKTIF')
                                        ->whereNotNull('no_ktp')
                                        ->where('no_ktp', '!=', '')
                                        ->when(
                                            $pickSearch,
                                            fn($q) => $q
                                                ->where('nama', 'like', "%{$pickSearch}%")
                                                ->orWhere('nik', 'like', "%{$pickSearch}%")
                                                ->orWhere('no_ktp', 'like', "%{$pickSearch}%"),
                                        )
                                        ->when($pickDepartment === 'medis', fn($q) => $q->where('bidang', 'Medis'))
                                        ->when(
                                            $pickDepartment === 'keperawatan',
                                            fn($q) => $q->whereIn('bidang', ['Keperawatan', 'Kebidanan']),
                                        )
                                        ->when(
                                            $pickDepartment === 'penunjang',
                                            fn($q) => $q->where('bidang', 'Penunjang Medis'),
                                        )
                                        ->when(
                                            $pickDepartment === 'non_medis',
                                            fn($q) => $q->whereNotIn('bidang', [
                                                'Medis',
                                                'Keperawatan',
                                                'Kebidanan',
                                                'Penunjang Medis',
                                            ]),
                                        )
                                        ->orderBy('nama')
                                        ->limit(50)
                                        ->get()
                                    : collect();
                            } catch (\Exception) {
                                $pickItems = collect();
                            }
                        @endphp
                        @forelse ($pickItems as $emp)
                            @php $isSynced = in_array($emp->no_ktp, $syncedNiksForPick); @endphp
                            <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/40">
                                <td class="px-4 py-3">
                                    <p class="text-sm font-medium text-zinc-800 dark:text-primary-dark-100">
                                        {{ $emp->nama }}</p>
                                    <p class="text-xs text-zinc-400 dark:text-primary-dark-500">
                                        {{ $emp->jbtn ?: '-' }}</p>
                                </td>
                                <td class="px-4 py-3 hidden sm:table-cell">
                                    <span
                                        class="text-xs text-zinc-600 dark:text-primary-dark-400">{{ $emp->bidang ?: '-' }}</span>
                                </td>
                                <td class="px-4 py-3 hidden md:table-cell">
                                    <span
                                        class="font-mono text-xs text-zinc-600 dark:text-primary-dark-400">{{ $emp->no_ktp }}</span>
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    @if ($isSynced)
                                        <flux:badge color="green" size="sm">Sudah Sync</flux:badge>
                                    @else
                                        <x-atoms.button size="sm" variant="primary" icon="arrow-path"
                                            wire:click="selectAndSync('{{ $emp->no_ktp }}', '{{ $emp->id }}')">
                                            Sync
                                        </x-atoms.button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4"
                                    class="px-4 py-10 text-center text-sm text-zinc-400 dark:text-primary-dark-500">
                                    {{ $pickSearch || $pickDepartment ? 'Tidak ada pegawai cocok' : 'Tidak ada data SIMRS tersedia' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            
        <x-slot:footer>
            <div class="flex justify-end">
                <x-atoms.button variant="ghost" wire:click="$set('showPickModal', false)">Tutup</x-atoms.button>
            </div>
        </x-slot:footer>
    </div>
    </x-organisms.modal>
</div>
