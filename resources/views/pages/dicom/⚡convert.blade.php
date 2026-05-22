<?php

use App\Models\Simrs\Pasien;
use App\Models\Simrs\PermintaanRadiologi;
use App\Models\Simrs\PermintaanPemeriksaanRadiologi;
use App\Services\Dicom\DicomConvertService;
use App\Services\Dicom\OrthancService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new #[Layout('layouts::app')] #[Title('DICOM — Convert Image')] class extends Component {
    use WithFileUploads;

    public bool $isConfigured = false;

    // Pasien
    public string $patientId = '';
    public string $patientName = '';
    public string $birthDate = '';
    public string $patientSex = '';
    public string $patientNoRm = '';

    // Metadata
    public string $studyDescription = '';
    public string $seriesDescription = '';
    public string $modality = 'OT';
    public string $studyDate = '';
    public string $noorder = '';
    public string $noRawat = '';
    public string $diagnosisInfo = '';

    // Upload
    public array $images = [];

    // Hasil
    public array $convertResult = [];
    public bool $submitted = false;
    public bool $confirmDuplicate = false;

    // Modal pasien
    public bool $showPatientModal = false;
    public string $patientSearch = '';

    // Modal order
    public bool $showOrderModal = false;
    public string $orderSearch = '';
    public string $orderStartDate = '';
    public string $orderEndDate = '';

    public function mount(): void
    {
        $this->isConfigured = app(OrthancService::class)->isConfigured();
        $this->studyDate = now()->format('Y-m-d');
        $this->orderStartDate = now()->subDays(3)->format('Y-m-d');
        $this->orderEndDate = now()->format('Y-m-d');
    }

    public function openPatientModal(): void
    {
        $this->patientSearch = '';
        $this->showPatientModal = true;
    }

    public function selectPatient(string $noRkm): void
    {
        try {
            $p = Pasien::findOrFail($noRkm);
            $this->patientNoRm = $p->no_rkm_medis;
            $this->patientId = $p->no_rkm_medis;
            $this->patientName = $p->nm_pasien;
            $this->birthDate = $p->tgl_lahir?->format('Y-m-d') ?? '';
            $this->patientSex = match ($p->jk) {
                'L' => 'M',
                'P' => 'F',
                default => 'O',
            };
            $this->showPatientModal = false;
        } catch (\Throwable) {
            $this->dispatch('toast', type: 'error', message: 'Pasien tidak ditemukan.');
        }
    }

    public function openOrderModal(): void
    {
        $this->orderSearch = '';
        $this->showOrderModal = true;
    }

    public function selectOrder(string $noorder): void
    {
        try {
            $order = PermintaanRadiologi::with(['regPeriksa.pasien'])
                ->where('noorder', $noorder)
                ->firstOrFail();
            $pasien = $order->regPeriksa?->pasien;

            if ($pasien && empty($this->patientId)) {
                $this->patientNoRm = $pasien->no_rkm_medis;
                $this->patientId = $pasien->no_rkm_medis;
                $this->patientName = $pasien->nm_pasien;
                $this->birthDate = $pasien->tgl_lahir?->format('Y-m-d') ?? '';
                $this->patientSex = match ($pasien->jk) {
                    'L' => 'M',
                    'P' => 'F',
                    default => 'O',
                };
            }

            $items = PermintaanPemeriksaanRadiologi::with('jenisPemeriksaan')->where('noorder', $noorder)->get();
            
            // Ambil nama dari mapping RadMap jika ada
            $mappedItems = \App\Models\Mapping\RadMap::whereIn('local_code', $items->pluck('kd_jenis_prw'))->get();
            $studyDesc = $mappedItems->map(fn($m) => $m->system_display ?: $m->system_term)->filter()->unique()->implode(', ');

            if (empty($studyDesc)) {
                $studyDesc = $items->map(fn($i) => $i->jenisPemeriksaan?->nm_perawatan)->filter()->unique()->implode(', ');
            }

            $this->noorder = $order->noorder;
            $this->noRawat = $order->no_rawat;
            $this->studyDescription = $studyDesc ?: $order->diagnosa_klinis ?? '';
            $this->diagnosisInfo = $order->diagnosa_klinis ?? '';
            $this->studyDate = $order->tgl_permintaan?->format('Y-m-d') ?? now()->format('Y-m-d');

            if ($items->isNotEmpty()) {
                $first = $items->first();
                $this->modality = $this->inferModality($first->kd_jenis_prw ?? '', $first->jenisPemeriksaan?->nm_perawatan ?? '');
            }

            $this->showOrderModal = false;
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal memuat order: ' . $e->getMessage());
        }
    }

    public function submit(): void
    {
        $this->validate([
            'patientId' => 'required|string|max:50',
            'patientName' => 'required|string|max:255',
            'studyDescription' => 'required|string|max:255',
            'modality' => 'required|string',
            'studyDate' => 'required|date',
            'images' => 'required|array|min:1',
            'images.*' => 'image|max:10240',
        ]);

        $tags = $this->prepareTags();
        $this->convertResult = app(DicomConvertService::class)->convertImages($this->images, $tags, $this->noRawat ?: null, $this->noorder ?: null);

        if ($this->convertResult['duplicate'] ?? false) {
            $this->confirmDuplicate = true;
            return;
        }

        $this->submitted = true;
        $this->dispatch('toast', type: $this->convertResult['success'] ? 'success' : 'error', message: $this->convertResult['message']);
    }

    public function confirmUpdate(): void
    {
        $tags = $this->prepareTags();
        $this->convertResult = app(DicomConvertService::class)->convertImages(
            $this->images,
            $tags,
            $this->noRawat ?: null,
            $this->noorder ?: null,
            force: true
        );

        $this->confirmDuplicate = false;
        $this->submitted = true;
        $this->dispatch('toast', type: $this->convertResult['success'] ? 'success' : 'error', message: $this->convertResult['message']);
    }

    private function prepareTags(): array
    {
        $tags = [
            'PatientID' => $this->patientId,
            'PatientName' => strtoupper(str_replace(' ', '^', $this->patientName)),
            'PatientBirthDate' => $this->birthDate ? \Carbon\Carbon::parse($this->birthDate)->format('Ymd') : '',
            'PatientSex' => $this->patientSex,
            'Modality' => $this->modality,
            'StudyDescription' => $this->studyDescription,
            'SeriesDescription' => $this->seriesDescription,
            'StudyDate' => \Carbon\Carbon::parse($this->studyDate)->format('Ymd'),
        ];

        if ($this->noorder) {
            $tags['AccessionNumber'] = $this->noorder;
        }

        return $tags;
    }

    public function resetForm(): void
    {
        $this->patientId = $this->patientName = $this->birthDate = $this->patientSex = $this->patientNoRm = '';
        $this->studyDescription = $this->seriesDescription = $this->noorder = $this->noRawat = $this->diagnosisInfo = '';
        $this->modality = 'OT';
        $this->studyDate = now()->format('Y-m-d');
        $this->images = [];
        $this->convertResult = [];
        $this->submitted = false;
        $this->resetValidation();
    }

    private function inferModality(string $kd, string $nm = ''): string
    {
        // 1. Prioritas Mapping Tabel
        if ($kd) {
            $mapped = \App\Models\Simrs\MappingRadiologiModality::where('kd_jenis_prw', $kd)->first();
            if ($mapped) {
                return $mapped->modality_code;
            }
        }

        // 2. Fallback Heuristik
        $u = strtoupper($kd . ' ' . $nm);
        return match (true) {
            str_contains($u, 'CT') => 'CT',
            str_contains($u, 'MR') || str_contains($u, 'MRI') => 'MR',
            str_contains($u, 'USG') || str_contains($u, 'US') => 'US',
            str_contains($u, 'MAMMO') || str_contains($u, 'MG') => 'MG',
            str_contains($u, 'DX') || str_contains($u, 'DR') || str_contains($u, 'XR') || str_contains($u, 'FOTO') || str_contains($u, 'RONTGEN') => 'DX',
            str_contains($u, 'CR') => 'CR',
            default => 'OT',
        };
    }

    public function with(): array
    {
        $patients = collect();
        if ($this->showPatientModal && strlen($this->patientSearch) >= 2) {
            try {
                $patients = Pasien::search($this->patientSearch)
                    ->select(['no_rkm_medis', 'nm_pasien', 'tgl_lahir', 'jk'])
                    ->limit(20)
                    ->get();
            } catch (\Throwable) {
            }
        }

        $orders = collect();
        if ($this->showOrderModal) {
            try {
                $orders = PermintaanRadiologi::with(['regPeriksa.pasien'])
                    ->leftJoin('permintaan_pemeriksaan_radiologi as ppr', 'permintaan_radiologi.noorder', '=', 'ppr.noorder')
                    ->leftJoin('jns_perawatan_radiologi as jpr', 'ppr.kd_jenis_prw', '=', 'jpr.kd_jenis_prw')
                    ->select(['permintaan_radiologi.noorder', 'permintaan_radiologi.no_rawat', 'permintaan_radiologi.tgl_permintaan', 'permintaan_radiologi.status', 'permintaan_radiologi.diagnosa_klinis', DB::raw('GROUP_CONCAT(jpr.nm_perawatan SEPARATOR ", ") as nama_pemeriksaan')])
                    ->when($this->orderStartDate, fn($q) => $q->whereDate('permintaan_radiologi.tgl_permintaan', '>=', $this->orderStartDate))
                    ->when($this->orderEndDate, fn($q) => $q->whereDate('permintaan_radiologi.tgl_permintaan', '<=', $this->orderEndDate))
                    ->when($this->orderSearch, fn($q) => $q->where(fn($q) => $q->where('permintaan_radiologi.noorder', 'like', "%{$this->orderSearch}%")->orWhere('jpr.nm_perawatan', 'like', "%{$this->orderSearch}%")))
                    ->groupBy('permintaan_radiologi.noorder', 'permintaan_radiologi.no_rawat', 'permintaan_radiologi.tgl_permintaan', 'permintaan_radiologi.status', 'permintaan_radiologi.diagnosa_klinis')
                    ->orderByDesc('permintaan_radiologi.tgl_permintaan')
                    ->limit(50)
                    ->get();
            } catch (\Throwable) {
            }
        }

        return compact('patients', 'orders');
    }
}; ?>

<div>
    <x-ui.page-header title="Convert Image ke DICOM"
        subtitle="Konversi gambar JPG/PNG menjadi instance DICOM dan simpan ke Orthanc." />

    @if (!$isConfigured)
        <x-ui.empty-state icon="arrow-path" title="Orthanc Belum Dikonfigurasi"
            description="Atur koneksi ke server Orthanc di halaman Konfigurasi → DICOM.">
            <x-atoms.button icon="cog-6-tooth" href="{{ route('configuration.connectivity', ['tab' => 'dicom']) }}"
                wire:navigate>Buka Konfigurasi</x-atoms.button>
        </x-ui.empty-state>
    @elseif ($submitted && ($convertResult['success'] ?? false))
        {{-- Hasil konversi --}}
        <x-organisms.card>
            <div class="max-w-xl mx-auto space-y-5">
                <div class="flex items-center gap-3">
                    <div
                        class="w-11 h-11 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center shrink-0">
                        <flux:icon name="check-circle" class="size-6 text-green-600 dark:text-green-400" />
                    </div>
                    <div>
                        <p class="font-semibold text-zinc-900 dark:text-primary-dark-100">Konversi Berhasil</p>
                        <p class="text-sm text-zinc-500 dark:text-primary-dark-400">{{ $convertResult['message'] }}</p>
                    </div>
                </div>

                <div
                    class="rounded-xl border border-zinc-200 dark:border-primary-dark-700 divide-y divide-zinc-100 dark:divide-primary-dark-800 overflow-hidden">
                    @foreach ($convertResult['instances'] as $inst)
                        <div class="flex items-center gap-3 px-4 py-3 bg-white dark:bg-primary-dark-800">
                            <div
                                class="w-7 h-7 rounded-full bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center shrink-0">
                                <span
                                    class="text-xs font-bold text-blue-600 dark:text-blue-400">#{{ $inst['index'] }}</span>
                            </div>
                            <span
                                class="flex-1 text-sm text-zinc-700 dark:text-primary-dark-200 truncate">{{ $inst['file_name'] }}</span>
                            <span
                                class="font-mono text-xs text-zinc-400 dark:text-primary-dark-500 truncate max-w-[160px]">{{ $inst['instance_id'] }}</span>
                        </div>
                    @endforeach
                </div>

                @if (!empty($convertResult['errors']))
                    <div
                        class="rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 p-4 space-y-1">
                        @foreach ($convertResult['errors'] as $err)
                            <p class="text-sm text-red-600 dark:text-red-400">{{ $err }}</p>
                        @endforeach
                    </div>
                @endif

                <div class="flex gap-3 pt-2 border-t border-zinc-100 dark:border-primary-dark-800">
                    <x-atoms.button icon="arrow-path" wire:click="resetForm">Konversi Gambar Lain</x-atoms.button>
                    <x-atoms.button icon="eye" variant="primary" href="{{ route('dicom.viewer') }}" wire:navigate>
                        Buka Viewer PACS
                    </x-atoms.button>
                </div>
            </div>
        </x-organisms.card>
    @else
        {{-- Form utama: 2 kolom --}}
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6 items-start">

            {{-- Kiri: Form (3/5) --}}
            <div class="lg:col-span-3 space-y-4">

                {{-- Panel: Data Pasien --}}
                <x-organisms.card title="Data Pasien">
                    <div class="space-y-4">
                        <x-molecules.patient-selector-card :patientName="$patientName" :patientNoRm="$patientNoRm" :birthDate="$birthDate"
                            :patientSex="$patientSex" onPickClick="openPatientModal" onChangeClick="openPatientModal" />

                        {{-- Collapsible manual input --}}
                        <div x-data="{ manualInputOpen: {{ $patientId ? 'false' : 'true' }} }">
                            <x-atoms.button type="button" variant="ghost"
                                class="flex items-center gap-1 text-xs text-zinc-400 dark:text-primary-dark-500 hover:text-zinc-600 dark:hover:text-primary-dark-300 transition-colors"
                                x-on:click="manualInputOpen = !manualInputOpen">
                                <span class="transition-transform duration-200"
                                    x-bind:class="manualInputOpen && 'rotate-90'">
                                    <flux:icon name="chevron-right" class="size-3.5" />
                                </span>
                                <span
                                    x-text="manualInputOpen ? 'Sembunyikan isian manual' : 'Isi data pasien manual'"></span>
                            </x-atoms.button>

                            <div x-show="manualInputOpen" x-collapse class="mt-4">
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="col-span-2">
                                        <flux:input wire:model="patientId" label="No. RM / Patient ID *"
                                            placeholder="000001" />
                                        @error('patientId')
                                            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="col-span-2">
                                        <flux:input wire:model="patientName" label="Nama Pasien *"
                                            placeholder="BUDI SANTOSO" />
                                        @error('patientName')
                                            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <flux:input type="date" wire:model="birthDate" label="Tgl Lahir" />
                                    <flux:select wire:model="patientSex" label="Jenis Kelamin">
                                        <flux:select.option value="">-- Pilih --</flux:select.option>
                                        <flux:select.option value="M">Laki-laki</flux:select.option>
                                        <flux:select.option value="F">Perempuan</flux:select.option>
                                        <flux:select.option value="O">Lainnya</flux:select.option>
                                    </flux:select>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-organisms.card>

                {{-- Panel: Metadata DICOM --}}
                <x-organisms.data-panel title="Metadata DICOM" subtitle="Isi metadata atau ambil dari order radiologi.">
                    <x-slot:action>
                        <x-atoms.button size="xs" icon="clipboard-document-list" wire:click="openOrderModal">
                            Dari Order Radiologi
                        </x-atoms.button>
                    </x-slot:action>

                    <div class="px-5 py-4 space-y-4">
                        {{-- Badge order terpilih --}}
                        @if ($noorder)
                            <div
                                class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs bg-teal-50 dark:bg-teal-900/20 border border-teal-200 dark:border-teal-800">
                                <flux:icon name="clipboard-document-check"
                                    class="size-4 text-teal-600 dark:text-teal-400 shrink-0" />
                                <span
                                    class="font-mono font-semibold text-teal-800 dark:text-teal-300">{{ $noorder }}</span>
                                @if ($noRawat)
                                    <span class="text-teal-500 dark:text-teal-400">· No. Rawat:
                                        {{ $noRawat }}</span>
                                @endif
                                <x-atoms.button type="button" wire:click="$set('noorder', '')"
                                    class="ml-auto text-teal-400 hover:text-teal-700 dark:hover:text-teal-200 transition-colors">
                                    <flux:icon name="x-mark" class="size-3.5" />
                                </x-atoms.button>
                            </div>
                        @endif

                        <flux:input wire:model="studyDescription" label="Study Description *"
                            placeholder="Foto Thorax PA" />
                        @error('studyDescription')
                            <p class="text-xs text-red-500 -mt-2">{{ $message }}</p>
                        @enderror

                        <flux:input wire:model="seriesDescription" label="Series Description"
                            placeholder="Series 1 (opsional)" />

                        @if ($diagnosisInfo)
                            <div
                                class="px-3 py-2 rounded-lg text-xs bg-zinc-50 dark:bg-primary-dark-800/60 border border-zinc-200 dark:border-primary-dark-700 text-zinc-500 dark:text-primary-dark-400">
                                <span class="font-medium">Diagnosis klinis:</span> {{ $diagnosisInfo }}
                            </div>
                        @endif

                        <div class="grid grid-cols-2 gap-3">
                            <flux:select wire:model="modality" label="Modality *">
                                @foreach (['CT', 'MR', 'DR', 'CR', 'US', 'PT', 'NM', 'DX', 'MG', 'OT'] as $m)
                                    <flux:select.option value="{{ $m }}">{{ $m }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                            <div>
                                <flux:input type="date" wire:model="studyDate" label="Tanggal Studi *" />
                                @error('studyDate')
                                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </x-organisms.data-panel>

            </div>

            {{-- Kanan: Upload + Ringkasan (2/5) --}}
            <div class="lg:col-span-2 space-y-4">

                {{-- UI Component: upload-panel (reusable) --}}
                <x-ui.upload-panel title="Upload Gambar" subtitle="Drag & drop atau klik untuk memilih file."
                    wireModel="images" accept="image/jpeg,image/png" :maxSizeMb="10" />

                {{-- Ringkasan + Submit --}}
                <x-organisms.card>
                    <div class="space-y-3 text-sm">
                        <h3 class="font-semibold text-zinc-700 dark:text-primary-dark-300 mb-3">Ringkasan</h3>
                        <div class="flex justify-between gap-2">
                            <span class="text-zinc-500 dark:text-primary-dark-400">Pasien</span>
                            <span
                                class="font-medium text-zinc-900 dark:text-primary-dark-100 text-right truncate max-w-[180px]">{{ $patientName ?: '—' }}</span>
                        </div>
                        <div class="flex justify-between gap-2">
                            <span class="text-zinc-500 dark:text-primary-dark-400">Modality</span>
                            <flux:badge color="blue" size="sm">{{ $modality }}</flux:badge>
                        </div>
                        <div class="flex justify-between gap-2">
                            <span class="text-zinc-500 dark:text-primary-dark-400 shrink-0">Studi</span>
                            <span
                                class="text-zinc-700 dark:text-primary-dark-200 text-right truncate max-w-[180px]">{{ $studyDescription ?: '—' }}</span>
                        </div>
                        @if ($noorder)
                            <div class="flex justify-between gap-2">
                                <span class="text-zinc-500 dark:text-primary-dark-400">No. Order</span>
                                <span
                                    class="font-mono text-zinc-700 dark:text-primary-dark-200">{{ $noorder }}</span>
                            </div>
                        @endif
                        <div
                            class="flex justify-between gap-2 pt-3 border-t border-zinc-100 dark:border-primary-dark-800">
                            <span class="text-zinc-500 dark:text-primary-dark-400">File dipilih</span>
                            <span class="font-bold text-zinc-900 dark:text-primary-dark-100">
                                {{ count($images) }} <span class="font-normal text-zinc-500">gambar</span>
                            </span>
                        </div>

                        <x-atoms.button variant="primary" icon="arrow-up-tray" class="w-full" wire:click="submit">
                            Konversi &amp; Kirim ke Orthanc
                        </x-atoms.button>

                        @if ($submitted && !($convertResult['success'] ?? false))
                            <p class="text-xs text-red-500 text-center">
                                {{ $convertResult['message'] ?? 'Terjadi kesalahan.' }}</p>
                        @endif
                    </div>
                </x-organisms.card>

            </div>
        </div>
    @endif

    {{-- Modal: Pilih Pasien --}}
    <x-organisms.modal wire:model="showPatientModal" title="Pilih Pasien"
        description="Cari berdasarkan No. RM atau nama pasien." maxWidth="xl">
        <div class="space-y-4">
            <flux:input wire:model.live.debounce.300ms="patientSearch" placeholder="Cari No. RM atau nama pasien..."
                icon="magnifying-glass" />

            @if (strlen($patientSearch) < 2)
                <x-ui.empty-state icon="magnifying-glass" title="Ketik untuk mencari"
                    description="Masukkan minimal 2 karakter No. RM atau nama pasien." class="py-6" />
            @elseif ($patients->isEmpty())
                <x-ui.empty-state icon="user" title="Pasien tidak ditemukan"
                    description="Coba gunakan kata kunci yang berbeda." class="py-6" />
            @else
                <div
                    class="max-h-72 overflow-y-auto rounded-xl border
                            border-zinc-200 dark:border-primary-dark-700
                            divide-y divide-zinc-100 dark:divide-primary-dark-800">
                    @foreach ($patients as $p)
                        <x-atoms.button type="button" variant="ghost"
                            wire:click="selectPatient('{{ $p->no_rkm_medis }}')"
                            class="w-full flex items-center gap-3 px-4 py-3 text-left
                                   hover:bg-zinc-50 dark:hover:bg-primary-dark-800 transition-colors group">
                            <div
                                class="w-9 h-9 rounded-full shrink-0 flex items-center justify-center
                                        bg-blue-100 dark:bg-blue-900/40
                                        text-sm font-bold text-blue-600 dark:text-blue-400">
                                {{ strtoupper(substr($p->nm_pasien, 0, 1)) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p
                                    class="text-sm font-semibold text-zinc-900 dark:text-primary-dark-100
                                          truncate group-hover:text-blue-600 dark:group-hover:text-blue-400
                                          transition-colors">
                                    {{ $p->nm_pasien }}
                                </p>
                                <p class="text-xs text-zinc-500 dark:text-primary-dark-400 font-mono mt-0.5">
                                    {{ $p->no_rkm_medis }}
                                    @if ($p->tgl_lahir)
                                        · {{ $p->tgl_lahir->format('d/m/Y') }}
                                    @endif
                                    · {{ $p->jk === 'L' ? 'Laki-laki' : 'Perempuan' }}
                                </p>
                            </div>
                            <flux:icon name="chevron-right"
                                class="size-4 text-zinc-300 dark:text-primary-dark-600 shrink-0
                                       group-hover:text-blue-400 transition-colors" />
                        </x-atoms.button>
                    @endforeach
                </div>
            @endif
        </div>

        <x-slot:footer>
            <x-atoms.button variant="ghost" wire:click="$set('showPatientModal', false)">Tutup</x-atoms.button>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal: Pilih Order Radiologi --}}

    <x-organisms.modal wire:model="showOrderModal" title="Pilih Permintaan Radiologi"
        description="Pilih order untuk mengisi metadata secara otomatis." maxWidth="2xl">
        <div class="space-y-4">
            <div class="flex flex-col sm:flex-row gap-3 items-center">
                <div class="flex items-center gap-2 shrink-0">
                    <div class="w-32">
                        <flux:input type="date" wire:model.live="orderStartDate" />
                    </div>
                    <span class="text-zinc-400">s/d</span>
                    <div class="w-32">
                        <flux:input type="date" wire:model.live="orderEndDate" />
                    </div>
                </div>
                <div class="flex-1 w-full">
                    <flux:input wire:model.live.debounce.300ms="orderSearch"
                        placeholder="Cari no. order atau nama pemeriksaan..." icon="magnifying-glass" />
                </div>
            </div>

            @if ($orders->isEmpty())
                <x-ui.empty-state icon="clipboard-document-list" title="Tidak ada permintaan radiologi"
                    description="Tidak ada data order pada rentang tanggal yang dipilih." class="py-6" />
            @else
                <div
                    class="max-h-96 overflow-y-auto rounded-xl border
                            border-zinc-200 dark:border-primary-dark-700
                            divide-y divide-zinc-100 dark:divide-primary-dark-800">
                    @foreach ($orders as $o)
                        @php
                            $nmPasien = $o->regPeriksa?->pasien?->nm_pasien ?? '-';
                            $noRm = $o->regPeriksa?->pasien?->no_rkm_medis ?? '-';
                            $statusColor = match ($o->status) {
                                'Sudah' => 'green',
                                'Belum' => 'yellow',
                                default => 'zinc',
                            };
                        @endphp
                        <x-atoms.button variant="ghost" type="button"
                            wire:click="selectOrder('{{ $o->noorder }}')"
                            class="w-full flex items-start gap-3 px-4 py-3 text-left
                                   hover:bg-zinc-50 dark:hover:bg-primary-dark-800 transition-colors group">
                            <div class="shrink-0 pt-0.5">
                                <flux:badge color="blue" size="sm">
                                    {{ $o->tgl_permintaan?->format('d/m') ?? '-' }}
                                </flux:badge>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p
                                    class="text-sm font-mono font-semibold text-zinc-800 dark:text-primary-dark-100
                                          group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                                    {{ $o->noorder }}
                                </p>
                                <p class="text-sm text-zinc-600 dark:text-primary-dark-300 truncate mt-0.5">
                                    {{ $o->nama_pemeriksaan ?: '—' }}
                                </p>
                                <p class="text-xs text-zinc-400 dark:text-primary-dark-500 mt-0.5">
                                    {{ $nmPasien }}
                                    · <span class="font-mono">{{ $noRm }}</span>
                                    @if ($o->diagnosa_klinis)
                                        · {{ Str::limit($o->diagnosa_klinis, 50) }}
                                    @endif
                                </p>
                            </div>
                            <flux:badge color="{{ $statusColor }}" size="sm" class="shrink-0 mt-0.5">
                                {{ $o->status ?? '-' }}
                            </flux:badge>
                        </x-atoms.button>
                    @endforeach
                </div>
            @endif
        </div>

        <x-slot:footer>
            <x-atoms.button variant="ghost" wire:click="$set('showOrderModal', false)">Tutup</x-atoms.button>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal: Konfirmasi Duplikasi --}}
    <x-organisms.modal wire:model="confirmDuplicate" title="Konfirmasi Pembaruan Data" maxWidth="md">
        <div class="space-y-4">
            <div class="p-4 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
                <div class="flex gap-3">
                    <flux:icon name="exclamation-triangle" class="size-5 text-amber-600 dark:text-amber-400 shrink-0" />
                    <div>
                        <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">Data Sudah Ada</p>
                        <p class="text-xs text-amber-700 dark:text-amber-400 mt-1">
                            Order <b>{{ $noorder }}</b> sudah terdaftar di worklist dengan Study ID lain.
                            Apakah Anda ingin memperbarui data tersebut dengan study baru ini?
                        </p>
                    </div>
                </div>
            </div>

            <div class="text-sm text-zinc-600 dark:text-primary-dark-400">
                Aksi ini akan menimpa referensi Study ID lama di database lokal agar merujuk ke hasil konversi terbaru.
            </div>
        </div>

        <x-slot:footer>
            <div class="flex justify-end gap-3">
                <x-atoms.button variant="ghost" wire:click="$set('confirmDuplicate', false)">Batal</x-atoms.button>
                <x-atoms.button variant="primary" color="amber" wire:click="confirmUpdate" wire:loading.attr="disabled">
                    <span wire:loading.remove>Ya, Timpa Data</span>
                    <span wire:loading>Memproses...</span>
                </x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>
</div>
