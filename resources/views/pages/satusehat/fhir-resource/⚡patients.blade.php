<?php

use App\Models\SatuSehat\SatuSehatPatient;
use App\Models\Simrs\Pasien;
use App\Models\Configuration;
use App\Jobs\SyncSatuSehatPatientJob;
use Illuminate\Support\Facades\Bus;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('Satu Sehat - Patient')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterGender = '';

    #[Url]
    public int $perPage = 25;

    public bool $showDetailModal = false;
    public ?SatuSehatPatient $selectedPatient = null;
    public ?array $simrsData = null;

    // Modal sync SIMRS
    public bool $showSyncModal = false;

    // Modal pilih pasien
    public bool $showPickModal = false;
    public string $pickSearch = '';

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
        // dipanggil reactive saat pickSearch berubah
    }

    public function showDetail(string $id): void
    {
        $this->selectedPatient = SatuSehatPatient::find($id);
        $this->showDetailModal = true;
        $this->simrsData = null;
        if ($this->selectedPatient?->nik) {
            try {
                $p = Pasien::where('no_ktp', $this->selectedPatient->nik)->first();
                if ($p) {
                    $this->simrsData = [
                        'no_rkm_medis' => $p->no_rkm_medis,
                        'nm_pasien' => $p->nm_pasien,
                        'jk' => $p->jk,
                        'tgl_lahir' => $p->tgl_lahir?->format('d M Y'),
                        'tmp_lahir' => $p->tmp_lahir,
                        'alamat' => $p->alamat,
                        'no_tlp' => $p->no_tlp,
                        'agama' => $p->agama,
                        'pekerjaan' => $p->pekerjaan,
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
        $this->selectedPatient = null;
        $this->simrsData = null;
    }

    public function refreshPatient(string $id): void
    {
        $patient = SatuSehatPatient::find($id);
        if ($patient && $patient->nik) {
            SyncSatuSehatPatientJob::dispatch($patient->nik);
            $this->toastSuccess('Proses refresh dijadwalkan');
        }
    }

    /** Sync semua pasien SIMRS yang belum ada di Satu Sehat, dipecah menjadi beberapa batch */
    public function syncSimrs(): void
    {
        try {
            $syncedNiks = SatuSehatPatient::pluck('nik')->toArray();

            $pasiens = Pasien::whereNotNull('no_ktp')
                ->where('no_ktp', '!=', '')
                ->when(!empty($syncedNiks), fn($q) => $q->whereNotIn('no_ktp', $syncedNiks))
                ->get();

            if ($pasiens->isEmpty()) {
                $this->toastSuccess('Semua pasien sudah tersinkronisasi');
                $this->showSyncModal = false;
                return;
            }

            $batchSize = (int) (Configuration::where('key', 'satusehat.sync_batch_size')->value('value') ?: 1000);
            $chunks = $pasiens->chunk($batchSize);
            $total = $chunks->count();

            foreach ($chunks as $index => $chunk) {
                $jobs = $chunk->map(fn($p) => new SyncSatuSehatPatientJob($p->no_ktp, $p->no_rkm_medis))->all();
                Bus::batch($jobs)
                    ->name('Patient Sync #' . ($index + 1) . '/' . $total . ' - ' . now()->format('d/m/Y H:i'))
                    ->dispatch();
            }

            $this->toastSuccess("Sync {$pasiens->count()} pasien dipecah menjadi {$total} batch");
            $this->showSyncModal = false;
        } catch (\Exception $e) {
            $this->toastError('Gagal memulai sync: ' . $e->getMessage());
        }
    }

    /** Sync pasien individual yang dipilih dari modal picker */
    public function selectAndSync(string $noKtp): void
    {
        SyncSatuSehatPatientJob::dispatch($noKtp);
        $this->toastSuccess("Sync pasien dijadwalkan (NIK: {$noKtp})");
        $this->showPickModal = false;
        $this->pickSearch = '';
    }

    public function with(): array
    {
        $patients = SatuSehatPatient::query()
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

        // Hitung pasien SIMRS belum sync (pakai PHP karena beda koneksi DB)
        $unsyncedSimrsCount = 0;
        $pickItems = collect();
        try {
            $syncedNiks = SatuSehatPatient::pluck('nik')->toArray();

            $unsyncedSimrsCount = Pasien::whereNotNull('no_ktp')
                ->where('no_ktp', '!=', '')
                ->when(!empty($syncedNiks), fn($q) => $q->whereNotIn('no_ktp', $syncedNiks))
                ->count();

            if ($this->showPickModal) {
                $pickItems = Pasien::whereNotNull('no_ktp')
                    ->where('no_ktp', '!=', '')
                    ->when(
                        $this->pickSearch,
                        fn($q) => $q
                            ->where('nm_pasien', 'like', "%{$this->pickSearch}%")
                            ->orWhere('no_ktp', 'like', "%{$this->pickSearch}%")
                            ->orWhere('no_rkm_medis', 'like', "%{$this->pickSearch}%"),
                    )
                    ->orderBy('nm_pasien')
                    ->limit(50)
                    ->get()
                    ->map(
                        fn($p) => [
                            'no_rkm_medis' => $p->no_rkm_medis,
                            'nm_pasien' => $p->nm_pasien,
                            'no_ktp' => $p->no_ktp,
                            'jk' => $p->jk,
                            'tgl_lahir' => $p->tgl_lahir?->format('d/m/Y'),
                            'is_synced' => in_array($p->no_ktp, $syncedNiks),
                        ],
                    );
            }
        } catch (\Exception) {
            // SIMRS tidak terjangkau
        }

        return [
            'patients' => $patients,
            'unsyncedSimrsCount' => $unsyncedSimrsCount,
        ];
    }
};
?>

<div>
    {{-- Header --}}
    <x-ui.page-header title="Satu Sehat - Patient" subtitle="Data patient yang telah terintegrasi dengan Satu Sehat">
        <x-slot name="actions">
            <x-atoms.button variant="ghost" icon="user-plus" wire:click="$set('showPickModal', true)">
                Pilih Pasien
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
                        {{ number_format(SatuSehatPatient::count()) }}
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
                        {{ number_format(SatuSehatPatient::where('gender', 'male')->count()) }}
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
                        {{ number_format(SatuSehatPatient::where('gender', 'female')->count()) }}
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
                            Pasien</th>
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
                    @forelse ($patients as $patient)
                        <tr :key="$patient->id" class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="font-mono text-sm font-medium text-primary-600 dark:text-primary-400">
                                    {{ $patient->ihs_number }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex-shrink-0 w-9 h-9 rounded-full flex items-center justify-center {{ $patient->gender === 'male' ? 'bg-blue-100 dark:bg-blue-900/50' : 'bg-pink-100 dark:bg-pink-900/50' }}">
                                        <flux:icon name="user"
                                            class="w-5 h-5 {{ $patient->gender === 'male' ? 'text-blue-600 dark:text-blue-400' : 'text-pink-600 dark:text-pink-400' }}" />
                                    </div>
                                    <div class="min-w-0">
                                        <p
                                            class="text-sm font-medium truncate text-zinc-900 dark:text-primary-dark-100">
                                            {{ $patient->name ?? '-' }}</p>
                                        <p class="text-xs text-zinc-500 dark:text-primary-dark-400">
                                            {{ $patient->phone ?? '-' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="hidden px-4 py-3 whitespace-nowrap md:table-cell">
                                <span class="font-mono text-sm text-zinc-600 dark:text-primary-dark-400">
                                    {{ $patient->nik ?? '-' }}
                                </span>
                            </td>
                            <td class="hidden px-4 py-3 whitespace-nowrap sm:table-cell">
                                @if ($patient->gender)
                                    <flux:badge :color="$patient->gender === 'male' ? 'blue' : 'pink'"
                                        size="sm">
                                        {{ $patient->gender === 'male' ? 'L' : 'P' }}
                                    </flux:badge>
                                @else
                                    <span class="text-sm text-zinc-400">-</span>
                                @endif
                            </td>
                            <td
                                class="hidden px-4 py-3 text-sm whitespace-nowrap lg:table-cell text-zinc-600 dark:text-primary-dark-400">
                                {{ $patient->birth_date?->format('d/m/Y') ?? '-' }}
                            </td>
                            <td
                                class="hidden px-4 py-3 text-sm whitespace-nowrap xl:table-cell text-zinc-600 dark:text-primary-dark-400">
                                {{ $patient->synced_at?->diffForHumans() ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <div class="flex items-center justify-center gap-1">
                                    <x-atoms.button variant="ghost" size="sm" icon="eye"
                                        wire:click="showDetail('{{ $patient->id }}')" title="Lihat Detail" />
                                    <x-atoms.button variant="ghost" size="sm" icon="arrow-path"
                                        wire:click="refreshPatient('{{ $patient->id }}')" title="Refresh Data" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <flux:icon name="users"
                                        class="w-12 h-12 text-zinc-300 dark:text-primary-dark-600" />
                                    <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">
                                        Belum ada data pasien terintegrasi</p>
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
    <x-organisms.modal wire:model="showDetailModal" maxWidth="3xl" title="">
        @if ($selectedPatient)
            <div class="space-y-6">
                <div class="flex items-start gap-4 pb-4 border-b border-zinc-200 dark:border-primary-dark-700">
                    <div
                        class="flex items-center justify-center flex-shrink-0 w-14 h-14 rounded-xl {{ $selectedPatient->gender === 'male' ? 'bg-blue-100 dark:bg-blue-900/50' : 'bg-pink-100 dark:bg-pink-900/50' }}">
                        <flux:icon name="user"
                            class="w-7 h-7 {{ $selectedPatient->gender === 'male' ? 'text-blue-600 dark:text-blue-400' : 'text-pink-600 dark:text-pink-400' }}" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <h2 class="text-lg font-bold text-zinc-900 dark:text-primary-dark-100">
                            {{ $selectedPatient->name ?? 'Tanpa Nama' }}</h2>
                        <div class="flex flex-wrap items-center gap-2 mt-2">
                            <flux:badge color="green" size="sm">
                                <flux:icon name="check-circle" class="w-3 h-3 mr-1" />
                                Terintegrasi
                            </flux:badge>
                            @if ($selectedPatient->gender)
                                <flux:badge :color="$selectedPatient->gender === 'male' ? 'blue' : 'pink'"
                                    size="sm">
                                    {{ $selectedPatient->gender === 'male' ? 'Laki-laki' : 'Perempuan' }}
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
                                    {{ $selectedPatient->ihs_number }}</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-24 text-zinc-500 dark:text-primary-dark-400">NIK</dt>
                                <dd class="font-mono text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedPatient->nik ?? '-' }}</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-24 text-zinc-500 dark:text-primary-dark-400">Tgl Lahir</dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedPatient->birth_date?->format('d F Y') ?? '-' }}</dd>
                            </div>
                        </dl>
                    </div>
                    <div class="p-4 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                        <h4 class="mb-3 text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">
                            Kontak &
                            Alamat</h4>
                        <dl class="space-y-2 text-sm">
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-24 text-zinc-500 dark:text-primary-dark-400">Telepon</dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedPatient->phone ?? '-' }}</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-24 text-zinc-500 dark:text-primary-dark-400">Email</dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedPatient->email ?? '-' }}</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-24 text-zinc-500 dark:text-primary-dark-400">Alamat</dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedPatient->address ?? '-' }}
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
                                <dt class="flex-shrink-0 w-24 text-zinc-500 dark:text-primary-dark-400">No. RM</dt>
                                <dd class="font-mono font-bold text-zinc-900 dark:text-primary-dark-100">
                                    {{ $simrsData['no_rkm_medis'] }}</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-24 text-zinc-500 dark:text-primary-dark-400">Nama</dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">{{ $simrsData['nm_pasien'] }}
                                </dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-24 text-zinc-500 dark:text-primary-dark-400">Jenis Kelamin
                                </dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $simrsData['jk'] === 'L' ? 'Laki-laki' : ($simrsData['jk'] === 'P' ? 'Perempuan' : ($simrsData['jk'] ?: '-')) }}
                                </dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-24 text-zinc-500 dark:text-primary-dark-400">Tgl Lahir</dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $simrsData['tgl_lahir'] ?? '-' }}{{ $simrsData['tmp_lahir'] ? ' / ' . $simrsData['tmp_lahir'] : '' }}
                                </dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-24 text-zinc-500 dark:text-primary-dark-400">No. Telepon
                                </dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">{{ $simrsData['no_tlp'] ?: '-' }}
                                </dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="flex-shrink-0 w-24 text-zinc-500 dark:text-primary-dark-400">Pekerjaan</dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $simrsData['pekerjaan'] ?: '-' }}</dd>
                            </div>
                            @if ($simrsData['alamat'])
                                <div class="flex gap-2 sm:col-span-2">
                                    <dt class="flex-shrink-0 w-24 text-zinc-500 dark:text-primary-dark-400">Alamat</dt>
                                    <dd class="text-zinc-900 dark:text-primary-dark-100">{{ $simrsData['alamat'] }}
                                    </dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                @endif

                @if ($selectedPatient->raw_response)
                    <div>
                        <h4 class="mb-2 text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">FHIR
                            Resource
                        </h4>
                        <x-atoms.code-block language="json" maxHeight="max-h-48">{{ json_encode($selectedPatient->raw_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</x-atoms.code-block>
                    </div>
                @endif
                <div
                    class="flex items-center justify-between pt-4 text-xs border-t border-zinc-200 dark:border-primary-dark-700 text-zinc-500 dark:text-primary-dark-400">
                    <span>Terakhir sync: {{ $selectedPatient->synced_at?->format('d M Y H:i') ?? '-' }}</span>
                </div>
                <div class="flex justify-end gap-3">
                    <x-atoms.button variant="ghost" wire:click="closeDetail">Tutup</x-atoms.button>
                    <x-atoms.button variant="primary" icon="arrow-path"
                        wire:click="refreshPatient('{{ $selectedPatient->id }}')">Refresh Data</x-atoms.button>
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
                    <flux:heading size="lg">Sync Pasien dari SIMRS</flux:heading>
                    <p class="text-sm text-zinc-500 dark:text-primary-dark-400">
                        {{ number_format($unsyncedSimrsCount) }} pasien SIMRS belum tersinkronisasi
                    </p>
                </div>
            </div>
            <div class="p-4 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
                <p class="text-sm text-amber-800 dark:text-amber-200">
                    <flux:icon name="exclamation-triangle" class="inline w-4 h-4 mr-1" />
                    Data diambil dari kolom <span class="font-mono font-bold">no_ktp</span> tabel pasien SIMRS.
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

    {{-- Modal Pilih Pasien --}}
    <x-organisms.modal wire:model="showPickModal" maxWidth="3xl" title="Pilih Pasien dari SIMRS">
        <div class="space-y-4">
            <div>
                
                <flux:text class="mt-0.5">Pilih pasien untuk disinkronkan ke Satu Sehat.</flux:text>
            </div>

            <flux:input wire:model.live.debounce.300ms="pickSearch" icon="magnifying-glass"
                placeholder="Cari nama, NIK, atau No. RM..." clearable />

            <div
                class="overflow-hidden border rounded-xl border-zinc-200 dark:border-primary-dark-700 max-h-96 overflow-y-auto">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-primary-dark-700">
                    <thead class="bg-zinc-50 dark:bg-primary-dark-900 sticky top-0">
                        <tr>
                            <th
                                class="px-4 py-2.5 text-xs font-medium text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                Pasien</th>
                            <th
                                class="px-4 py-2.5 text-xs font-medium text-left uppercase text-zinc-500 dark:text-primary-dark-400 hidden sm:table-cell">
                                NIK</th>
                            <th
                                class="px-4 py-2.5 text-xs font-medium text-left uppercase text-zinc-500 dark:text-primary-dark-400 hidden md:table-cell">
                                Tgl Lahir</th>
                            <th
                                class="px-4 py-2.5 text-xs font-medium text-center uppercase text-zinc-500 dark:text-primary-dark-400 w-28">
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-zinc-100 dark:divide-primary-dark-700/60 bg-white dark:bg-primary-dark-800">
                        @php
                            $syncedNiksForPick = $this->showPickModal
                                ? \App\Models\SatuSehat\SatuSehatPatient::pluck('nik')->toArray()
                                : [];
                            try {
                                $pickItems = $this->showPickModal
                                    ? \App\Models\Simrs\Pasien::whereNotNull('no_ktp')
                                        ->where('no_ktp', '!=', '')
                                        ->when(
                                            $pickSearch,
                                            fn($q) => $q
                                                ->where('nm_pasien', 'like', "%{$pickSearch}%")
                                                ->orWhere('no_ktp', 'like', "%{$pickSearch}%")
                                                ->orWhere('no_rkm_medis', 'like', "%{$pickSearch}%"),
                                        )
                                        ->orderBy('nm_pasien')
                                        ->limit(50)
                                        ->get()
                                    : collect();
                            } catch (\Exception) {
                                $pickItems = collect();
                            }
                        @endphp
                        @forelse ($pickItems as $item)
                            @php $isSynced = in_array($item->no_ktp, $syncedNiksForPick); @endphp
                            <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/40">
                                <td class="px-4 py-3">
                                    <p class="text-sm font-medium text-zinc-800 dark:text-primary-dark-100">
                                        {{ $item->nm_pasien }}</p>
                                    <p class="text-xs font-mono text-zinc-400 dark:text-primary-dark-500">
                                        {{ $item->no_rkm_medis }}</p>
                                </td>
                                <td class="px-4 py-3 hidden sm:table-cell">
                                    <span
                                        class="font-mono text-xs text-zinc-600 dark:text-primary-dark-400">{{ $item->no_ktp }}</span>
                                </td>
                                <td
                                    class="px-4 py-3 text-xs text-zinc-500 dark:text-primary-dark-400 hidden md:table-cell">
                                    {{ $item->tgl_lahir?->format('d/m/Y') ?? '-' }}
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    @if ($isSynced)
                                        <flux:badge color="green" size="sm">Sudah Sync</flux:badge>
                                    @else
                                        <x-atoms.button size="sm" variant="primary" icon="arrow-path"
                                            wire:click="selectAndSync('{{ $item->no_ktp }}')">
                                            Sync
                                        </x-atoms.button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4"
                                    class="px-4 py-10 text-center text-sm text-zinc-400 dark:text-primary-dark-500">
                                    {{ $pickSearch ? 'Tidak ada pasien cocok dengan pencarian' : 'Tidak ada data SIMRS tersedia' }}
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
