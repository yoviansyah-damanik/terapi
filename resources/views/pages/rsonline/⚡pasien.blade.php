<?php

use App\Services\RsOnline\RsOnlineService;
use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts::app', ['title' => 'RS Online'])] class extends Component {
    public string $activeTab = 'pasien';

    // ---- Data list ----
    public array $pasienList = [];
    public array $diagnosisList = [];
    public ?string $listError = null;

    // ---- Form Pasien ----
    public string $formMode = ''; // 'add' | 'edit' | ''
    public array $form = [];
    public ?array $formResult = null;

    // ---- Form Diagnosis ----
    public string $diagFormMode = '';
    public array $diagForm = [];
    public ?array $diagFormResult = null;

    /** Default field pasien */
    private function defaultPasienForm(): array
    {
        return [
            'noc' => '',
            'nomr' => '',
            'initial' => '',
            'nama_lengkap' => '',
            'tglmasuk' => '',
            'gender' => '',
            'birthdate' => '',
            'kewarganegaraan' => '1',
            'sumber_penularan' => '',
            'kecamatan' => '',
            'tglkeluar' => '0000-00-00',
            'status_keluar' => '0',
            'tgl_lapor' => '',
            'status_rawat' => '',
            'status_isolasi' => '',
            'email' => '-',
            'notelp' => '-',
            'sebab_kematian' => '',
        ];
    }

    /** Default field diagnosis */
    private function defaultDiagForm(): array
    {
        return [
            'nomr' => '',
            'kode_diagnosa' => '',
            'diagnosa' => '',
            'tglmasuk' => '',
        ];
    }

    public function mount(): void
    {
        $this->form = $this->defaultPasienForm();
        $this->diagForm = $this->defaultDiagForm();
    }

    /** Ganti tab aktif */
    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->listError = null;
    }

    // =================== GET ===================

    /** Ambil data pasien */
    public function loadPasien(): void
    {
        $this->listError = null;
        $service = new RsOnlineService();

        if (!$service->isConfigured()) {
            $this->listError = 'Konfigurasi RS Online belum lengkap.';
            return;
        }

        $result = $service->getPasien();
        $this->pasienList = $result['success'] ? (array) ($result['data'] ?? []) : [];
        if (!$result['success']) {
            $this->listError = $result['message'];
        }
    }

    /** Ambil data diagnosis */
    public function loadDiagnosis(): void
    {
        $this->listError = null;
        $service = new RsOnlineService();

        if (!$service->isConfigured()) {
            $this->listError = 'Konfigurasi RS Online belum lengkap.';
            return;
        }

        $result = $service->getDiagnosis();
        $this->diagnosisList = $result['success'] ? (array) ($result['data'] ?? []) : [];
        if (!$result['success']) {
            $this->listError = $result['message'];
        }
    }

    // =================== Pasien CRUD ===================

    public function openAddPasien(): void
    {
        $this->form = $this->defaultPasienForm();
        $this->form['tgl_lapor'] = now()->format('Y-m-d H:i:s');
        $this->formMode = 'add';
        $this->formResult = null;
    }

    public function openEditPasien(array $row): void
    {
        $this->form = array_merge($this->defaultPasienForm(), $row);
        $this->formMode = 'edit';
        $this->formResult = null;
    }

    public function submitPasien(): void
    {
        $this->validate(
            [
                'form.nomr' => 'required',
                'form.nama_lengkap' => 'required',
                'form.tglmasuk' => 'required|date',
            ],
            [
                'form.nomr.required' => 'No MR wajib diisi.',
                'form.nama_lengkap.required' => 'Nama lengkap wajib diisi.',
                'form.tglmasuk.required' => 'Tanggal masuk wajib diisi.',
            ],
        );

        $service = new RsOnlineService();
        $result = $this->formMode === 'edit' ? $service->updatePasien($this->form) : $service->kirimPasien($this->form);

        $this->formResult = $result;

        if ($result['success']) {
            $this->formMode = '';
            $this->loadPasien();
            $this->dispatch('toast', type: 'success', message: 'Data pasien berhasil ' . ($this->formMode === 'edit' ? 'diperbarui.' : 'dikirim.'));
        } else {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $result['message']);
        }
    }

    public function deletePasien(string $nomr, string $noc): void
    {
        $service = new RsOnlineService();
        $result = $service->deletePasien(['nomr' => $nomr, 'noc' => $noc]);

        if ($result['success']) {
            $this->loadPasien();
            $this->dispatch('toast', type: 'success', message: 'Data pasien berhasil dihapus.');
        } else {
            $this->dispatch('toast', type: 'error', message: 'Gagal hapus: ' . $result['message']);
        }
    }

    public function cancelPasienForm(): void
    {
        $this->formMode = '';
        $this->formResult = null;
    }

    // =================== Diagnosis CRUD ===================

    public function openAddDiagnosis(?string $nomr = null): void
    {
        $this->diagForm = $this->defaultDiagForm();
        $this->diagForm['nomr'] = $nomr ?? '';
        $this->diagFormMode = 'add';
        $this->diagFormResult = null;
    }

    public function submitDiagnosis(): void
    {
        $this->validate(
            [
                'diagForm.nomr' => 'required',
                'diagForm.kode_diagnosa' => 'required',
            ],
            [
                'diagForm.nomr.required' => 'No MR wajib diisi.',
                'diagForm.kode_diagnosa.required' => 'Kode diagnosa wajib diisi.',
            ],
        );

        $service = new RsOnlineService();
        $result = $this->diagFormMode === 'edit' ? $service->updateDiagnosis($this->diagForm) : $service->kirimDiagnosis($this->diagForm);

        $this->diagFormResult = $result;

        if ($result['success']) {
            $this->diagFormMode = '';
            $this->loadDiagnosis();
            $this->dispatch('toast', type: 'success', message: 'Data diagnosis berhasil disimpan.');
        } else {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $result['message']);
        }
    }

    public function deleteDiagnosis(string $nomr, string $kode): void
    {
        $service = new RsOnlineService();
        $result = $service->deleteDiagnosis(['nomr' => $nomr, 'kode_diagnosa' => $kode]);

        if ($result['success']) {
            $this->loadDiagnosis();
            $this->dispatch('toast', type: 'success', message: 'Diagnosis berhasil dihapus.');
        } else {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $result['message']);
        }
    }

    public function cancelDiagForm(): void
    {
        $this->diagFormMode = '';
        $this->diagFormResult = null;
    }
};
?>

<div>
    <x-ui.page-header title="Data Pasien RS Online" subtitle="Kelola pengiriman data pasien ke RS Online Kemenkes">
        <x-slot:actions>
            <a wire:navigate href="{{ route('rsonline.configuration') }}">
                <x-atoms.button icon="cog-6-tooth" variant="ghost" size="sm">Konfigurasi</x-atoms.button>
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Tab --}}
    <div class="bg-white dark:bg-primary-dark-800 rounded-xl shadow border border-zinc-200 dark:border-primary-dark-700">
        <x-molecules.tabs>
    
                <x-atoms.tab-item wire:click="switchTab('pasien')" :active="$activeTab === 'pasien'">Pasien</x-atoms.tab-item>
                <x-atoms.tab-item wire:click="switchTab('diagnosis')" :active="$activeTab === 'diagnosis'">Diagnosis</x-atoms.tab-item>
            
    </x-molecules.tabs>

        <div class="p-6">
            {{-- Error --}}
            @if ($listError)
                <div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-4 mb-4">
                    <div class="flex items-start gap-3">
                        <flux:icon name="exclamation-triangle" class="w-5 h-5 text-red-500 shrink-0 mt-0.5" />
                        <p class="text-sm text-red-700 dark:text-red-400">{{ $listError }}</p>
                    </div>
                </div>
            @endif

            {{-- ========= TAB PASIEN ========= --}}
            @if ($activeTab === 'pasien')
                {{-- Form tambah/edit --}}
                @if ($formMode)
                    <div
                        class="mb-6 bg-zinc-50 dark:bg-primary-dark-900/50 rounded-lg border border-zinc-200 dark:border-primary-dark-700 p-5">
                        <h4 class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-200 mb-4">
                            {{ $formMode === 'edit' ? 'Edit Data Pasien' : 'Tambah Data Pasien Baru' }}
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div>
                                <flux:label>No MR *</flux:label>
                                <flux:input wire:model="form.nomr" placeholder="No rekam medis" class="mt-1" />
                            </div>
                            <div>
                                <flux:label>NIK (noc)</flux:label>
                                <flux:input wire:model="form.noc" placeholder="Nomor NIK pasien" class="mt-1" />
                            </div>
                            <div>
                                <flux:label>Initial</flux:label>
                                <flux:input wire:model="form.initial" placeholder="Inisial" class="mt-1" />
                            </div>
                            <div class="md:col-span-2">
                                <flux:label>Nama Lengkap *</flux:label>
                                <flux:input wire:model="form.nama_lengkap" placeholder="Nama lengkap pasien"
                                    class="mt-1" />
                            </div>
                            <div>
                                <flux:label>Gender</flux:label>
                                <flux:select wire:model="form.gender" class="mt-1">
                                    <option value="">-- Pilih --</option>
                                    <option value="1">Laki-laki</option>
                                    <option value="2">Perempuan</option>
                                </flux:select>
                            </div>
                            <div>
                                <flux:label>Tanggal Lahir</flux:label>
                                <flux:input wire:model="form.birthdate" type="date" class="mt-1" />
                            </div>
                            <div>
                                <flux:label>Tanggal Masuk *</flux:label>
                                <flux:input wire:model="form.tglmasuk" type="date" class="mt-1" />
                            </div>
                            <div>
                                <flux:label>Tanggal Keluar</flux:label>
                                <flux:input wire:model="form.tglkeluar" type="date" class="mt-1" />
                            </div>
                            <div>
                                <flux:label>Status Rawat</flux:label>
                                <flux:input wire:model="form.status_rawat" placeholder="Kode status rawat"
                                    class="mt-1" />
                            </div>
                            <div>
                                <flux:label>Status Isolasi</flux:label>
                                <flux:input wire:model="form.status_isolasi" placeholder="Kode status isolasi"
                                    class="mt-1" />
                            </div>
                            <div>
                                <flux:label>Status Keluar</flux:label>
                                <flux:input wire:model="form.status_keluar" placeholder="Kode status keluar"
                                    class="mt-1" />
                            </div>
                            <div>
                                <flux:label>Sumber Penularan</flux:label>
                                <flux:input wire:model="form.sumber_penularan" placeholder="Kode sumber"
                                    class="mt-1" />
                            </div>
                            <div>
                                <flux:label>Kecamatan</flux:label>
                                <flux:input wire:model="form.kecamatan" placeholder="Kode kecamatan" class="mt-1" />
                            </div>
                            <div>
                                <flux:label>No Telp</flux:label>
                                <flux:input wire:model="form.notelp" placeholder="-" class="mt-1" />
                            </div>
                            <div>
                                <flux:label>Email</flux:label>
                                <flux:input wire:model="form.email" placeholder="-" class="mt-1" />
                            </div>
                            <div>
                                <flux:label>Sebab Kematian</flux:label>
                                <flux:input wire:model="form.sebab_kematian" placeholder="(jika meninggal)"
                                    class="mt-1" />
                            </div>
                            <div>
                                <flux:label>Tgl Lapor</flux:label>
                                <flux:input wire:model="form.tgl_lapor" type="datetime-local" class="mt-1" />
                            </div>
                        </div>

                        @if ($formResult && !$formResult['success'])
                            <div
                                class="mt-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded text-sm text-red-700 dark:text-red-400">
                                {{ $formResult['message'] }}
                            </div>
                        @endif

                        <div class="flex gap-3 mt-5 pt-4 border-t border-zinc-200 dark:border-primary-dark-700">
                            <x-atoms.button wire:click="submitPasien" variant="primary" wire:loading.attr="disabled"
                                wire:target="submitPasien">
                                <span wire:loading.remove
                                    wire:target="submitPasien">{{ $formMode === 'edit' ? 'Update' : 'Kirim' }}</span>
                                <span wire:loading wire:target="submitPasien">Memproses...</span>
                            </x-atoms.button>
                            <x-atoms.button wire:click="cancelPasienForm" variant="ghost">Batal</x-atoms.button>
                        </div>
                    </div>
                @endif

                {{-- Toolbar --}}
                <div class="flex items-center justify-between mb-4">
                    <div class="flex gap-2">
                        <x-atoms.button wire:click="loadPasien" icon="arrow-path" variant="ghost" size="sm"
                            wire:loading.attr="disabled" wire:target="loadPasien">
                            <span wire:loading.remove wire:target="loadPasien">Muat Data</span>
                            <span wire:loading wire:target="loadPasien">Memuat...</span>
                        </x-atoms.button>
                    </div>
                    @if (!$formMode)
                        <x-atoms.button wire:click="openAddPasien" icon="plus" variant="primary" size="sm">
                            Tambah Pasien
                        </x-atoms.button>
                    @endif
                </div>

                {{-- Tabel --}}
                <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-primary-dark-700">
                    <table class="w-full text-sm">
                        <thead class="bg-zinc-50 dark:bg-primary-dark-900/50">
                            <tr>
                                <th
                                    class="px-4 py-2.5 text-left text-xs font-medium text-zinc-500 dark:text-primary-dark-400">
                                    No MR</th>
                                <th
                                    class="px-4 py-2.5 text-left text-xs font-medium text-zinc-500 dark:text-primary-dark-400">
                                    Nama</th>
                                <th
                                    class="px-4 py-2.5 text-left text-xs font-medium text-zinc-500 dark:text-primary-dark-400">
                                    Tgl Masuk</th>
                                <th
                                    class="px-4 py-2.5 text-left text-xs font-medium text-zinc-500 dark:text-primary-dark-400">
                                    Status Rawat</th>
                                <th
                                    class="px-4 py-2.5 text-left text-xs font-medium text-zinc-500 dark:text-primary-dark-400">
                                    Status Isolasi</th>
                                <th
                                    class="px-4 py-2.5 text-left text-xs font-medium text-zinc-500 dark:text-primary-dark-400">
                                    Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
                            @forelse ($pasienList as $row)
                                @php $row = (array) $row; @endphp
                                <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/30">
                                    <td class="px-4 py-2.5 font-mono text-zinc-800 dark:text-primary-dark-200">
                                        {{ $row['nomr'] ?? '-' }}</td>
                                    <td class="px-4 py-2.5 text-zinc-800 dark:text-primary-dark-200">
                                        {{ $row['nama_lengkap'] ?? '-' }}</td>
                                    <td class="px-4 py-2.5 text-zinc-600 dark:text-primary-dark-400">
                                        {{ $row['tglmasuk'] ?? '-' }}</td>
                                    <td class="px-4 py-2.5 text-zinc-600 dark:text-primary-dark-400">
                                        {{ $row['status_rawat'] ?? '-' }}</td>
                                    <td class="px-4 py-2.5 text-zinc-600 dark:text-primary-dark-400">
                                        {{ $row['status_isolasi'] ?? '-' }}</td>
                                    <td class="px-4 py-2.5">
                                        <div class="flex gap-1">
                                            <x-atoms.button wire:click="openEditPasien({{ json_encode($row) }})"
                                                icon="pencil" size="xs" variant="ghost" title="Edit" />
                                            <x-atoms.button
                                                wire:click="deletePasien('{{ $row['nomr'] ?? '' }}', '{{ $row['noc'] ?? '' }}')"
                                                wire:confirm="Hapus data pasien ini?" icon="trash" size="xs"
                                                variant="ghost" class="text-red-500 hover:text-red-700"
                                                title="Hapus" />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6"
                                        class="px-4 py-8 text-center text-zinc-400 dark:text-primary-dark-600 text-sm">
                                        Belum ada data. Klik "Muat Data" untuk mengambil dari API.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- ========= TAB DIAGNOSIS ========= --}}
            @if ($activeTab === 'diagnosis')
                {{-- Form tambah diagnosis --}}
                @if ($diagFormMode)
                    <div
                        class="mb-6 bg-zinc-50 dark:bg-primary-dark-900/50 rounded-lg border border-zinc-200 dark:border-primary-dark-700 p-5">
                        <h4 class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-200 mb-4">
                            {{ $diagFormMode === 'edit' ? 'Edit Diagnosis' : 'Tambah Diagnosis' }}
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <flux:label>No MR *</flux:label>
                                <flux:input wire:model="diagForm.nomr" placeholder="No rekam medis" class="mt-1" />
                            </div>
                            <div>
                                <flux:label>Kode Diagnosa *</flux:label>
                                <flux:input wire:model="diagForm.kode_diagnosa" placeholder="Mis: A00"
                                    class="mt-1" />
                            </div>
                            <div>
                                <flux:label>Diagnosa</flux:label>
                                <flux:input wire:model="diagForm.diagnosa" placeholder="Nama diagnosa"
                                    class="mt-1" />
                            </div>
                            <div>
                                <flux:label>Tanggal Masuk</flux:label>
                                <flux:input wire:model="diagForm.tglmasuk" type="date" class="mt-1" />
                            </div>
                        </div>

                        @if ($diagFormResult && !$diagFormResult['success'])
                            <div
                                class="mt-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded text-sm text-red-700 dark:text-red-400">
                                {{ $diagFormResult['message'] }}
                            </div>
                        @endif

                        <div class="flex gap-3 mt-5 pt-4 border-t border-zinc-200 dark:border-primary-dark-700">
                            <x-atoms.button wire:click="submitDiagnosis" variant="primary" wire:loading.attr="disabled"
                                wire:target="submitDiagnosis">
                                <span wire:loading.remove
                                    wire:target="submitDiagnosis">{{ $diagFormMode === 'edit' ? 'Update' : 'Kirim' }}</span>
                                <span wire:loading wire:target="submitDiagnosis">Memproses...</span>
                            </x-atoms.button>
                            <x-atoms.button wire:click="cancelDiagForm" variant="ghost">Batal</x-atoms.button>
                        </div>
                    </div>
                @endif

                <div class="flex items-center justify-between mb-4">
                    <x-atoms.button wire:click="loadDiagnosis" icon="arrow-path" variant="ghost" size="sm"
                        wire:loading.attr="disabled" wire:target="loadDiagnosis">
                        <span wire:loading.remove wire:target="loadDiagnosis">Muat Data</span>
                        <span wire:loading wire:target="loadDiagnosis">Memuat...</span>
                    </x-atoms.button>
                    @if (!$diagFormMode)
                        <x-atoms.button wire:click="openAddDiagnosis" icon="plus" variant="primary" size="sm">
                            Tambah Diagnosis
                        </x-atoms.button>
                    @endif
                </div>

                <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-primary-dark-700">
                    <table class="w-full text-sm">
                        <thead class="bg-zinc-50 dark:bg-primary-dark-900/50">
                            <tr>
                                <th
                                    class="px-4 py-2.5 text-left text-xs font-medium text-zinc-500 dark:text-primary-dark-400">
                                    No MR</th>
                                <th
                                    class="px-4 py-2.5 text-left text-xs font-medium text-zinc-500 dark:text-primary-dark-400">
                                    Kode Diagnosa</th>
                                <th
                                    class="px-4 py-2.5 text-left text-xs font-medium text-zinc-500 dark:text-primary-dark-400">
                                    Diagnosa</th>
                                <th
                                    class="px-4 py-2.5 text-left text-xs font-medium text-zinc-500 dark:text-primary-dark-400">
                                    Tgl Masuk</th>
                                <th
                                    class="px-4 py-2.5 text-left text-xs font-medium text-zinc-500 dark:text-primary-dark-400">
                                    Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
                            @forelse ($diagnosisList as $row)
                                @php $row = (array) $row; @endphp
                                <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/30">
                                    <td class="px-4 py-2.5 font-mono text-zinc-800 dark:text-primary-dark-200">
                                        {{ $row['nomr'] ?? '-' }}</td>
                                    <td class="px-4 py-2.5 font-mono text-zinc-800 dark:text-primary-dark-200">
                                        {{ $row['kode_diagnosa'] ?? '-' }}</td>
                                    <td class="px-4 py-2.5 text-zinc-600 dark:text-primary-dark-400">
                                        {{ $row['diagnosa'] ?? '-' }}</td>
                                    <td class="px-4 py-2.5 text-zinc-600 dark:text-primary-dark-400">
                                        {{ $row['tglmasuk'] ?? '-' }}</td>
                                    <td class="px-4 py-2.5">
                                        <div class="flex gap-1">
                                            <x-atoms.button wire:click="openAddDiagnosis('{{ $row['nomr'] ?? '' }}')"
                                                icon="pencil" size="xs" variant="ghost" title="Edit" />
                                            <x-atoms.button
                                                wire:click="deleteDiagnosis('{{ $row['nomr'] ?? '' }}', '{{ $row['kode_diagnosa'] ?? '' }}')"
                                                wire:confirm="Hapus diagnosis ini?" icon="trash" size="xs"
                                                variant="ghost" class="text-red-500 hover:text-red-700"
                                                title="Hapus" />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5"
                                        class="px-4 py-8 text-center text-zinc-400 dark:text-primary-dark-600 text-sm">
                                        Belum ada data. Klik "Muat Data" untuk mengambil dari API.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
