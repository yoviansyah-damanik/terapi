<?php

use App\Jobs\SyncBatchSatuSehatPractitionersJob;
use App\Jobs\SyncBpjsPractitionersJob;
use App\Models\Bpjs\BpjsPractitioner;
use App\Models\Mapping\EmployeeMap;
use App\Models\Mapping\EmployeeSpecialtyMap;
use App\Models\SatuSehat\SatuSehatPractitioner;
use App\Models\Simrs\Departemen;
use App\Models\Simrs\Jabatan;
use App\Models\Simrs\Pegawai;
use App\Services\SatuSehat\Resources\PractitionerService;
use App\Services\Snomed\SnowstormService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app')] #[Title('Mapping Practitioner')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';
    #[Url]
    public string $filterBidang = '';
    #[Url]
    public string $filterJabatan = '';
    #[Url]
    public string $filterDepartemen = '';

    // SNOMED
    public bool $showSnomedSearchModal = false;
    public ?string $selectedSnomedId = null;
    public string $snomedInitialSearch = '';

    // SNOMED hapus
    public bool $showDeleteModal = false;
    public ?string $deleteId = null;
    public string $deleteName = '';

    // BPJS hapus
    public bool $showBpjsDeleteModal = false;
    public ?string $deleteBpjsIdentifier = null;
    public string $deleteBpjsName = '';

    // Sync
    public bool $showSyncModal = false;
    public bool $showSyncSsModal = false;

    // Satu Sehat single-send
    public bool $showSsSendModal = false;
    public string $ssSendNik = '';
    public string $ssSendName = '';
    public array $ssSendResult = [];
    public string $ssSendError = '';
    public bool $ssSendDone = false;

    // Specialty
    public bool $showSpecialtyModal = false;
    public ?string $selectedSpecialtyTarget = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }
    public function updatedFilterBidang(): void
    {
        $this->resetPage();
    }
    public function updatedFilterJabatan(): void
    {
        $this->resetPage();
    }
    public function updatedFilterDepartemen(): void
    {
        $this->resetPage();
    }

    // -------------------------------------------------------------------------
    // BPJS
    // -------------------------------------------------------------------------

    public function generateBpjsUuid(string $nik, string $name): void
    {
        if (BpjsPractitioner::where('identifier', $nik)->exists()) {
            $this->toastWarning('Practitioner ini sudah memiliki UUID BPJS.');
            return;
        }
        BpjsPractitioner::create(['identifier' => $nik, 'name' => $name]);
        $this->toastSuccess("UUID BPJS berhasil di-generate untuk: {$name}");
    }

    public function confirmBpjsDelete(string $nik, string $name): void
    {
        $this->deleteBpjsIdentifier = $nik;
        $this->deleteBpjsName = $name;
        $this->showBpjsDeleteModal = true;
    }

    public function deleteBpjsUuid(): void
    {
        BpjsPractitioner::where('identifier', $this->deleteBpjsIdentifier)->delete();
        $this->showBpjsDeleteModal = false;
        $this->reset(['deleteBpjsIdentifier', 'deleteBpjsName']);
        $this->toastSuccess('UUID BPJS berhasil dihapus.');
    }

    public function syncAllBpjs(): void
    {
        foreach (['dokter', 'Medis', 'Keperawatan', 'Penunjang Medis', 'Non Medis'] as $param) {
            SyncBpjsPractitionersJob::dispatch($param);
        }
        $this->showSyncModal = false;
        $this->toastSuccess('Sync BPJS semua bidang telah dijadwalkan di queue.', 'Dijadwalkan');
    }

    // -------------------------------------------------------------------------
    // Satu Sehat
    // -------------------------------------------------------------------------

    public function openSsSend(string $nik, string $name): void
    {
        if (empty(trim($nik)) || $nik === '-') {
            $this->toastError('NIK/KTP kosong, periksa kelengkapan profil.', 'Aksi Ditolak');
            return;
        }
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
            $result = app(PractitionerService::class)->findByNik($this->ssSendNik);
            if (!$result) {
                $this->ssSendError = 'Practitioner tidak ditemukan di Satu Sehat.';
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

            SatuSehatPractitioner::updateOrCreate(
                ['nik' => $this->ssSendNik],
                [
                    'ihs_number' => $ihsNumber,
                    'name' => $name,
                    'gender' => $resource['gender'] ?? null,
                    'birth_date' => $resource['birthDate'] ?? null,
                    'phone' => $telecom->firstWhere('system', 'phone')['value'] ?? null,
                    'email' => $telecom->firstWhere('system', 'email')['value'] ?? null,
                    'qualification' => $resource['qualification'] ?? null,
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
        SyncBatchSatuSehatPractitionersJob::dispatch(syncAll: true, limit: 0);
        $this->showSyncSsModal = false;
        $this->toastSuccess('Sync Satu Sehat telah dijadwalkan di queue.', 'Dijadwalkan');
    }

    // -------------------------------------------------------------------------
    // SNOMED CT
    // -------------------------------------------------------------------------

    public function selectSnomed(string $id): void
    {
        $status = app(SnowstormService::class)->testConnection();
        if (!$status['success']) {
            $this->toastError('Server SNOMED CT tidak dapat dijangkau. Periksa koneksi jaringan.', 'Koneksi Terputus');
            return;
        }
        $this->selectedSnomedId = $id;
        $this->snomedInitialSearch = EmployeeMap::where('employee_id', $id)->value('system_term') ?? '';
        $this->showSnomedSearchModal = true;
    }

    #[On('snomed-selected')]
    public function snomedSelected(string $system_code, string $system_term, string $system_display, string $category): void
    {
        if (!$this->selectedSnomedId) {
            return;
        }

        EmployeeMap::updateOrCreate(['employee_id' => $this->selectedSnomedId], ['system_code' => $system_code, 'system_term' => $system_term, 'system_display' => 'http://snomed.info/sct']);
        $this->showSnomedSearchModal = false;
        $this->reset(['selectedSnomedId']);
        $this->toastSuccess('Mapping SNOMED CT berhasil disimpan.', 'Sukses');
    }

    public function confirmDelete(string $id, string $name): void
    {
        $this->deleteId = $id;
        $this->deleteName = $name;
        $this->showDeleteModal = true;
    }

    public function deleteMapping(): void
    {
        EmployeeMap::where('employee_id', $this->deleteId)->delete();
        $this->showDeleteModal = false;
        $this->reset(['deleteId', 'deleteName']);
        $this->toastSuccess('Mapping SNOMED CT berhasil dihapus.', 'Sukses');
    }

    // -------------------------------------------------------------------------
    // Specialty
    // -------------------------------------------------------------------------

    public function selectSpecialty(string $id): void
    {
        $this->selectedSpecialtyTarget = $id;
        $this->showSpecialtyModal = true;
    }

    #[On('fhir-dictionary-selected')]
    public function specialtySelected(array $item): void
    {
        if (!$this->selectedSpecialtyTarget) {
            return;
        }

        EmployeeSpecialtyMap::updateOrCreate(
            ['employee_id' => $this->selectedSpecialtyTarget],
            [
                'specialty_code' => $item['system_code'],
                'specialty_term' => $item['system_term'],
                'specialty_display' => $item['system_display'] ?? null,
            ],
        );
        $this->showSpecialtyModal = false;
        $this->reset(['selectedSpecialtyTarget']);
        $this->toastSuccess('Mapping Speciality berhasil disimpan.');
    }

    public function removeSpecialty(string $id): void
    {
        EmployeeSpecialtyMap::where('employee_id', $id)->delete();
        $this->toastSuccess('Speciality berhasil dihapus.');
    }

    // -------------------------------------------------------------------------
    // Data
    // -------------------------------------------------------------------------

    public function with(): array
    {
        $bpjsMap = BpjsPractitioner::pluck('id', 'identifier');
        $ssMap = SatuSehatPractitioner::pluck('ihs_number', 'nik');

        // Opsi filter dari SIMRS
        $bidangOptions = [];
        $jabatanOptions = collect();
        $departemenOptions = collect();
        try {
            $bidangOptions = Pegawai::where('stts_aktif', 'AKTIF')->whereNotNull('bidang')->distinct()->orderBy('bidang')->pluck('bidang');
            $jabatanOptions = Jabatan::whereIn('kd_jbtn', Pegawai::where('stts_aktif', 'AKTIF')->whereNotNull('jbtn')->distinct()->pluck('jbtn'))
                ->orderBy('nm_jbtn')
                ->get();
            $departemenOptions = Departemen::whereIn('dep_id', Pegawai::where('stts_aktif', 'AKTIF')->whereNotNull('departemen')->distinct()->pluck('departemen'))
                ->orderBy('nama')
                ->get();
        } catch (\Throwable) {
        }

        $employees = Pegawai::where('stts_aktif', 'AKTIF')->when($this->search, fn($q) => $q->where('nama', 'like', "%{$this->search}%")->orWhere('id', 'like', "%{$this->search}%"))->when($this->filterBidang, fn($q) => $q->where('bidang', $this->filterBidang))->when($this->filterJabatan, fn($q) => $q->where('jbtn', $this->filterJabatan))->when($this->filterDepartemen, fn($q) => $q->where('departemen', $this->filterDepartemen))->orderBy('nama')->paginate(25);

        $ids = $employees->pluck('id')->toArray();
        $snomedMaps = EmployeeMap::whereIn('employee_id', $ids)->get()->keyBy('employee_id');
        $specialties = EmployeeSpecialtyMap::whereIn('employee_id', $ids)->get()->keyBy('employee_id');

        // Lookup nama jabatan & departemen untuk halaman ini
        $jabatanMap = Jabatan::whereIn('kd_jbtn', $employees->pluck('jbtn')->filter()->unique())->pluck('nm_jbtn', 'kd_jbtn');
        $departemenMap = Departemen::whereIn('dep_id', $employees->pluck('departemen')->filter()->unique())->pluck('nama', 'dep_id');

        $employees->getCollection()->transform(function ($emp) use ($snomedMaps, $bpjsMap, $ssMap, $specialties, $jabatanMap, $departemenMap) {
            $map = $snomedMaps->get($emp->id);
            $emp->snomed_code = $map?->system_code;
            $emp->snomed_term = $map?->system_term;
            $emp->bpjs_uuid = $bpjsMap[$emp->nik] ?? null;
            $emp->ihs_number = $ssMap[$emp->no_ktp] ?? null;
            $emp->specialty_code = $specialties->get($emp->id)?->specialty_code;
            $emp->specialty_term = $specialties->get($emp->id)?->specialty_term;
            $emp->jabatan_name = $jabatanMap[$emp->jbtn] ?? $emp->jbtn;
            $emp->departemen_name = $departemenMap[$emp->departemen] ?? $emp->departemen;
            return $emp;
        });

        return [
            'items' => $employees,
            'bidangOptions' => $bidangOptions,
            'jabatanOptions' => $jabatanOptions,
            'departemenOptions' => $departemenOptions,
        ];
    }
};
?>

<div>
    <x-ui.page-header title="Mapping Practitioner"
        subtitle="Hubungkan tenaga kesehatan dengan SNOMED CT, Speciality Kemkes, UUID BPJS, dan IHS Satu Sehat">
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

    <x-organisms.data-panel>
        <x-slot:filter>
            <div class="flex gap-3">
                <flux:input class="flex-1" wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                    placeholder="Cari nama atau ID pegawai..." clearable />
                <flux:select wire:model.live="filterBidang" class="!w-44">
                    <flux:select.option value="">Semua Bidang</flux:select.option>
                    @foreach ($bidangOptions as $opt)
                        <flux:select.option value="{{ $opt }}">{{ $opt }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="filterJabatan" class="!w-52">
                    <flux:select.option value="">Semua Jabatan</flux:select.option>
                    @foreach ($jabatanOptions as $jab)
                        <flux:select.option value="{{ $jab->kd_jbtn }}">{{ $jab->nm_jbtn }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="filterDepartemen" class="!w-52">
                    <flux:select.option value="">Semua Departemen</flux:select.option>
                    @foreach ($departemenOptions as $dep)
                        <flux:select.option value="{{ $dep->dep_id }}">{{ $dep->nama }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </x-slot:filter>

        <x-organisms.table>
            <x-slot:headings>
                <x-atoms.table-heading>Practitioner</x-atoms.table-heading>
                <x-atoms.table-heading class="w-36">ID Pegawai</x-atoms.table-heading>
                <x-atoms.table-heading class="w-28">Gender</x-atoms.table-heading>
                <x-atoms.table-heading>Mapping SNOMED CT</x-atoms.table-heading>
                <x-atoms.table-heading>Speciality</x-atoms.table-heading>
                <x-atoms.table-heading class="w-44">UUID BPJS</x-atoms.table-heading>
                <x-atoms.table-heading class="w-44">IHS Satu Sehat</x-atoms.table-heading>
                <x-atoms.table-heading align="center" class="w-52">Aksi</x-atoms.table-heading>
            </x-slot:headings>

            @forelse ($items as $item)
                <x-molecules.table-row :key="$item->id">
                    {{-- Practitioner --}}
                    <x-atoms.table-cell>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">
                                {{ $item->nama }}</p>
                            @if ($item->bidang || $item->jabatan_name || $item->departemen_name)
                                <div class="mt-2 space-y-1">
                                    @if ($item->bidang)
                                        <div>
                                            <flux:badge size="sm" color="violet" variant="pill">{{ $item->bidang }}
                                            </flux:badge>
                                        </div>
                                    @endif
                                    @if ($item->jabatan_name)
                                        <div
                                            class="flex items-center gap-1.5 text-xs text-zinc-500 dark:text-primary-dark-400">
                                            <flux:icon name="briefcase"
                                                class="h-3 w-3 shrink-0 text-zinc-400 dark:text-primary-dark-500" />
                                            <span>{{ $item->jabatan_name }}</span>
                                        </div>
                                    @endif
                                    @if ($item->departemen_name)
                                        <div
                                            class="flex items-center gap-1.5 text-xs text-zinc-400 dark:text-primary-dark-500">
                                            <flux:icon name="building-office" class="h-3 w-3 shrink-0" />
                                            <span>{{ $item->departemen_name }}</span>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </x-atoms.table-cell>

                    {{-- ID Pegawai --}}
                    <x-atoms.table-cell nowrap>
                        <div class="space-y-0.5">
                            <div class="flex items-center gap-1.5">
                                <span class="w-14 shrink-0 text-xs text-zinc-400 dark:text-primary-dark-500">ID</span>
                                <span
                                    class="font-mono text-xs font-semibold text-zinc-700 dark:text-primary-dark-200">{{ $item->id }}</span>
                            </div>
                            @if ($item->nik)
                                <div class="flex items-center gap-1.5">
                                    <span
                                        class="w-14 shrink-0 text-xs text-zinc-400 dark:text-primary-dark-500">Pengguna</span>
                                    <span
                                        class="font-mono text-xs text-zinc-500 dark:text-primary-dark-400">{{ $item->nik }}</span>
                                </div>
                            @endif
                            @if ($item->no_ktp)
                                <div class="flex items-center gap-1.5">
                                    <span class="w-14 shrink-0 text-xs text-zinc-400 dark:text-primary-dark-500">No.
                                        KTP</span>
                                    <span
                                        class="font-mono text-xs text-zinc-500 dark:text-primary-dark-400">{{ $item->no_ktp }}</span>
                                </div>
                            @endif
                        </div>
                    </x-atoms.table-cell>

                    {{-- Gender --}}
                    <x-atoms.table-cell>
                        @if ($item->jk === 'Pria')
                            <flux:badge color="blue" size="sm">Laki-laki</flux:badge>
                        @elseif ($item->jk === 'Warnita')
                            <flux:badge color="pink" size="sm">Perempuan</flux:badge>
                        @else
                            <span class="text-xs text-zinc-400 dark:text-primary-dark-500">—</span>
                        @endif
                    </x-atoms.table-cell>

                    {{-- SNOMED CT --}}
                    <x-atoms.table-cell>
                        @if ($item->snomed_code)
                            <div class="flex items-start gap-2.5">
                                <span
                                    class="mt-1 h-2 w-2 shrink-0 rounded-full bg-emerald-400 ring-2 ring-emerald-100 dark:bg-emerald-500 dark:ring-emerald-900/50"></span>
                                <div class="min-w-0">
                                    <p class="font-mono text-xs font-bold text-emerald-700 dark:text-emerald-400">
                                        {{ $item->snomed_code }}</p>
                                    <p
                                        class="mt-0.5 line-clamp-2 text-xs leading-snug text-zinc-500 dark:text-primary-dark-400">
                                        {{ $item->snomed_term }}</p>
                                </div>
                            </div>
                        @else
                            <div class="flex items-center gap-2 text-zinc-400 dark:text-primary-dark-500">
                                <span class="h-2 w-2 rounded-full bg-zinc-200 dark:bg-primary-dark-600"></span>
                                <span class="text-xs italic">Belum di-mapping</span>
                            </div>
                        @endif
                    </x-atoms.table-cell>

                    {{-- Speciality --}}
                    <x-atoms.table-cell>
                        @if ($item->specialty_code)
                            <div class="flex items-start gap-2.5">
                                <span
                                    class="mt-1 h-2 w-2 shrink-0 rounded-full bg-violet-400 ring-2 ring-violet-100 dark:bg-violet-500 dark:ring-violet-900/50"></span>
                                <div class="min-w-0">
                                    <p class="font-mono text-xs font-bold text-violet-700 dark:text-violet-400">
                                        {{ $item->specialty_code }}</p>
                                    <p
                                        class="mt-0.5 line-clamp-2 text-xs leading-snug text-zinc-500 dark:text-primary-dark-400">
                                        {{ $item->specialty_term }}</p>
                                </div>
                            </div>
                        @else
                            <div class="flex items-center gap-2 text-zinc-400 dark:text-primary-dark-500">
                                <span class="h-2 w-2 rounded-full bg-zinc-200 dark:bg-primary-dark-600"></span>
                                <span class="text-xs italic">Belum di-mapping</span>
                            </div>
                        @endif
                    </x-atoms.table-cell>

                    {{-- UUID BPJS --}}
                    <x-atoms.table-cell>
                        @if ($item->bpjs_uuid)
                            <span
                                class="font-mono text-xs text-blue-700 dark:text-blue-400 break-all">{{ $item->bpjs_uuid }}</span>
                        @else
                            <span class="text-xs italic text-zinc-400 dark:text-primary-dark-500">Belum terdaftar</span>
                        @endif
                    </x-atoms.table-cell>

                    {{-- IHS Satu Sehat --}}
                    <x-atoms.table-cell>
                        @if ($item->ihs_number)
                            <span
                                class="font-mono text-xs font-semibold text-sky-700 dark:text-sky-400">{{ $item->ihs_number }}</span>
                        @else
                            <span class="text-xs italic text-zinc-400 dark:text-primary-dark-500">Belum terdaftar</span>
                        @endif
                    </x-atoms.table-cell>

                    {{-- Aksi --}}
                    <x-atoms.table-cell :action="true" align="center" nowrap>
                        @if (!$item->ihs_number)
                            <x-atoms.button size="sm" variant="ghost" icon="cloud-arrow-up"
                                tooltip="Kirim ke Satu Sehat" class="text-sky-600"
                                wire:click="openSsSend('{{ $item->no_ktp ?? '' }}', '{{ addslashes($item->nama) }}')" />
                        @endif

                        <span class="mx-0.5 h-4 w-px bg-zinc-200 dark:bg-primary-dark-600"></span>

                        {{-- SNOMED --}}
                        <x-atoms.button wire:click="selectSnomed('{{ $item->id }}')" size="sm" variant="ghost"
                            icon="{{ $item->snomed_code ? 'pencil-square' : 'plus' }}"
                            tooltip="{{ $item->snomed_code ? 'Ubah SNOMED' : 'Petakan SNOMED' }}" />
                        @if ($item->snomed_code)
                            <x-atoms.button
                                wire:click="confirmDelete('{{ $item->id }}', '{{ addslashes($item->nama) }}')"
                                size="sm" icon="trash" variant="ghost" tooltip="Hapus SNOMED"
                                class="text-red-500" />
                        @endif

                        <span class="mx-0.5 h-4 w-px bg-zinc-200 dark:bg-primary-dark-600"></span>

                        {{-- Speciality --}}
                        <x-atoms.button wire:click="selectSpecialty('{{ $item->id }}')" size="sm"
                            variant="ghost" icon="{{ $item->specialty_code ? 'pencil-square' : 'plus-circle' }}"
                            tooltip="{{ $item->specialty_code ? 'Ubah Speciality' : 'Petakan Speciality' }}" />
                        @if ($item->specialty_code)
                            <x-atoms.button wire:click="removeSpecialty('{{ $item->id }}')" size="sm"
                                icon="trash" variant="ghost" tooltip="Hapus Speciality" class="text-red-500" />
                        @endif

                        <span class="mx-0.5 h-4 w-px bg-zinc-200 dark:bg-primary-dark-600"></span>

                        {{-- BPJS --}}
                        @if ($item->bpjs_uuid)
                            <x-atoms.button
                                wire:click="confirmBpjsDelete('{{ $item->nik }}', '{{ addslashes($item->nama) }}')"
                                size="sm" icon="trash" variant="ghost" tooltip="Hapus UUID BPJS"
                                class="text-red-500" />
                        @else
                            <x-atoms.button
                                wire:click="generateBpjsUuid('{{ $item->nik }}', '{{ addslashes($item->nama) }}')"
                                size="sm" icon="plus" variant="ghost" tooltip="Generate UUID BPJS" />
                        @endif
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @empty
                <x-molecules.table-row>
                    <x-atoms.table-cell colspan="8" align="center" class="py-16">
                        <div class="flex flex-col items-center gap-3">
                            <div
                                class="flex h-14 w-14 items-center justify-center rounded-2xl bg-zinc-100 dark:bg-primary-dark-700">
                                <flux:icon name="user-group"
                                    class="h-7 w-7 text-zinc-300 dark:text-primary-dark-500" />
                            </div>
                            <p class="text-sm font-semibold text-zinc-500 dark:text-primary-dark-400">Tidak ada data
                                practitioner</p>
                            <p class="text-xs text-zinc-400 dark:text-primary-dark-500">Coba ubah kata kunci pencarian
                            </p>
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

    {{-- Modal: Kirim ke Satu Sehat --}}
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
                    <p class="text-xs text-zinc-500">NIK / KTP: <span
                            class="font-mono font-semibold text-zinc-700 dark:text-primary-dark-200">{{ $ssSendNik }}</span>
                    </p>
                    <p class="mt-1 text-xs text-zinc-400 dark:text-primary-dark-500">Akan mencari IHS Number
                        berdasarkan
                        NIK ini di Satu Sehat dan menyimpan hasilnya.</p>
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
            <div class="flex justify-end gap-2">
                @if (!$ssSendDone)
                    <x-atoms.button wire:click="$set('showSsSendModal', false)" variant="ghost">Batal</x-atoms.button>
                    <x-atoms.button wire:click="executeSsSend" variant="primary" icon="cloud-arrow-up">
                        <span wire:loading.remove wire:target="executeSsSend">Kirim</span>
                        <span wire:loading wire:target="executeSsSend">Mengirim...</span>
                    </x-atoms.button>
                @else
                    <x-atoms.button wire:click="$set('showSsSendModal', false)" variant="ghost">Tutup</x-atoms.button>
                @endif
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal: Sync Satu Sehat --}}
    <x-organisms.modal wire:model="showSyncSsModal" maxWidth="sm" title="Sync Semua ke Satu Sehat">
        <div class="space-y-6">
            <div class="flex items-center gap-4">
                <div
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-sky-100 dark:bg-sky-900/30">
                    <flux:icon name="cloud-arrow-up" class="h-6 w-6 text-sky-600 dark:text-sky-400" />
                </div>
                <flux:text>Proses dijalankan di background queue.</flux:text>
            </div>
            <flux:text class="text-sm text-zinc-600 dark:text-primary-dark-300">
                Akan mencari dan menyimpan IHS Number untuk semua practitioner yang belum terdaftar di Satu Sehat.
            </flux:text>

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-atoms.button wire:click="$set('showSyncSsModal', false)" variant="ghost">Batal</x-atoms.button>
                    <x-atoms.button wire:click="syncAllSatuSehat" variant="primary" icon="cloud-arrow-up">Sync
                        Sekarang</x-atoms.button>
                </div>
            </x-slot:footer>
        </div>
    </x-organisms.modal>

    {{-- Modal: Sync BPJS --}}
    <x-organisms.modal wire:model="showSyncModal" maxWidth="sm" title="Sync UUID BPJS">
        <div class="space-y-6">
            <div class="flex items-center gap-4">
                <div
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-blue-100 dark:bg-blue-900/30">
                    <flux:icon name="arrow-path" class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                </div>
                <flux:text>Proses dijalankan di background queue untuk semua bidang.</flux:text>
            </div>

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-atoms.button wire:click="$set('showSyncModal', false)" variant="ghost">Batal</x-atoms.button>
                    <x-atoms.button wire:click="syncAllBpjs" variant="primary" icon="arrow-path">Sync
                        Sekarang</x-atoms.button>
                </div>
            </x-slot:footer>
        </div>
    </x-organisms.modal>

    {{-- Modal: Pencarian SNOMED CT --}}
    <x-organisms.modal wire:model="showSnomedSearchModal" maxWidth="4xl" title="Pilih Kode SNOMED CT">
        <div class="space-y-4">
            <flux:text>Klik baris untuk memilih kode spesialistik / okupasi.</flux:text>
            <livewire:components.snomed-search defaultTag="occupation" :initialSearch="$snomedInitialSearch" :key="'snomed-practitioner-' . ($selectedSnomedId ?? 'new')" />

            <x-slot:footer>
                <div class="flex justify-end">
                    <x-atoms.button wire:click="$set('showSnomedSearchModal', false)"
                        variant="ghost">Tutup</x-atoms.button>
                </div>
            </x-slot:footer>
        </div>
    </x-organisms.modal>

    {{-- Modal: Pilih Speciality Kemkes --}}
    <x-organisms.modal wire:model="showSpecialtyModal" maxWidth="3xl" title="Pilih Speciality Kemkes">
        <livewire:components.fhir-dictionaries-search :limitTypes="['practitioner-speciality']" :limitSources="['kemkes']" :key="'specialty-' . ($selectedSpecialtyTarget ?? 'new')" />
        <x-slot:footer>
            <div class="flex justify-end">
                <x-atoms.button wire:click="$set('showSpecialtyModal', false)" variant="ghost">Tutup</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal: Hapus UUID BPJS --}}
    <x-organisms.modal wire:model="showBpjsDeleteModal" maxWidth="sm" title="Hapus UUID BPJS">
        <div class="space-y-6">
            <div class="flex items-center gap-4">
                <div
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-red-100 dark:bg-red-900/30">
                    <flux:icon name="exclamation-triangle" class="h-6 w-6 text-red-600 dark:text-red-400" />
                </div>
                <flux:text>Tindakan ini tidak dapat dibatalkan.</flux:text>
            </div>
            <div
                class="space-y-2 rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-primary-dark-700 dark:bg-primary-dark-900/40">
                <p class="text-xs font-medium text-zinc-500">NIK: <span
                        class="font-mono font-bold text-zinc-700 dark:text-primary-dark-200">{{ $deleteBpjsIdentifier }}</span>
                </p>
                <p class="text-xs font-medium text-zinc-500">Nama: <span
                        class="text-zinc-700 dark:text-primary-dark-200">{{ $deleteBpjsName }}</span></p>
            </div>

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-atoms.button wire:click="$set('showBpjsDeleteModal', false)"
                        variant="ghost">Batal</x-atoms.button>
                    <x-atoms.button wire:click="deleteBpjsUuid" variant="danger" icon="trash">Hapus
                        UUID</x-atoms.button>
                </div>
            </x-slot:footer>
        </div>
    </x-organisms.modal>

    {{-- Modal: Hapus Mapping SNOMED --}}
    <x-organisms.modal wire:model="showDeleteModal" maxWidth="sm" title="Hapus Mapping SNOMED">
        <div class="space-y-6">
            <div class="flex items-center gap-4">
                <div
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-red-100 dark:bg-red-900/30">
                    <flux:icon name="exclamation-triangle" class="h-6 w-6 text-red-600 dark:text-red-400" />
                </div>
                <flux:text>Tindakan ini tidak dapat dibatalkan.</flux:text>
            </div>
            <div
                class="space-y-2 rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-primary-dark-700 dark:bg-primary-dark-900/40">
                <div class="flex items-center gap-3">
                    <span class="w-10 shrink-0 text-xs font-medium text-zinc-400">ID</span>
                    <span
                        class="font-mono text-sm font-bold text-primary-600 dark:text-primary-400">{{ $deleteId }}</span>
                </div>
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 w-10 shrink-0 text-xs font-medium text-zinc-400">Nama</span>
                    <span class="text-sm text-zinc-700 dark:text-primary-dark-300">{{ $deleteName }}</span>
                </div>
            </div>

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-atoms.button wire:click="$set('showDeleteModal', false)" variant="ghost">Batal</x-atoms.button>
                    <x-atoms.button wire:click="deleteMapping" variant="danger">Hapus Mapping</x-atoms.button>
                </div>
            </x-slot:footer>
        </div>
    </x-organisms.modal>
</div>
