<?php

use App\Jobs\SyncBatchSatuSehatPatientsJob;
use App\Jobs\SyncBpjsPatientsJob;
use App\Models\Bpjs\BpjsPatient;
use App\Models\SatuSehat\SatuSehatPatient;
use App\Services\SatuSehat\Resources\PatientService;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('Patient')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterBpjs = '';

    #[Url]
    public string $filterSs = '';

    public int $perPage = 25;

    // Modal konfirmasi hapus UUID BPJS
    public bool $showBpjsDeleteModal = false;

    public ?string $deleteBpjsNik = null;
    public string $deleteBpjsName = '';

    // Modal sync BPJS
    public bool $showSyncModal = false;

    // Modal sync Satu Sehat (bulk)
    public bool $showSyncSsModal = false;

    // Modal kirim Satu Sehat (single item)
    public bool $showSsSendModal = false;
    public string $ssSendNik = '';
    public string $ssSendName = '';
    public array $ssSendResult = [];
    public string $ssSendError = '';
    public bool $ssSendDone = false;

    // Detail Pasien
    public bool $showDetailModal = false;
    public ?array $selectedPatientDetail = null;

    public function showDetail(string $nik): void
    {
        $bpjs = BpjsPatient::where('nik', $nik)->first();
        $ss = SatuSehatPatient::where('nik', $nik)->first();
        $simrs = \App\Models\Simrs\Pasien::where('no_ktp', $nik)->first();

        $this->selectedPatientDetail = [
            'nik' => $nik,
            'name' => $simrs?->nm_pasien ?? $ss?->name ?? 'Unknown',
            'gender' => $simrs?->jk ?? '-',
            'birth_place' => $simrs?->tmp_lahir,
            'birth_date' => $simrs?->tgl_lahir?->format('Y-m-d') ?? $ss?->birth_date,
            'bpjs_uuid' => $bpjs?->id,
            'ihs_number' => $ss?->ihs_number,
            'phone' => $ss?->phone ?? $simrs?->no_tlp,
            'email' => $ss?->email ?? $simrs?->email,
            'address' => $ss?->address ?? $simrs?->alamat,
            'no_rkm_medis' => $simrs?->no_rkm_medis,
            'mother_name' => $simrs?->nm_ibu,
            'blood_type' => $simrs?->gol_darah,
            'job' => $simrs?->pekerjaan,
            'marital_status' => $simrs?->stts_nikah,
            'religion' => $simrs?->agama,
            'education' => $simrs?->pnd,
            'register_date' => $simrs?->tgl_daftar ? $simrs->tgl_daftar->format('d/m/Y') : null,
            'ss_raw' => $ss?->raw_response,
        ];

        $this->showDetailModal = true;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }
    public function updatedFilterBpjs(): void
    {
        $this->resetPage();
    }
    public function updatedFilterSs(): void
    {
        $this->resetPage();
    }

    public function generateBpjsUuid(string $nik, string $name): void
    {
        if (BpjsPatient::where('nik', $nik)->exists()) {
            $this->toastWarning('Pasien ini sudah memiliki UUID BPJS.');
            return;
        }

        BpjsPatient::create(['nik' => $nik]);

        $this->toastSuccess("UUID BPJS berhasil di-generate untuk: {$name}");
    }

    public function confirmBpjsDelete(string $nik, string $name): void
    {
        $this->deleteBpjsNik = $nik;
        $this->deleteBpjsName = $name;
        $this->showBpjsDeleteModal = true;
    }

    public function deleteBpjsUuid(): void
    {
        if (!$this->deleteBpjsNik) {
            return;
        }

        BpjsPatient::where('nik', $this->deleteBpjsNik)->delete();
        $this->showBpjsDeleteModal = false;
        $this->reset(['deleteBpjsNik', 'deleteBpjsName']);
        $this->toastSuccess('UUID BPJS berhasil dihapus.');
    }

    public function syncAllBpjs(): void
    {
        SyncBpjsPatientsJob::dispatch();
        $this->showSyncModal = false;
        $this->toastSuccess('Sync BPJS Pasien telah dijadwalkan di queue.', 'Dijadwalkan');
    }

    public function openSsSend(string $nik, string $name): void
    {
        $this->ssSendNik = $nik;
        $this->ssSendName = $name;
        $this->ssSendResult = [];
        $this->ssSendError = '';
        $this->ssSendDone = false;
        $this->showSsSendModal = true;
    }

    public function executeSsSend(): void
    {
        try {
            $result = app(PatientService::class)->findByNik($this->ssSendNik);

            if (!$result) {
                $this->ssSendError = 'Pasien tidak ditemukan di Satu Sehat.';
                $this->ssSendDone = true;
                return;
            }

            $resource = $result['resource'] ?? $result;
            $ihsNumber = $resource['id'] ?? null;

            if (!$ihsNumber) {
                $this->ssSendError = 'IHS Number tidak ditemukan dalam respons Satu Sehat.';
                $this->ssSendDone = true;
                return;
            }

            $names = $resource['name'] ?? [];
            $name = $names[0]['text'] ?? null;
            $telecom = collect($resource['telecom'] ?? []);
            $address = $resource['address'][0] ?? [];

            SatuSehatPatient::updateOrCreate(
                ['nik' => $this->ssSendNik],
                [
                    'ihs_number' => $ihsNumber,
                    'name' => $name,
                    'gender' => $resource['gender'] ?? null,
                    'birth_date' => $resource['birthDate'] ?? null,
                    'phone' => $telecom->firstWhere('system', 'phone')['value'] ?? null,
                    'email' => $telecom->firstWhere('system', 'email')['value'] ?? null,
                    'address' => implode(', ', $address['line'] ?? []),
                    'city' => $address['city'] ?? null,
                    'province' => $address['state'] ?? null,
                    'postal_code' => $address['postalCode'] ?? null,
                    'raw_response' => $resource,
                    'synced_at' => now(),
                ],
            );

            $this->ssSendResult = ['ihs_number' => $ihsNumber, 'name' => $name];
            $this->ssSendDone = true;
        } catch (\Throwable $e) {
            $this->ssSendError = $e->getMessage();
            $this->ssSendDone = true;
        }
    }

    public function syncAllSatuSehat(): void
    {
        SyncBatchSatuSehatPatientsJob::dispatch(syncAll: true, limit: 0);
        $this->showSyncSsModal = false;
        $this->toastSuccess('Sync Satu Sehat Pasien telah dijadwalkan di queue.', 'Dijadwalkan');
    }

    public function with(): array
    {
        // Siapkan NIK dari lokal hanya jika filter aktif
        $bpjsNiks = $this->filterBpjs !== '' ? BpjsPatient::pluck('nik') : collect();
        $ssNiks = $this->filterSs !== '' ? SatuSehatPatient::whereNotNull('ihs_number')->pluck('nik') : collect();

        $query = \App\Models\Simrs\Pasien::on('simrs')
            ->whereNotNull('no_ktp')
            ->where('no_ktp', '!=', '');

        if ($search = trim($this->search)) {
            $query->where(function ($q) use ($search) {
                $q->where('nm_pasien', 'like', "%{$search}%")
                    ->orWhere('no_ktp', 'like', "%{$search}%")
                    ->orWhere('no_rkm_medis', 'like', "%{$search}%");
            });
        }

        if ($this->filterBpjs === 'terdaftar') {
            $query->whereIn('no_ktp', $bpjsNiks);
        } elseif ($this->filterBpjs === 'belum') {
            $query->whereNotIn('no_ktp', $bpjsNiks);
        }

        if ($this->filterSs === 'terdaftar') {
            $query->whereIn('no_ktp', $ssNiks);
        } elseif ($this->filterSs === 'belum') {
            $query->whereNotIn('no_ktp', $ssNiks);
        }

        $items = $query->orderByDesc('tgl_daftar')->paginate($this->perPage);

        // Lookup BPJS & SS hanya untuk pasien di halaman ini
        $pageNiks = $items->pluck('no_ktp')->filter()->unique()->values()->toArray();
        $bpjsMap = BpjsPatient::whereIn('nik', $pageNiks)->get()->keyBy('nik');
        $ssMap = SatuSehatPatient::whereIn('nik', $pageNiks)->get()->keyBy('nik');

        $totalSimrs = 0;
        try {
            $totalSimrs = \App\Models\Simrs\Pasien::on('simrs')
                ->whereNotNull('no_ktp')->where('no_ktp', '!=', '')->count();
        } catch (\Throwable) {}

        $totalBpjs = BpjsPatient::count();
        $totalSs = SatuSehatPatient::whereNotNull('ihs_number')->count();

        return compact('items', 'bpjsMap', 'ssMap', 'totalSimrs', 'totalBpjs', 'totalSs');
    }
};

?>

<div>
    <x-ui.page-header title="Patient" subtitle="Data pasien dari SIMRS dengan status bridging BPJS dan Satu Sehat.">
        <x-slot:actions>
            <x-atoms.button wire:click="$set('showSyncSsModal', true)" icon="cloud-arrow-up" variant="outline"
                size="sm">
                Sync Satu Sehat
            </x-atoms.button>
            <x-atoms.button wire:click="$set('showSyncModal', true)" icon="arrow-path" variant="outline" size="sm">
                Sync BPJS
            </x-atoms.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <x-organisms.stat-card title="Total Pasien SIMRS" :value="number_format($totalSimrs)" color="zinc" icon="users" />
        <x-organisms.stat-card title="UUID BPJS" :value="number_format($totalBpjs)" color="blue" icon="identification" />
        <x-organisms.stat-card title="IHS Satu Sehat" :value="number_format($totalSs)" color="sky" icon="globe-alt" />
    </div>

    <x-organisms.data-panel>
        <x-slot:filter>
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-[200px]">
                    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                        placeholder="Cari NIK atau nama..." clearable />
                </div>
                <flux:select wire:model.live="filterBpjs" class="sm:w-44">
                    <flux:select.option value="">Semua Status BPJS</flux:select.option>
                    <flux:select.option value="terdaftar">Terdaftar BPJS</flux:select.option>
                    <flux:select.option value="belum">Belum Terdaftar</flux:select.option>
                </flux:select>
                <flux:select wire:model.live="filterSs" class="sm:w-44">
                    <flux:select.option value="">Semua Status SS</flux:select.option>
                    <flux:select.option value="terdaftar">Terdaftar SS</flux:select.option>
                    <flux:select.option value="belum">Belum Terdaftar</flux:select.option>
                </flux:select>
                <flux:select wire:model.live="perPage" class="w-40 shrink-0">
                    <flux:select.option value="25">25 / halaman</flux:select.option>
                    <flux:select.option value="50">50 / halaman</flux:select.option>
                    <flux:select.option value="100">100 / halaman</flux:select.option>
                </flux:select>
            </div>
        </x-slot:filter>

        <x-organisms.table>
            <x-slot:headings>
                <x-atoms.table-heading>NIK</x-atoms.table-heading>
                <x-atoms.table-heading>Nama</x-atoms.table-heading>
                <x-atoms.table-heading class="hidden md:table-cell">Gender</x-atoms.table-heading>
                <x-atoms.table-heading class="hidden md:table-cell">Tgl Lahir</x-atoms.table-heading>
                <x-atoms.table-heading>UUID BPJS</x-atoms.table-heading>
                <x-atoms.table-heading>IHS Satu Sehat</x-atoms.table-heading>
                <x-atoms.table-heading align="center" class="w-44">Aksi</x-atoms.table-heading>
            </x-slot:headings>

            @forelse ($items as $row)
                @php
                    $bpjsRow = $bpjsMap->get($row->no_ktp);
                    $ssRow = $ssMap->get($row->no_ktp);
                @endphp
                <x-molecules.table-row wire:key="patient-{{ $row->no_rkm_medis }}">
                    <x-atoms.table-cell nowrap>
                        <span
                            class="font-mono text-xs font-semibold text-zinc-700 dark:text-primary-dark-200">{{ $row->no_ktp ?: '-' }}</span>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        <p class="text-sm font-medium text-zinc-800 dark:text-primary-dark-100">{{ $row->nm_pasien }}</p>
                        <p class="text-xs text-zinc-400 dark:text-primary-dark-500 font-mono">{{ $row->no_rkm_medis }}</p>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell class="hidden md:table-cell" nowrap>
                        @if ($row->jk === 'L')
                            <flux:badge color="blue" size="sm">Laki-laki</flux:badge>
                        @elseif ($row->jk === 'P')
                            <flux:badge color="pink" size="sm">Perempuan</flux:badge>
                        @else
                            <span class="text-xs text-zinc-400">-</span>
                        @endif
                    </x-atoms.table-cell>
                    <x-atoms.table-cell class="hidden md:table-cell" nowrap>
                        <span
                            class="text-sm text-zinc-600 dark:text-primary-dark-300">{{ $row->tgl_lahir?->format('d/m/Y') ?? '-' }}</span>
                    </x-atoms.table-cell>

                    {{-- UUID BPJS --}}
                    <x-atoms.table-cell>
                        @if ($bpjsRow?->id)
                            <span
                                class="font-mono text-xs text-blue-700 dark:text-blue-400 break-all">{{ $bpjsRow->id }}</span>
                        @else
                            <span class="text-xs italic text-zinc-400 dark:text-primary-dark-500">Belum terdaftar</span>
                        @endif
                    </x-atoms.table-cell>

                    {{-- IHS SS --}}
                    <x-atoms.table-cell>
                        @if ($ssRow?->ihs_number)
                            <span
                                class="font-mono text-xs font-semibold text-sky-700 dark:text-sky-400">{{ $ssRow->ihs_number }}</span>
                        @else
                            <span class="text-xs italic text-zinc-400 dark:text-primary-dark-500">Belum terdaftar</span>
                        @endif
                    </x-atoms.table-cell>

                    {{-- Aksi --}}
                    <x-atoms.table-cell :action="true" align="center" nowrap>
                        <div class="flex items-center justify-center gap-1">
                            <x-atoms.button wire:click="showDetail('{{ $row->no_ktp }}')" size="sm"
                                variant="ghost" icon="eye" tooltip="Detail Pasien" />

                            <span class="w-px h-4 bg-zinc-200 dark:bg-primary-dark-600 mx-0.5"></span>

                            {{-- Satu Sehat --}}
                            <x-atoms.button size="sm" variant="ghost" icon="cloud-arrow-up"
                                tooltip="Kirim ke Satu Sehat" class="text-sky-600"
                                wire:click="openSsSend('{{ $row->no_ktp }}', '{{ addslashes($row->nm_pasien) }}')" />

                            <span class="w-px h-4 bg-zinc-200 dark:bg-primary-dark-600 mx-0.5"></span>

                            {{-- BPJS --}}
                            @if ($bpjsRow?->id)
                                <x-atoms.button size="sm" variant="ghost" icon="trash" class="text-red-500"
                                    tooltip="Hapus UUID BPJS"
                                    wire:click="confirmBpjsDelete('{{ $row->no_ktp }}', '{{ addslashes($row->nm_pasien) }}')" />
                            @else
                                <x-atoms.button size="sm" variant="ghost" icon="plus"
                                    tooltip="Generate UUID BPJS"
                                    wire:click="generateBpjsUuid('{{ $row->no_ktp }}', '{{ addslashes($row->nm_pasien) }}')" />
                            @endif
                        </div>
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @empty
                <x-molecules.table-row>
                    <x-atoms.table-cell colspan="7" align="center" class="py-16">
                        <div class="flex flex-col items-center gap-3">
                            <div
                                class="flex h-14 w-14 items-center justify-center rounded-2xl bg-zinc-100 dark:bg-primary-dark-700">
                                <flux:icon name="users" class="h-7 w-7 text-zinc-300 dark:text-primary-dark-500" />
                            </div>
                            <p class="text-sm font-semibold text-zinc-500 dark:text-primary-dark-400">Tidak ada data
                                pasien</p>
                        </div>
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @endforelse
        </x-organisms.table>

        @if ($items->hasPages())
            <x-slot:footer>
                {{ $items->links() }}
            </x-slot:footer>
        @endif
    </x-organisms.data-panel>

    {{-- Modal: Kirim Satu Sehat (single) --}}
    <x-organisms.modal wire:model="showSsSendModal" maxWidth="md" title="Kirim ke Satu Sehat">
        <div class="space-y-5">
            <div class="flex items-center gap-4">
                <div
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-sky-100 dark:bg-sky-900/30">
                    <flux:icon name="cloud-arrow-up" class="h-6 w-6 text-sky-600 dark:text-sky-400" />
                </div>
                <flux:text class="font-medium">{{ $ssSendName }}</flux:text>
            </div>

            @if (!$ssSendDone)
                <div
                    class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-primary-dark-700 dark:bg-primary-dark-900/40">
                    <p class="text-xs text-zinc-500">NIK: <span
                            class="font-mono font-semibold text-zinc-700 dark:text-primary-dark-200">{{ $ssSendNik }}</span>
                    </p>
                    <p class="mt-1 text-xs text-zinc-400 dark:text-primary-dark-500">Akan mencari IHS Number
                        berdasarkan NIK ini di Satu Sehat dan menyimpan hasilnya.</p>
                </div>
            @elseif ($ssSendError)
                <div class="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
                    <p class="text-sm font-semibold text-red-700 dark:text-red-400">Gagal</p>
                    <p class="mt-1 text-xs text-red-600 dark:text-red-300 break-words">{{ $ssSendError }}</p>
                </div>
            @else
                <div
                    class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-800 dark:bg-emerald-900/20 space-y-2">
                    <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-400">Berhasil disimpan</p>
                    <div>
                        <p class="text-xs text-zinc-400 dark:text-primary-dark-500">IHS Number</p>
                        <p class="font-mono text-sm font-bold text-emerald-700 dark:text-emerald-400">
                            {{ $ssSendResult['ihs_number'] ?? '-' }}</p>
                    </div>
                    @if ($ssSendResult['name'] ?? null)
                        <div>
                            <p class="text-xs text-zinc-400 dark:text-primary-dark-500">Nama (dari Satu Sehat)</p>
                            <p class="text-sm text-zinc-700 dark:text-primary-dark-200">{{ $ssSendResult['name'] }}
                            </p>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <x-slot:footer>
            @if (!$ssSendDone)
                <div class="flex justify-end gap-2">
                    <x-atoms.button wire:click="$set('showSsSendModal', false)" variant="ghost">Batal</x-atoms.button>
                    <x-atoms.button wire:click="executeSsSend" variant="primary" icon="cloud-arrow-up">
                        <span wire:loading.remove wire:target="executeSsSend">Kirim</span>
                        <span wire:loading wire:target="executeSsSend">Mengirim...</span>
                    </x-atoms.button>
                </div>
            @else
                <div class="flex justify-end">
                    <x-atoms.button wire:click="$set('showSsSendModal', false)" variant="ghost">Tutup</x-atoms.button>
                </div>
            @endif
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal: Sync Satu Sehat --}}
    <x-organisms.modal wire:model="showSyncSsModal" maxWidth="sm" title="Sync Semua Pasien ke Satu Sehat">
        <div class="space-y-6">
            <div class="flex items-center gap-4">
                <div
                    class="flex items-center justify-center w-12 h-12 rounded-2xl bg-sky-100 dark:bg-sky-900/30 shrink-0">
                    <flux:icon name="cloud-arrow-up" class="w-6 h-6 text-sky-600 dark:text-sky-400" />
                </div>
                <div>

                    <flux:text class="mt-0.5">Proses dijalankan di background queue.</flux:text>
                </div>
            </div>
            <flux:text class="text-sm text-zinc-600 dark:text-primary-dark-300">
                Akan mencari dan menyimpan IHS Number untuk semua pasien yang belum terdaftar di Satu Sehat (berdasarkan
                NIK).
            </flux:text>
        </div>

        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <x-atoms.button wire:click="$set('showSyncSsModal', false)" variant="ghost">Batal</x-atoms.button>
                <x-atoms.button wire:click="syncAllSatuSehat" variant="primary" icon="cloud-arrow-up">Sync
                    Sekarang</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal: Sync BPJS --}}
    <x-organisms.modal wire:model="showSyncModal" maxWidth="sm" title="Sync Semua Pasien BPJS">
        <div class="space-y-6">
            <div class="flex items-center gap-4">
                <div
                    class="flex items-center justify-center w-12 h-12 rounded-2xl bg-blue-100 dark:bg-blue-900/30 shrink-0">
                    <flux:icon name="arrow-path" class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                </div>
                <div>

                    <flux:text class="mt-0.5">Proses dijalankan di background queue.</flux:text>
                </div>
            </div>
            <flux:text class="text-sm text-zinc-600 dark:text-primary-dark-300">
                Akan mendaftarkan semua pasien SIMRS (berdasarkan NIK) yang belum memiliki UUID BPJS.
            </flux:text>
        </div>

        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <x-atoms.button wire:click="$set('showSyncModal', false)" variant="ghost">Batal</x-atoms.button>
                <x-atoms.button wire:click="syncAllBpjs" variant="primary" icon="arrow-path">Sync
                    Sekarang</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal: Hapus UUID BPJS --}}
    <x-organisms.modal wire:model="showBpjsDeleteModal" maxWidth="sm" title="Hapus UUID BPJS">
        <div class="space-y-6">
            <div class="flex items-center gap-4">
                <div
                    class="flex items-center justify-center w-12 h-12 rounded-2xl bg-red-100 dark:bg-red-900/30 shrink-0">
                    <flux:icon name="exclamation-triangle" class="w-6 h-6 text-red-600 dark:text-red-400" />
                </div>
                <div>

                    <flux:text class="mt-0.5">Tindakan ini tidak dapat dibatalkan.</flux:text>
                </div>
            </div>
            <div
                class="p-4 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/40 border border-zinc-200 dark:border-primary-dark-700 space-y-2">
                <p class="text-xs font-medium text-zinc-500">Nama: <span
                        class="text-zinc-700 dark:text-primary-dark-200">{{ $deleteBpjsName }}</span></p>
                <p class="text-xs font-medium text-zinc-500">NIK: <span
                        class="font-mono font-bold text-zinc-700 dark:text-primary-dark-200">{{ $deleteBpjsNik }}</span>
                </p>
            </div>
        </div>

        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <x-atoms.button wire:click="$set('showBpjsDeleteModal', false)" variant="ghost">Batal</x-atoms.button>
                <x-atoms.button wire:click="deleteBpjsUuid" variant="danger" icon="trash">Hapus</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal: Detail Pasien --}}
    <x-organisms.modal wire:model="showDetailModal" maxWidth="4xl" title="Detail Bridging Pasien">
        @if ($selectedPatientDetail)
            <div class="space-y-8">
                {{-- Header Detail --}}
                <div class="flex items-center gap-5 p-1">
                    <div
                        class="flex items-center justify-center w-20 h-20 rounded-3xl bg-primary-100 dark:bg-primary-900/30 shrink-0 text-primary-600 dark:text-primary-400 text-3xl font-black shadow-inner">
                        {{ strtoupper(substr($selectedPatientDetail['name'], 0, 1)) }}
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-3">
                            <h3 class="text-2xl font-black tracking-tight text-zinc-900 dark:text-primary-dark-100">
                                {{ $selectedPatientDetail['name'] }}
                            </h3>
                            <flux:badge size="sm" variant="pill"
                                :color="$selectedPatientDetail['gender'] === 'L' ? 'blue' : 'pink'">
                                {{ $selectedPatientDetail['gender'] === 'L' ? 'Laki-laki' : 'Perempuan' }}
                            </flux:badge>
                        </div>
                        <div class="flex items-center gap-4 mt-1.5">
                            <div class="flex items-center gap-1.5">
                                <flux:icon name="identification" class="w-4 h-4 text-zinc-400" />
                                <span
                                    class="font-mono text-sm font-bold text-zinc-600 dark:text-primary-dark-300">{{ $selectedPatientDetail['nik'] }}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <flux:icon name="hashtag" class="w-4 h-4 text-zinc-400" />
                                <span
                                    class="font-mono text-sm font-bold text-primary-600 dark:text-primary-400">{{ $selectedPatientDetail['no_rkm_medis'] ?: '-' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Status Bridging --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div
                        class="p-5 rounded-3xl bg-white dark:bg-primary-dark-900/40 border border-zinc-200 dark:border-primary-dark-700 shadow-sm transition-all hover:shadow-md">
                        <div class="flex items-center justify-between mb-4">
                            <p class="text-xs font-black uppercase tracking-widest text-zinc-400">BPJS Kesehatan</p>
                            <flux:icon name="shield-check" class="w-5 h-5 text-zinc-300" />
                        </div>
                        @if ($selectedPatientDetail['bpjs_uuid'])
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center shrink-0">
                                    <flux:icon name="check"
                                        class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[10px] font-bold text-zinc-400 uppercase leading-none mb-1">UUID
                                        Pasien</p>
                                    <p
                                        class="text-sm font-mono font-bold text-zinc-700 dark:text-primary-dark-200 truncate">
                                        {{ $selectedPatientDetail['bpjs_uuid'] }}
                                    </p>
                                </div>
                            </div>
                        @else
                            <div class="flex items-center gap-3 opacity-60">
                                <div
                                    class="w-10 h-10 rounded-xl bg-zinc-100 dark:bg-primary-dark-800 flex items-center justify-center shrink-0">
                                    <flux:icon name="x-mark" class="w-5 h-5 text-zinc-400" />
                                </div>
                                <p class="text-sm italic text-zinc-400">Belum terhubung ke BPJS</p>
                            </div>
                        @endif
                    </div>

                    <div
                        class="p-5 rounded-3xl bg-white dark:bg-primary-dark-900/40 border border-zinc-200 dark:border-primary-dark-700 shadow-sm transition-all hover:shadow-md">
                        <div class="flex items-center justify-between mb-4">
                            <p class="text-xs font-black uppercase tracking-widest text-zinc-400">Satu Sehat (Kemkes)
                            </p>
                            <flux:icon name="check-badge" class="w-5 h-5 text-zinc-300" />
                        </div>
                        @if ($selectedPatientDetail['ihs_number'])
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-10 h-10 rounded-xl bg-sky-100 dark:bg-sky-900/30 flex items-center justify-center shrink-0">
                                    <flux:icon name="check" class="w-5 h-5 text-sky-600 dark:text-sky-400" />
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[10px] font-bold text-zinc-400 uppercase leading-none mb-1">IHS
                                        Number</p>
                                    <p
                                        class="text-sm font-mono font-bold text-zinc-700 dark:text-primary-dark-200 truncate">
                                        {{ $selectedPatientDetail['ihs_number'] }}
                                    </p>
                                </div>
                            </div>
                        @else
                            <div class="flex items-center gap-3 opacity-60">
                                <div
                                    class="w-10 h-10 rounded-xl bg-zinc-100 dark:bg-primary-dark-800 flex items-center justify-center shrink-0">
                                    <flux:icon name="x-mark" class="w-5 h-5 text-zinc-400" />
                                </div>
                                <p class="text-sm italic text-zinc-400">Belum terhubung ke Satu Sehat</p>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    {{-- Informasi Personal --}}
                    <div class="space-y-5 md:col-span-2">
                        <div class="flex items-center gap-2 mb-2">
                            <flux:icon name="user" class="w-4 h-4 text-primary-500" />
                            <h4 class="text-xs font-black uppercase tracking-widest text-zinc-500">Informasi Personal
                                Pasien</h4>
                        </div>

                        <div class="grid grid-cols-2 gap-x-8 gap-y-6">
                            <div>
                                <p class="text-[11px] font-bold text-zinc-400 uppercase mb-1">Tempat, Tanggal Lahir</p>
                                <p class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">
                                    {{ $selectedPatientDetail['birth_place'] ?: '-' }},
                                    {{ \Carbon\Carbon::parse($selectedPatientDetail['birth_date'])->format('d F Y') ?: '-' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-[11px] font-bold text-zinc-400 uppercase mb-1">Nama Ibu Kandung</p>
                                <p class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">
                                    {{ $selectedPatientDetail['mother_name'] ?: '-' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-[11px] font-bold text-zinc-400 uppercase mb-1">Golongan Darah</p>
                                <p class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">
                                    <flux:badge size="sm" variant="solid" color="zinc">
                                        {{ $selectedPatientDetail['blood_type'] ?: '-' }}</flux:badge>
                                </p>
                            </div>
                            <div>
                                <p class="text-[11px] font-bold text-zinc-400 uppercase mb-1">Agama</p>
                                <p class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">
                                    {{ $selectedPatientDetail['religion'] ?: '-' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-[11px] font-bold text-zinc-400 uppercase mb-1">Status Perkawinan</p>
                                <p class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">
                                    {{ $selectedPatientDetail['marital_status'] ?: '-' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-[11px] font-bold text-zinc-400 uppercase mb-1">Pendidikan</p>
                                <p class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">
                                    {{ $selectedPatientDetail['education'] ?: '-' }}
                                </p>
                            </div>
                            <div class="col-span-2">
                                <p class="text-[11px] font-bold text-zinc-400 uppercase mb-1">Pekerjaan</p>
                                <p class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">
                                    {{ $selectedPatientDetail['job'] ?: '-' }}
                                </p>
                            </div>
                            <div class="col-span-2">
                                <p class="text-[11px] font-bold text-zinc-400 uppercase mb-1">Alamat Lengkap</p>
                                <p
                                    class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100 leading-relaxed">
                                    {{ $selectedPatientDetail['address'] ?: '-' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Informasi Kontak --}}
                    <div class="space-y-6">
                        <div class="space-y-4">
                            <div class="flex items-center gap-2">
                                <flux:icon name="phone" class="w-4 h-4 text-emerald-500" />
                                <h4 class="text-xs font-black uppercase tracking-widest text-zinc-500">Kontak</h4>
                            </div>
                            <div class="space-y-4">
                                <div>
                                    <p class="text-[11px] font-bold text-zinc-400 uppercase mb-1">Nomor Telepon</p>
                                    <p class="text-sm font-mono font-bold text-zinc-700 dark:text-primary-dark-200">
                                        {{ $selectedPatientDetail['phone'] ?: '-' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-[11px] font-bold text-zinc-400 uppercase mb-1">Alamat Email</p>
                                    <p class="text-sm font-medium text-zinc-700 dark:text-primary-dark-200">
                                        {{ $selectedPatientDetail['email'] ?: '-' }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4 pt-4 border-t border-zinc-100 dark:border-primary-dark-800">
                            <div class="flex items-center gap-2">
                                <flux:icon name="calendar-days" class="w-4 h-4 text-orange-500" />
                                <h4 class="text-xs font-black uppercase tracking-widest text-zinc-500">Sistem</h4>
                            </div>
                            <div>
                                <p class="text-[11px] font-bold text-zinc-400 uppercase mb-1">Terdaftar Sejak</p>
                                <p class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-200">
                                    {{ $selectedPatientDetail['register_date'] ?: '-' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                @if ($selectedPatientDetail['ss_raw'])
                    <div x-data="{ open: false }" class="pt-2">
                        <button @click="open = !open"
                            class="flex items-center gap-2 group text-[10px] font-black text-zinc-400 uppercase tracking-[0.2em] hover:text-primary-500 transition-colors">
                            <div
                                class="w-6 h-6 rounded-lg bg-zinc-100 dark:bg-primary-dark-800 flex items-center justify-center group-hover:bg-primary-100 dark:group-hover:bg-primary-900/30 transition-colors">
                                <flux:icon name="chevron-right" class="w-3 h-3 transition-transform"
                                    x-bind:class="open ? 'rotate-90' : ''" />
                            </div>
                            Data Mentah Satu Sehat (JSON)
                        </button>
                        <div x-show="open" x-cloak x-collapse class="mt-4">
                            <x-atoms.code-block language="json"
                                maxHeight="max-h-80">{{ json_encode($selectedPatientDetail['ss_raw'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</x-atoms.code-block>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <x-slot:footer>
            <div class="flex justify-end">
                <x-atoms.button wire:click="$set('showDetailModal', false)" variant="ghost">Tutup</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>
</div>
