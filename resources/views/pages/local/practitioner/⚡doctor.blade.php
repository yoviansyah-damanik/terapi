<?php

use App\Jobs\SyncBatchSatuSehatPractitionersJob;
use App\Jobs\SyncBpjsPractitionersJob;
use App\Models\Bpjs\BpjsPractitioner;
use App\Services\SatuSehat\Resources\PractitionerService;
use App\Models\Mapping\DoctorMap;
use App\Models\SatuSehat\SatuSehatPractitioner;
use App\Models\Simrs\Dokter;
use App\Services\Snomed\SnowstormService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Halaman mapping dokter ke SNOMED CT + UUID BPJS + IHS Satu Sehat. */
new #[Layout('layouts::app')] #[Title('Mapping Dokter → SNOMED CT')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterBpjs = '';

    #[Url]
    public string $filterSs = '';

    public bool $showSnomedSearchModal = false;
    public ?string $selectedDoctorCode = null;

    public bool $showDeleteModal = false;
    public ?string $deleteCode = null;
    public ?string $deleteName = null;

    // Modal konfirmasi hapus UUID BPJS
    public bool $showBpjsDeleteModal = false;
    public ?string $deleteBpjsCode = null;
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

    public string $snomedInitialSearch = '';

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

    public function generateBpjsUuid(string $kd_dokter, string $nm_dokter): void
    {
        if (BpjsPractitioner::where('identifier', $kd_dokter)->exists()) {
            $this->toastWarning('Dokter ini sudah memiliki UUID BPJS.');
            return;
        }

        BpjsPractitioner::create(['identifier' => $kd_dokter, 'name' => $nm_dokter]);
        $this->toastSuccess("UUID BPJS berhasil di-generate untuk: {$nm_dokter}");
    }

    public function confirmBpjsDelete(string $kd_dokter, string $nm_dokter): void
    {
        $this->deleteBpjsCode = $kd_dokter;
        $this->deleteBpjsName = $nm_dokter;
        $this->showBpjsDeleteModal = true;
    }

    public function deleteBpjsUuid(): void
    {
        if (!$this->deleteBpjsCode) {
            return;
        }

        BpjsPractitioner::where('identifier', $this->deleteBpjsCode)->delete();
        $this->showBpjsDeleteModal = false;
        $this->reset(['deleteBpjsCode', 'deleteBpjsName']);
        $this->toastSuccess('UUID BPJS berhasil dihapus.');
    }

    public function syncAllBpjs(): void
    {
        SyncBpjsPractitionersJob::dispatch('dokter');
        $this->showSyncModal = false;
        $this->toastSuccess('Sync BPJS Dokter telah dijadwalkan di queue.', 'Dijadwalkan');
    }

    /** Buka modal kirim Satu Sehat; nik digunakan sebagai NIK (relasi ke pegawai.no_ktp). */
    public function openSsSend(string $nik, string $name): void
    {
        if (empty(trim($nik)) || $nik === '-') {
            $this->toastError('Harap periksa kelengkapan Profil Dokter (NIK / KTP kosong).', 'Aksi Ditolak');
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
                $this->ssSendError = 'Dokter tidak ditemukan di Satu Sehat.';
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
        $this->toastSuccess('Sync Satu Sehat Dokter telah dijadwalkan di queue.', 'Dijadwalkan');
    }

    public function with(): array
    {
        $bpjsMap = BpjsPractitioner::pluck('id', 'identifier');
        $ssMap = SatuSehatPractitioner::pluck('ihs_number', 'nik');

        $query = Dokter::with('pegawai')->active()->search($this->search)->orderBy('nm_dokter');

        if ($this->filterBpjs === 'terdaftar') {
            $query->whereIn('kd_dokter', $bpjsMap->keys()->toArray());
        } elseif ($this->filterBpjs === 'belum') {
            $query->whereNotIn('kd_dokter', $bpjsMap->keys()->toArray());
        }

        if ($this->filterSs === 'terdaftar') {
            $query->whereHas('pegawai', function ($q) use ($ssMap) {
                $q->whereIn('no_ktp', $ssMap->filter()->keys()->toArray());
            });
        } elseif ($this->filterSs === 'belum') {
            $query->whereHas('pegawai', function ($q) use ($ssMap) {
                $q->whereNotIn('no_ktp', $ssMap->filter()->keys()->toArray());
            });
        }

        $doctors = $query->paginate(25);

        $codes = $doctors->pluck('kd_dokter')->toArray();
        $mappings = DoctorMap::whereIn('doctor_code', $codes)->get()->keyBy('doctor_code');

        $doctors->getCollection()->transform(function ($doctor) use ($mappings, $bpjsMap, $ssMap) {
            $map = $mappings->get($doctor->kd_dokter);
            $doctor->snomed_code = $map?->system_code;
            $doctor->snomed_term = $map?->system_term;
            $doctor->bpjs_uuid = $bpjsMap[$doctor->kd_dokter] ?? null;
            $doctor->ihs_number = $ssMap[$doctor->pegawai?->no_ktp] ?? null;
            return $doctor;
        });

        return ['items' => $doctors];
    }

    /** Cek koneksi Snowstorm lalu buka modal cari SNOMED CT */
    public function selectSnomed(string $doctorCode): void
    {
        $status = app(SnowstormService::class)->testConnection();
        if (!$status['success']) {
            $this->toastError('Server SNOMED CT tidak dapat dijangkau. Periksa koneksi jaringan.', 'Koneksi Terputus');
            return;
        }

        $this->selectedDoctorCode = $doctorCode;
        $this->snomedInitialSearch = DoctorMap::where('doctor_code', $doctorCode)->value('system_term') ?? '';
        $this->showSnomedSearchModal = true;
    }

    #[On('snomed-selected')]
    public function snomedSelected(string $system_code, string $system_term, string $system_display, string $category): void
    {
        if (!$this->selectedDoctorCode) {
            return;
        }

        DoctorMap::updateOrCreate(['doctor_code' => $this->selectedDoctorCode], ['system_code' => $system_code, 'system_term' => $system_term, 'system_display' => $system_display]);

        $this->showSnomedSearchModal = false;
        $this->reset(['selectedDoctorCode']);
        $this->toastSuccess('Mapping SNOMED CT berhasil disimpan', 'Sukses');
    }

    public function confirmDelete(string $doctorCode, string $doctorName): void
    {
        $this->deleteCode = $doctorCode;
        $this->deleteName = $doctorName;
        $this->showDeleteModal = true;
    }

    public function deleteMapping(): void
    {
        if (!$this->deleteCode) {
            return;
        }

        DoctorMap::where('doctor_code', $this->deleteCode)->delete();
        $this->showDeleteModal = false;
        $this->reset(['deleteCode', 'deleteName']);
        $this->toastSuccess('Mapping berhasil dihapus', 'Sukses');
    }

    public function cancelDelete(): void
    {
        $this->showDeleteModal = false;
        $this->reset(['deleteCode', 'deleteName']);
    }
};
?>

<div>
    <x-ui.page-header title="Mapping Dokter → SNOMED CT"
        subtitle="Hubungkan dokter dengan kode spesialistik SNOMED CT, UUID BPJS, dan IHS Satu Sehat">
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

    @php
        $mappedCount = collect($items->items())->filter(fn($i) => $i->snomed_code)->count();
    @endphp

    <x-organisms.data-panel>
        <x-slot:filter>
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-40">
                    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                        placeholder="Cari nama atau kode dokter..." clearable />
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
                <div
                    class="hidden sm:flex items-center gap-2 text-xs font-medium text-zinc-500 dark:text-primary-dark-400 whitespace-nowrap">
                    <span class="inline-block w-2 h-2 rounded-full bg-emerald-400"></span>
                    {{ $mappedCount }} ter-mapping / {{ $items->count() }} di halaman ini
                </div>
            </div>
        </x-slot:filter>

        <x-organisms.table>
            <x-slot:headings>
                <x-atoms.table-heading>Nama Dokter</x-atoms.table-heading>
                <x-atoms.table-heading class="w-32">Kode</x-atoms.table-heading>
                <x-atoms.table-heading>Mapping SNOMED CT</x-atoms.table-heading>
                <x-atoms.table-heading class="w-44">UUID BPJS</x-atoms.table-heading>
                <x-atoms.table-heading class="w-44">IHS Satu Sehat</x-atoms.table-heading>
                <x-atoms.table-heading align="center" class="w-48">Aksi</x-atoms.table-heading>
            </x-slot:headings>

            @forelse ($items as $item)
                <x-molecules.table-row :key="$item->kd_dokter">
                    <x-atoms.table-cell>
                        <p class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">{{ $item->nm_dokter }}
                        </p>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell nowrap>
                        <span
                            class="inline-block rounded-md bg-zinc-100 px-2 py-1 font-mono text-xs font-bold text-zinc-600 ring-1 ring-zinc-200 dark:bg-primary-dark-700 dark:text-primary-dark-300 dark:ring-primary-dark-600">
                            {{ $item->kd_dokter }}
                        </span>
                    </x-atoms.table-cell>
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

                    <x-atoms.table-cell :action="true" align="center" nowrap>
                        {{-- Satu Sehat --}}
                        @if (!$item->ihs_number)
                            <x-atoms.button size="sm" variant="ghost" icon="cloud-arrow-up"
                                tooltip="Kirim ke Satu Sehat" class="text-sky-600"
                                wire:click="openSsSend('{{ $item->pegawai?->no_ktp ?? '' }}', '{{ addslashes($item->nm_dokter) }}')" />
                        @endif

                        <span class="w-px h-4 bg-zinc-200 dark:bg-primary-dark-600 mx-0.5"></span>

                        {{-- SNOMED --}}
                        <x-atoms.button wire:click="selectSnomed('{{ $item->kd_dokter }}')" size="sm"
                            variant="ghost" icon="{{ $item->snomed_code ? 'pencil-square' : 'plus' }}"
                            tooltip="{{ $item->snomed_code ? 'Ubah SNOMED' : 'Petakan SNOMED' }}" />
                        @if ($item->snomed_code)
                            <x-atoms.button
                                wire:click="confirmDelete('{{ $item->kd_dokter }}', '{{ addslashes($item->nm_dokter) }}')"
                                size="sm" icon="trash" variant="ghost" tooltip="Hapus SNOMED"
                                class="text-red-500" />
                        @endif

                        <span class="w-px h-4 bg-zinc-200 dark:bg-primary-dark-600 mx-0.5"></span>

                        {{-- BPJS --}}
                        @if ($item->bpjs_uuid)
                            <x-atoms.button
                                wire:click="confirmBpjsDelete('{{ $item->kd_dokter }}', '{{ addslashes($item->nm_dokter) }}')"
                                size="sm" icon="trash" variant="ghost" tooltip="Hapus UUID BPJS"
                                class="text-red-500" />
                        @else
                            <x-atoms.button
                                wire:click="generateBpjsUuid('{{ $item->kd_dokter }}', '{{ addslashes($item->nm_dokter) }}')"
                                size="sm" icon="plus" variant="ghost" tooltip="Generate UUID BPJS" />
                        @endif
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @empty
                <x-molecules.table-row>
                    <x-atoms.table-cell colspan="6" align="center" class="py-16">
                        <div class="flex flex-col items-center gap-3">
                            <div
                                class="flex h-14 w-14 items-center justify-center rounded-2xl bg-zinc-100 dark:bg-primary-dark-700">
                                <flux:icon name="user-circle"
                                    class="h-7 w-7 text-zinc-300 dark:text-primary-dark-500" />
                            </div>
                            <p class="text-sm font-semibold text-zinc-500 dark:text-primary-dark-400">Tidak ada data
                                dokter</p>
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

    {{-- Modal: Kirim Satu Sehat (single) --}}
    <x-organisms.modal wire:model="showSsSendModal" maxWidth="md" title="Kirim ke Satu Sehat">
        <div class="space-y-5">
            <div class="flex items-center gap-4">
                <div
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-sky-100 dark:bg-sky-900/30">
                    <flux:icon name="cloud-arrow-up" class="h-6 w-6 text-sky-600 dark:text-sky-400" />
                </div>
                <div>
                    <flux:text class="mt-0.5 font-medium">{{ $ssSendName }}</flux:text>
                </div>
            </div>

            @if (!$ssSendDone)
                <div
                    class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-primary-dark-700 dark:bg-primary-dark-900/40">
                    <p class="text-xs text-zinc-500">Kode Dokter (NIK): <span
                            class="font-mono font-semibold text-zinc-700 dark:text-primary-dark-200">{{ $ssSendNik }}</span>
                    </p>
                    <p class="mt-1 text-xs text-zinc-400 dark:text-primary-dark-500">Akan mencari IHS Number berdasarkan
                        kode ini di Satu Sehat dan menyimpan hasilnya.</p>
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

    {{-- Modal: Sync Satu Sehat (bulk) --}}
    <x-organisms.modal wire:model="showSyncSsModal" maxWidth="sm" title="Sync Semua Dokter ke Satu Sehat">
        <div class="space-y-6">
            <div class="flex items-center gap-4">
                <div
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-sky-100 dark:bg-sky-900/30">
                    <flux:icon name="cloud-arrow-up" class="h-6 w-6 text-sky-600 dark:text-sky-400" />
                </div>
                <div>

                    <flux:text class="mt-0.5">Proses dijalankan di background queue.</flux:text>
                </div>
            </div>
            <flux:text class="text-sm text-zinc-600 dark:text-primary-dark-300">
                Akan mencari dan menyimpan IHS Number untuk semua dokter yang belum terdaftar di Satu Sehat.
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

    {{-- Modal Pencarian SNOMED CT --}}
    <x-organisms.modal wire:model="showSnomedSearchModal" maxWidth="4xl" title="Pilih Kode SNOMED CT">
        <div class="space-y-4">
            <div>

                <flux:text class="mt-0.5">Klik baris untuk memilih kode spesialistik dokter.</flux:text>
            </div>
            <livewire:components.snomed-search defaultTag="occupation" :initialSearch="$snomedInitialSearch" :key="'snomed-doctor-' . ($selectedDoctorCode ?? 'new')" />

            <x-slot:footer>
                <div class="flex justify-end">
                    <x-atoms.button wire:click="$set('showSnomedSearchModal', false)"
                        variant="ghost">Tutup</x-atoms.button>
                </div>
            </x-slot:footer>
        </div>
    </x-organisms.modal>

    {{-- Modal: Sync BPJS --}}
    <x-organisms.modal wire:model="showSyncModal" maxWidth="sm" title="Sync UUID BPJS Dokter">
        <div class="space-y-6">
            <div class="flex items-center gap-4">
                <div
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-blue-100 dark:bg-blue-900/30">
                    <flux:icon name="arrow-path" class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                </div>
                <div>

                    <flux:text class="mt-0.5">Proses dijalankan di background queue.</flux:text>
                </div>
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

    {{-- Modal: Hapus UUID BPJS --}}
    <x-organisms.modal wire:model="showBpjsDeleteModal" maxWidth="sm" title="Hapus UUID BPJS">
        <div class="space-y-6">
            <div class="flex items-center gap-4">
                <div
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-red-100 dark:bg-red-900/30">
                    <flux:icon name="exclamation-triangle" class="h-6 w-6 text-red-600 dark:text-red-400" />
                </div>
                <div>

                    <flux:text class="mt-0.5">Tindakan ini tidak dapat dibatalkan.</flux:text>
                </div>
            </div>
            <div
                class="space-y-2 rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-primary-dark-700 dark:bg-primary-dark-900/40">
                <p class="text-xs font-medium text-zinc-500">Kode: <span
                        class="font-mono font-bold text-zinc-700 dark:text-primary-dark-200">{{ $deleteBpjsCode }}</span>
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

    {{-- Modal Konfirmasi Hapus SNOMED --}}
    <x-organisms.modal wire:model="showDeleteModal" maxWidth="sm" title="Hapus Mapping SNOMED">
        <div class="space-y-6">
            <div class="flex items-center gap-4">
                <div
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-red-100 dark:bg-red-900/30">
                    <flux:icon name="exclamation-triangle" class="h-6 w-6 text-red-600 dark:text-red-400" />
                </div>
                <div>

                    <flux:text class="mt-0.5">Tindakan ini tidak dapat dibatalkan.</flux:text>
                </div>
            </div>
            <div
                class="space-y-2 rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-primary-dark-700 dark:bg-primary-dark-900/40">
                <div class="flex items-center gap-3">
                    <span class="w-10 shrink-0 text-xs font-medium text-zinc-400">Kode</span>
                    <span
                        class="font-mono text-sm font-bold text-primary-600 dark:text-primary-400">{{ $deleteCode }}</span>
                </div>
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 w-10 shrink-0 text-xs font-medium text-zinc-400">Nama</span>
                    <span class="text-sm text-zinc-700 dark:text-primary-dark-300">{{ $deleteName }}</span>
                </div>
            </div>

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-atoms.button wire:click="cancelDelete" variant="ghost">Batal</x-atoms.button>
                    <x-atoms.button wire:click="deleteMapping" variant="danger">Hapus Mapping</x-atoms.button>
                </div>
            </x-slot:footer>
        </div>
    </x-organisms.modal>
</div>
