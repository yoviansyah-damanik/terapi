<?php

use App\Models\Simrs\Alergi;
use App\Models\Simrs\AlergiKritisitas;
use App\Models\Simrs\AlergiPasien;
use App\Models\Simrs\AlergiTingkatKeparahan;
use App\Models\Simrs\Pasien;
use App\Models\Simrs\Pegawai;
use App\Models\Simrs\AlergiReaksi;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

new #[Layout('layouts::app')] #[Title('Master Data — Alergi')] class extends Component {
    use WithPagination;

    #[Url]
    public string $tab = 'allergy';

    // Filter tab alergi
    #[Url]
    public string $searchAllergy = '';

    #[Url]
    public string $filterTipe = '';

    // Filter tab reaksi
    #[Url]
    public string $searchReaction = '';

    #[Url]
    public string $filterKategori = '';

    // -------------------------------------------------------------------------
    // CRUD — Alergi
    // -------------------------------------------------------------------------
    public bool $showAllergyModal = false;
    public ?int $editingAllergyId = null;
    public string $allergyName = '';
    public string $allergyKeterangan = '';
    public string $allergyTipeMode = 'existing'; // 'existing' | 'new'
    public string $allergyTipeSelected = '';
    public string $allergyTipeNew = '';

    public bool $showDeleteAllergyModal = false;
    public ?int $deletingAllergyId = null;
    public string $deletingAllergyName = '';

    // -------------------------------------------------------------------------
    // CRUD — Reaksi Alergi
    // -------------------------------------------------------------------------
    public bool $showReactionModal = false;
    public ?int $editingReactionId = null;
    public string $reactionName = '';
    public string $reactionKeterangan = '';
    public string $reactionKategoriMode = 'existing'; // 'existing' | 'new'
    public string $reactionKategoriSelected = '';
    public string $reactionKategoriNew = '';

    public bool $showDeleteReactionModal = false;
    public ?int $deletingReactionId = null;
    public string $deletingReactionName = '';

    // -------------------------------------------------------------------------
    // Modal daftar pasien
    // -------------------------------------------------------------------------
    public bool $showPatientsModal = false;
    public ?int $viewingAllergyId = null;
    public string $viewingAllergyName = '';

    // -------------------------------------------------------------------------
    // CRUD — Tingkat Keparahan
    // -------------------------------------------------------------------------
    public string $searchKeparahan = '';
    public bool $showKeparahanModal = false;
    public ?int $editingKeparahanId = null;
    public string $keparahanName = '';
    public string $keparahanDesc = '';
    public bool $showDeleteKeparahanModal = false;
    public ?int $deletingKeparahanId = null;
    public string $deletingKeparahanName = '';

    // -------------------------------------------------------------------------
    // CRUD — Kritisitas
    // -------------------------------------------------------------------------
    public string $searchKritisitas = '';
    public bool $showKritisitasModal = false;
    public ?int $editingKritisitasId = null;
    public string $kritisitasName = '';
    public string $kritisitasDesc = '';
    public bool $showDeleteKritisitasModal = false;
    public ?int $deletingKritisitasId = null;
    public string $deletingKritisitasName = '';

    public function switchTab(string $tab): void
    {
        $this->tab = $tab;
        $this->resetPage();
    }

    public function updatedSearchAllergy(): void
    {
        $this->resetPage();
    }
    public function updatedFilterTipe(): void
    {
        $this->resetPage();
    }
    public function updatedSearchReaction(): void
    {
        $this->resetPage();
    }
    public function updatedFilterKategori(): void
    {
        $this->resetPage();
    }
    public function updatedSearchKeparahan(): void
    {
        $this->resetPage();
    }
    public function updatedSearchKritisitas(): void
    {
        $this->resetPage();
    }

    // -------------------------------------------------------------------------
    // CRUD — Alergi methods
    // -------------------------------------------------------------------------

    public function openCreateAllergy(): void
    {
        $this->editingAllergyId = null;
        $this->allergyName = '';
        $this->allergyKeterangan = '';
        $this->allergyTipeMode = 'existing';
        $this->allergyTipeSelected = '';
        $this->allergyTipeNew = '';
        $this->showAllergyModal = true;
    }

    public function openEditAllergy(int $id): void
    {
        $alergi = Alergi::findOrFail($id);

        $this->editingAllergyId = $id;
        $this->allergyName = $alergi->nama_alergi;
        $this->allergyKeterangan = $alergi->keterangan ?? '';

        // Cek apakah tipe sudah ada di daftar
        $existingTypes = Alergi::whereNotNull('tipe')->where('tipe', '!=', '')->distinct()->pluck('tipe');
        if ($alergi->tipe && $existingTypes->contains($alergi->tipe)) {
            $this->allergyTipeMode = 'existing';
            $this->allergyTipeSelected = $alergi->tipe;
            $this->allergyTipeNew = '';
        } else {
            $this->allergyTipeMode = $alergi->tipe ? 'new' : 'existing';
            $this->allergyTipeSelected = '';
            $this->allergyTipeNew = $alergi->tipe ?? '';
        }

        $this->showAllergyModal = true;
    }

    public function saveAllergy(): void
    {
        $tipe = $this->allergyTipeMode === 'new' ? $this->allergyTipeNew : $this->allergyTipeSelected;

        $rules = [
            'allergyName' => 'required|string|max:200',
            'allergyKeterangan' => 'nullable|string|max:500',
        ];
        if ($this->allergyTipeMode === 'new' && $this->allergyTipeNew !== '') {
            $rules['allergyTipeNew'] = ['required', 'regex:/^[a-z0-9]+$/'];
        }
        $this->validate($rules, [
            'allergyName.required' => 'Nama alergi wajib diisi.',
            'allergyTipeNew.required' => 'Masukkan tipe baru.',
            'allergyTipeNew.regex' => 'Tipe hanya boleh huruf kecil dan angka, tanpa spasi atau simbol.',
        ]);

        try {
            $data = [
                'nama_alergi' => $this->allergyName,
                'keterangan' => $this->allergyKeterangan ?: null,
                'tipe' => $tipe ?: null,
            ];

            if ($this->editingAllergyId) {
                Alergi::where('id', $this->editingAllergyId)->update($data);
                $this->toastSuccess('Data alergi berhasil diperbarui.');
            } else {
                Alergi::create($data);
                $this->toastSuccess('Data alergi berhasil ditambahkan.');
            }
        } catch (\Exception $e) {
            $this->toastError('Gagal menyimpan: ' . $e->getMessage());
            return;
        }

        $this->showAllergyModal = false;
    }

    public function confirmDeleteAllergy(int $id, string $name): void
    {
        $this->deletingAllergyId = $id;
        $this->deletingAllergyName = $name;
        $this->showDeleteAllergyModal = true;
    }

    public function deleteAllergy(): void
    {
        if (!$this->deletingAllergyId) {
            return;
        }

        try {
            Alergi::where('id', $this->deletingAllergyId)->delete();
            $this->toastSuccess("Alergi \"{$this->deletingAllergyName}\" berhasil dihapus.");
        } catch (\Exception $e) {
            $this->toastError('Gagal menghapus: ' . $e->getMessage());
        }

        $this->showDeleteAllergyModal = false;
        $this->deletingAllergyId = null;
        $this->deletingAllergyName = '';
    }

    // -------------------------------------------------------------------------
    // CRUD — Reaksi Alergi methods
    // -------------------------------------------------------------------------

    public function openCreateReaction(): void
    {
        $this->editingReactionId = null;
        $this->reactionName = '';
        $this->reactionKeterangan = '';
        $this->reactionKategoriMode = 'existing';
        $this->reactionKategoriSelected = '';
        $this->reactionKategoriNew = '';
        $this->showReactionModal = true;
    }

    public function openEditReaction(int $id): void
    {
        $reaksi = AlergiReaksi::findOrFail($id);

        $this->editingReactionId = $id;
        $this->reactionName = $reaksi->nama_reaksi;
        $this->reactionKeterangan = $reaksi->keterangan ?? '';

        $existingCategories = AlergiReaksi::whereNotNull('kategori')->where('kategori', '!=', '')->distinct()->pluck('kategori');
        if ($reaksi->kategori && $existingCategories->contains($reaksi->kategori)) {
            $this->reactionKategoriMode = 'existing';
            $this->reactionKategoriSelected = $reaksi->kategori;
            $this->reactionKategoriNew = '';
        } else {
            $this->reactionKategoriMode = $reaksi->kategori ? 'new' : 'existing';
            $this->reactionKategoriSelected = '';
            $this->reactionKategoriNew = $reaksi->kategori ?? '';
        }

        $this->showReactionModal = true;
    }

    public function saveReaction(): void
    {
        $kategori = $this->reactionKategoriMode === 'new' ? $this->reactionKategoriNew : $this->reactionKategoriSelected;

        $rules = [
            'reactionName' => 'required|string|max:200',
            'reactionKeterangan' => 'nullable|string|max:500',
        ];
        if ($this->reactionKategoriMode === 'new' && $this->reactionKategoriNew !== '') {
            $rules['reactionKategoriNew'] = ['required', 'regex:/^[a-z0-9]+$/'];
        }
        $this->validate($rules, [
            'reactionName.required' => 'Nama reaksi wajib diisi.',
            'reactionKategoriNew.required' => 'Masukkan kategori baru.',
            'reactionKategoriNew.regex' => 'Kategori hanya boleh huruf kecil dan angka, tanpa spasi atau simbol.',
        ]);

        try {
            $data = [
                'nama_reaksi' => $this->reactionName,
                'keterangan' => $this->reactionKeterangan ?: null,
                'kategori' => $kategori ?: null,
            ];

            if ($this->editingReactionId) {
                AlergiReaksi::where('id', $this->editingReactionId)->update($data);
                $this->toastSuccess('Data reaksi alergi berhasil diperbarui.');
            } else {
                AlergiReaksi::create($data);
                $this->toastSuccess('Data reaksi alergi berhasil ditambahkan.');
            }
        } catch (\Exception $e) {
            $this->toastError('Gagal menyimpan: ' . $e->getMessage());
            return;
        }

        $this->showReactionModal = false;
    }

    public function confirmDeleteReaction(int $id, string $name): void
    {
        $this->deletingReactionId = $id;
        $this->deletingReactionName = $name;
        $this->showDeleteReactionModal = true;
    }

    public function deleteReaction(): void
    {
        if (!$this->deletingReactionId) {
            return;
        }

        try {
            AlergiReaksi::where('id', $this->deletingReactionId)->delete();
            $this->toastSuccess("Reaksi \"{$this->deletingReactionName}\" berhasil dihapus.");
        } catch (\Exception $e) {
            $this->toastError('Gagal menghapus: ' . $e->getMessage());
        }

        $this->showDeleteReactionModal = false;
        $this->deletingReactionId = null;
        $this->deletingReactionName = '';
    }

    // -------------------------------------------------------------------------
    // CRUD — Tingkat Keparahan methods
    // -------------------------------------------------------------------------

    public function openCreateKeparahan(): void
    {
        $this->editingKeparahanId = null;
        $this->keparahanName = '';
        $this->keparahanDesc = '';
        $this->showKeparahanModal = true;
    }

    public function openEditKeparahan(int $id): void
    {
        $row = AlergiTingkatKeparahan::findOrFail($id);
        $this->editingKeparahanId = $id;
        $this->keparahanName = $row->keparahan;
        $this->keparahanDesc = $row->deskripsi ?? '';
        $this->showKeparahanModal = true;
    }

    public function saveKeparahan(): void
    {
        $this->validate(
            [
                'keparahanName' => 'required|string|max:100',
                'keparahanDesc' => 'nullable|string|max:500',
            ],
            [],
            ['keparahanName' => 'Tingkat Keparahan', 'keparahanDesc' => 'Deskripsi'],
        );

        try {
            $data = ['keparahan' => $this->keparahanName, 'deskripsi' => $this->keparahanDesc ?: null];
            if ($this->editingKeparahanId) {
                AlergiTingkatKeparahan::where('id', $this->editingKeparahanId)->update($data);
                $this->toastSuccess('Tingkat keparahan berhasil diperbarui.');
            } else {
                AlergiTingkatKeparahan::create($data);
                $this->toastSuccess('Tingkat keparahan berhasil ditambahkan.');
            }
        } catch (\Exception $e) {
            $this->toastError('Gagal menyimpan: ' . $e->getMessage());
            return;
        }

        $this->showKeparahanModal = false;
    }

    public function confirmDeleteKeparahan(int $id, string $name): void
    {
        $this->deletingKeparahanId = $id;
        $this->deletingKeparahanName = $name;
        $this->showDeleteKeparahanModal = true;
    }

    public function deleteKeparahan(): void
    {
        try {
            AlergiTingkatKeparahan::where('id', $this->deletingKeparahanId)->delete();
            $this->toastSuccess("Tingkat keparahan \"{$this->deletingKeparahanName}\" berhasil dihapus.");
        } catch (\Exception $e) {
            $this->toastError('Gagal menghapus: ' . $e->getMessage());
        }

        $this->showDeleteKeparahanModal = false;
        $this->deletingKeparahanId = null;
        $this->deletingKeparahanName = '';
    }

    // -------------------------------------------------------------------------
    // CRUD — Kritisitas methods
    // -------------------------------------------------------------------------

    public function openCreateKritisitas(): void
    {
        $this->editingKritisitasId = null;
        $this->kritisitasName = '';
        $this->kritisitasDesc = '';
        $this->showKritisitasModal = true;
    }

    public function openEditKritisitas(int $id): void
    {
        $row = AlergiKritisitas::findOrFail($id);
        $this->editingKritisitasId = $id;
        $this->kritisitasName = $row->kritisitas;
        $this->kritisitasDesc = $row->deskripsi ?? '';
        $this->showKritisitasModal = true;
    }

    public function saveKritisitas(): void
    {
        $this->validate(
            [
                'kritisitasName' => 'required|string|max:100',
                'kritisitasDesc' => 'nullable|string|max:500',
            ],
            [],
            ['kritisitasName' => 'Kritisitas', 'kritisitasDesc' => 'Deskripsi'],
        );

        try {
            $data = ['kritisitas' => $this->kritisitasName, 'deskripsi' => $this->kritisitasDesc ?: null];
            if ($this->editingKritisitasId) {
                AlergiKritisitas::where('id', $this->editingKritisitasId)->update($data);
                $this->toastSuccess('Kritisitas berhasil diperbarui.');
            } else {
                AlergiKritisitas::create($data);
                $this->toastSuccess('Kritisitas berhasil ditambahkan.');
            }
        } catch (\Exception $e) {
            $this->toastError('Gagal menyimpan: ' . $e->getMessage());
            return;
        }

        $this->showKritisitasModal = false;
    }

    public function confirmDeleteKritisitas(int $id, string $name): void
    {
        $this->deletingKritisitasId = $id;
        $this->deletingKritisitasName = $name;
        $this->showDeleteKritisitasModal = true;
    }

    public function deleteKritisitas(): void
    {
        try {
            AlergiKritisitas::where('id', $this->deletingKritisitasId)->delete();
            $this->toastSuccess("Kritisitas \"{$this->deletingKritisitasName}\" berhasil dihapus.");
        } catch (\Exception $e) {
            $this->toastError('Gagal menghapus: ' . $e->getMessage());
        }

        $this->showDeleteKritisitasModal = false;
        $this->deletingKritisitasId = null;
        $this->deletingKritisitasName = '';
    }

    // -------------------------------------------------------------------------
    // Pasien modal
    // -------------------------------------------------------------------------

    public function viewPatients(int $allergyId, string $allergyName): void
    {
        $this->viewingAllergyId = $allergyId;
        $this->viewingAllergyName = $allergyName;
        $this->showPatientsModal = true;
    }

    public function with(): array
    {
        $simrsError = null;
        $allergies = collect();
        $reactions = collect();
        $keparahans = collect();
        $kritisitasList = collect();
        $allergyTypes = collect();
        $reactionCategories = collect();
        $totalAllergies = 0;
        $totalReactions = 0;
        $totalPatients = 0;
        $totalKeparahan = 0;
        $totalKritisitas = 0;
        $allergyPatients = collect();
        $patientNames = collect();
        $employeeNames = collect();

        try {
            $totalAllergies = Alergi::count();
            $totalReactions = AlergiReaksi::count();
            $totalPatients = AlergiPasien::distinct('no_rkm_medis')->count('no_rkm_medis');
            $totalKeparahan = AlergiTingkatKeparahan::count();
            $totalKritisitas = AlergiKritisitas::count();

            $allergyTypes = Alergi::whereNotNull('tipe')->where('tipe', '!=', '')->distinct()->orderBy('tipe')->pluck('tipe');

            $reactionCategories = AlergiReaksi::whereNotNull('kategori')->where('kategori', '!=', '')->distinct()->orderBy('kategori')->pluck('kategori');

            // Jumlah pasien unik per alergi
            $patientCounts = AlergiPasien::selectRaw('id_alergi, COUNT(DISTINCT no_rkm_medis) as patient_count')->groupBy('id_alergi')->pluck('patient_count', 'id_alergi');

            if ($this->tab === 'allergy') {
                $allergies = Alergi::query()
                    ->when(
                        $this->searchAllergy,
                        fn($q) => $q->where(function ($q) {
                            $q->where('nama_alergi', 'like', '%' . $this->searchAllergy . '%')
                                ->orWhere('tipe', 'like', '%' . $this->searchAllergy . '%')
                                ->orWhere('keterangan', 'like', '%' . $this->searchAllergy . '%');
                        }),
                    )
                    ->when($this->filterTipe, fn($q) => $q->where('tipe', $this->filterTipe))
                    ->orderBy('nama_alergi')
                    ->paginate(25);

                $allergies->getCollection()->transform(function ($a) use ($patientCounts) {
                    $a->patient_count = $patientCounts->get($a->id, 0);
                    return $a;
                });
            }

            if ($this->tab === 'reaction') {
                $reactions = AlergiReaksi::query()
                    ->when(
                        $this->searchReaction,
                        fn($q) => $q->where(function ($q) {
                            $q->where('nama_reaksi', 'like', '%' . $this->searchReaction . '%')
                                ->orWhere('kategori', 'like', '%' . $this->searchReaction . '%')
                                ->orWhere('keterangan', 'like', '%' . $this->searchReaction . '%');
                        }),
                    )
                    ->when($this->filterKategori, fn($q) => $q->where('kategori', $this->filterKategori))
                    ->orderBy('nama_reaksi')
                    ->paginate(25);
            }

            if ($this->tab === 'keparahan') {
                $keparahans = AlergiTingkatKeparahan::query()->when($this->searchKeparahan, fn($q) => $q->where('keparahan', 'like', '%' . $this->searchKeparahan . '%')->orWhere('deskripsi', 'like', '%' . $this->searchKeparahan . '%'))->orderBy('keparahan')->paginate(25);
            }

            if ($this->tab === 'kritisitas') {
                $kritisitasList = AlergiKritisitas::query()->when($this->searchKritisitas, fn($q) => $q->where('kritisitas', 'like', '%' . $this->searchKritisitas . '%')->orWhere('deskripsi', 'like', '%' . $this->searchKritisitas . '%'))->orderBy('kritisitas')->paginate(25);
            }

            // Data modal daftar pasien
            if ($this->showPatientsModal && $this->viewingAllergyId) {
                $allergyPatients = AlergiPasien::with(['reaksi', 'tingkatKeparahan', 'kritisitas'])
                    ->where('id_alergi', $this->viewingAllergyId)
                    ->orderByDesc('tanggal')
                    ->orderByDesc('jam')
                    ->get();

                $noRkmList = $allergyPatients->pluck('no_rkm_medis')->unique()->filter()->toArray();
                if (!empty($noRkmList)) {
                    $patientNames = Pasien::whereIn('no_rkm_medis', $noRkmList)->pluck('nm_pasien', 'no_rkm_medis');
                }

                $nipList = $allergyPatients->pluck('nip')->unique()->filter()->toArray();
                if (!empty($nipList)) {
                    $employeeNames = Pegawai::whereIn('id', $nipList)->pluck('nama', 'id');
                }
            }
        } catch (\Exception $e) {
            $simrsError = $e->getMessage();
        }

        return compact('simrsError', 'allergies', 'reactions', 'keparahans', 'kritisitasList', 'allergyTypes', 'reactionCategories', 'totalAllergies', 'totalReactions', 'totalPatients', 'totalKeparahan', 'totalKritisitas', 'allergyPatients', 'patientNames', 'employeeNames');
    }
};
?>


<div>
    <x-ui.page-header title="Master Data Alergi" subtitle="Data master alergi dan reaksi alergi dari SIMRS">
        <x-slot:actions>
            @if ($tab === 'allergy')
                <x-atoms.button wire:click="openCreateAllergy" icon="plus" variant="primary">
                    Tambah Alergi
                </x-atoms.button>
            @elseif ($tab === 'reaction')
                <x-atoms.button wire:click="openCreateReaction" icon="plus" variant="primary">
                    Tambah Reaksi
                </x-atoms.button>
            @elseif ($tab === 'keparahan')
                <x-atoms.button wire:click="openCreateKeparahan" icon="plus" variant="primary">
                    Tambah Keparahan
                </x-atoms.button>
            @elseif ($tab === 'kritisitas')
                <x-atoms.button wire:click="openCreateKritisitas" icon="plus" variant="primary">
                    Tambah Kritisitas
                </x-atoms.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($simrsError)
        <x-ui.empty-state icon="exclamation-triangle" title="Koneksi SIMRS Gagal" :description="$simrsError" />
    @else
        {{-- Stats --}}
        <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-5">
            <x-organisms.stat-card title="Jenis Alergi" :value="$totalAllergies" icon="shield-exclamation" color="violet" />
            <x-organisms.stat-card title="Jenis Reaksi" :value="$totalReactions" icon="bolt" color="amber" />
            <x-organisms.stat-card title="Tingkat Keparahan" :value="$totalKeparahan" icon="chart-bar" color="blue" />
            <x-organisms.stat-card title="Kritisitas" :value="$totalKritisitas" icon="exclamation-triangle" color="red" />
            <x-organisms.stat-card title="Pasien dengan Alergi" :value="$totalPatients" icon="user-group" color="rose" />
        </div>

        {{-- Tab bar --}}
        <x-molecules.tabs>
            <x-atoms.tab-item wire:click="switchTab('allergy')" :active="$tab === 'allergy'">
                <flux:icon.shield-exclamation class="size-4" />
                Alergi
                <span class="rounded-full px-1.5 py-0.5 text-xs font-semibold {{ $tab === 'allergy' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400' : 'bg-zinc-100 text-zinc-500 dark:bg-primary-dark-700 dark:text-primary-dark-400' }}">
                    {{ $totalAllergies }}
                </span>
            </x-atoms.tab-item>
            <x-atoms.tab-item wire:click="switchTab('reaction')" :active="$tab === 'reaction'">
                <flux:icon.bolt class="size-4" />
                Reaksi Alergi
                <span class="rounded-full px-1.5 py-0.5 text-xs font-semibold {{ $tab === 'reaction' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400' : 'bg-zinc-100 text-zinc-500 dark:bg-primary-dark-700 dark:text-primary-dark-400' }}">
                    {{ $totalReactions }}
                </span>
            </x-atoms.tab-item>
            <x-atoms.tab-item wire:click="switchTab('keparahan')" :active="$tab === 'keparahan'">
                <flux:icon.chart-bar class="size-4" />
                Tingkat Keparahan
                <span class="rounded-full px-1.5 py-0.5 text-xs font-semibold {{ $tab === 'keparahan' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400' : 'bg-zinc-100 text-zinc-500 dark:bg-primary-dark-700 dark:text-primary-dark-400' }}">
                    {{ $totalKeparahan }}
                </span>
            </x-atoms.tab-item>
            <x-atoms.tab-item wire:click="switchTab('kritisitas')" :active="$tab === 'kritisitas'">
                <flux:icon.exclamation-triangle class="size-4" />
                Kritisitas
                <span class="rounded-full px-1.5 py-0.5 text-xs font-semibold {{ $tab === 'kritisitas' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400' : 'bg-zinc-100 text-zinc-500 dark:bg-primary-dark-700 dark:text-primary-dark-400' }}">
                    {{ $totalKritisitas }}
                </span>
            </x-atoms.tab-item>
        </x-molecules.tabs>

        {{-- ================================================================ --}}
        {{-- Tab: Alergi                                                       --}}
        {{-- ================================================================ --}}
        @if ($tab === 'allergy')
            @if ($allergies->isEmpty())
                <x-ui.empty-state icon="shield-exclamation" title="Tidak ada data alergi"
                    description="Tidak ada alergi yang cocok dengan filter." />
            @else
                <x-organisms.data-panel>
                    <x-slot:filter>
                        <div class="min-w-48 flex-1">
                            <flux:input wire:model.live.debounce="searchAllergy" icon="magnifying-glass"
                                placeholder="Cari nama alergi, tipe, atau keterangan..." clearable />
                        </div>
                        @if ($allergyTypes->isNotEmpty())
                            <flux:select wire:model.live="filterTipe" class="w-44">
                                <flux:select.option value="">Semua Tipe</flux:select.option>
                                @foreach ($allergyTypes as $tipe)
                                    <flux:select.option value="{{ $tipe }}">{{ $tipe }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        @endif
                    </x-slot:filter>

                    <x-organisms.table>
                        <x-slot:headings>
                            <x-atoms.table-heading class="w-16">ID</x-atoms.table-heading>
                            <x-atoms.table-heading>Nama Alergi</x-atoms.table-heading>
                            <x-atoms.table-heading class="w-40">Tipe</x-atoms.table-heading>
                            <x-atoms.table-heading>Keterangan</x-atoms.table-heading>
                            <x-atoms.table-heading align="center" class="w-36">Jumlah Pasien</x-atoms.table-heading>
                            <x-atoms.table-heading class="w-24"></x-atoms.table-heading>
                        </x-slot:headings>

                        @foreach ($allergies as $item)
                            <x-molecules.table-row wire:key="allergy-{{ $item->id }}">
                                <x-atoms.table-cell nowrap>
                                    <span class="font-mono text-xs font-bold text-zinc-500 dark:text-primary-dark-400">
                                        {{ $item->id }}
                                    </span>
                                </x-atoms.table-cell>
                                <x-atoms.table-cell>
                                    <span class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">
                                        {{ $item->nama_alergi }}
                                    </span>
                                </x-atoms.table-cell>
                                <x-atoms.table-cell>
                                    @if ($item->tipe)
                                        <flux:badge color="violet" size="sm">{{ $item->tipe }}</flux:badge>
                                    @else
                                        <span class="text-xs text-zinc-400">—</span>
                                    @endif
                                </x-atoms.table-cell>
                                <x-atoms.table-cell class="text-zinc-500 dark:text-primary-dark-400">
                                    {{ $item->keterangan ?: '—' }}
                                </x-atoms.table-cell>
                                <x-atoms.table-cell align="center">
                                    @if ($item->patient_count > 0)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-700 dark:bg-rose-900/30 dark:text-rose-400">
                                            <flux:icon.user-group class="size-3" />
                                            {{ $item->patient_count }}
                                        </span>
                                    @else
                                        <span class="text-xs text-zinc-400">—</span>
                                    @endif
                                </x-atoms.table-cell>
                                <x-atoms.table-cell :action="true">
                                    <div class="flex items-center justify-end gap-1">
                                        @if ($item->patient_count > 0)
                                            <x-atoms.button size="xs" variant="ghost" icon="eye"
                                                wire:click="viewPatients({{ $item->id }}, '{{ addslashes($item->nama_alergi) }}')"
                                                class="opacity-0 transition-opacity group-hover:opacity-100" />
                                        @endif
                                        <x-atoms.button size="xs" variant="ghost" icon="pencil-square"
                                            wire:click="openEditAllergy({{ $item->id }})"
                                            class="opacity-0 transition-opacity group-hover:opacity-100" />
                                        <x-atoms.button size="xs" variant="ghost" icon="trash"
                                            wire:click="confirmDeleteAllergy({{ $item->id }}, '{{ addslashes($item->nama_alergi) }}')"
                                            class="text-red-400 opacity-0 transition-opacity group-hover:opacity-100 hover:text-red-600" />
                                    </div>
                                </x-atoms.table-cell>
                            </x-molecules.table-row>
                        @endforeach
                    </x-organisms.table>

                    <x-slot:footer>
                        @if ($allergies->hasPages())
                            {{ $allergies->links() }}
                        @endif
                    </x-slot:footer>
                </x-organisms.data-panel>
            @endif
        @endif

        {{-- ================================================================ --}}
        {{-- Tab: Reaksi Alergi                                               --}}
        {{-- ================================================================ --}}
        @if ($tab === 'reaction')
            @if ($reactions->isEmpty())
                <x-ui.empty-state icon="bolt" title="Tidak ada data reaksi alergi"
                    description="Tidak ada reaksi yang cocok dengan filter." />
            @else
                <x-organisms.data-panel>
                    <x-slot:filter>
                        <div class="min-w-48 flex-1">
                            <flux:input wire:model.live.debounce="searchReaction" icon="magnifying-glass"
                                placeholder="Cari nama reaksi, kategori, atau keterangan..." clearable />
                        </div>
                        @if ($reactionCategories->isNotEmpty())
                            <flux:select wire:model.live="filterKategori" class="w-44">
                                <flux:select.option value="">Semua Kategori</flux:select.option>
                                @foreach ($reactionCategories as $cat)
                                    <flux:select.option value="{{ $cat }}">{{ $cat }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        @endif
                    </x-slot:filter>

                    <x-organisms.table>
                        <x-slot:headings>
                            <x-atoms.table-heading class="w-16">ID</x-atoms.table-heading>
                            <x-atoms.table-heading>Nama Reaksi</x-atoms.table-heading>
                            <x-atoms.table-heading class="w-44">Kategori</x-atoms.table-heading>
                            <x-atoms.table-heading>Keterangan</x-atoms.table-heading>
                            <x-atoms.table-heading class="w-20"></x-atoms.table-heading>
                        </x-slot:headings>

                        @foreach ($reactions as $item)
                            <x-molecules.table-row wire:key="reaction-{{ $item->id }}">
                                <x-atoms.table-cell nowrap>
                                    <span class="font-mono text-xs font-bold text-zinc-500 dark:text-primary-dark-400">
                                        {{ $item->id }}
                                    </span>
                                </x-atoms.table-cell>
                                <x-atoms.table-cell>
                                    <span class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">
                                        {{ $item->nama_reaksi }}
                                    </span>
                                </x-atoms.table-cell>
                                <x-atoms.table-cell>
                                    @if ($item->kategori)
                                        <flux:badge color="amber" size="sm">{{ $item->kategori }}</flux:badge>
                                    @else
                                        <span class="text-xs text-zinc-400">—</span>
                                    @endif
                                </x-atoms.table-cell>
                                <x-atoms.table-cell class="text-zinc-500 dark:text-primary-dark-400">
                                    {{ $item->keterangan ?: '—' }}
                                </x-atoms.table-cell>
                                <x-atoms.table-cell :action="true">
                                    <div class="flex items-center justify-end gap-1">
                                        <x-atoms.button size="xs" variant="ghost" icon="pencil-square"
                                            wire:click="openEditReaction({{ $item->id }})"
                                            class="opacity-0 transition-opacity group-hover:opacity-100" />
                                        <x-atoms.button size="xs" variant="ghost" icon="trash"
                                            wire:click="confirmDeleteReaction({{ $item->id }}, '{{ addslashes($item->nama_reaksi) }}')"
                                            class="text-red-400 opacity-0 transition-opacity group-hover:opacity-100 hover:text-red-600" />
                                    </div>
                                </x-atoms.table-cell>
                            </x-molecules.table-row>
                        @endforeach
                    </x-organisms.table>

                    <x-slot:footer>
                        @if ($reactions->hasPages())
                            {{ $reactions->links() }}
                        @endif
                    </x-slot:footer>
                </x-organisms.data-panel>
            @endif
        @endif

        {{-- ================================================================ --}}
        {{-- Tab: Tingkat Keparahan                                           --}}
        {{-- ================================================================ --}}
        @if ($tab === 'keparahan')
            @if ($keparahans->isEmpty())
                <x-ui.empty-state icon="chart-bar" title="Tidak ada data tingkat keparahan"
                    description="Belum ada data tingkat keparahan yang cocok dengan filter." />
            @else
                <x-organisms.data-panel>
                    <x-slot:filter>
                        <div class="flex-1">
                            <flux:input wire:model.live.debounce="searchKeparahan" icon="magnifying-glass"
                                placeholder="Cari tingkat keparahan atau deskripsi..." clearable />
                        </div>
                    </x-slot:filter>

                    <x-organisms.table>
                        <x-slot:headings>
                            <x-atoms.table-heading class="w-16">ID</x-atoms.table-heading>
                            <x-atoms.table-heading>Tingkat Keparahan</x-atoms.table-heading>
                            <x-atoms.table-heading>Deskripsi</x-atoms.table-heading>
                            <x-atoms.table-heading class="w-20"></x-atoms.table-heading>
                        </x-slot:headings>

                        @foreach ($keparahans as $item)
                            <x-molecules.table-row wire:key="keparahan-{{ $item->id }}">
                                <x-atoms.table-cell nowrap>
                                    <span class="font-mono text-xs font-bold text-zinc-500 dark:text-primary-dark-400">
                                        {{ $item->id }}
                                    </span>
                                </x-atoms.table-cell>
                                <x-atoms.table-cell>
                                    <span class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">
                                        {{ $item->keparahan }}
                                    </span>
                                </x-atoms.table-cell>
                                <x-atoms.table-cell class="text-zinc-500 dark:text-primary-dark-400">
                                    {{ $item->deskripsi ?: '—' }}
                                </x-atoms.table-cell>
                                <x-atoms.table-cell :action="true">
                                    <div class="flex items-center justify-end gap-1">
                                        <x-atoms.button size="xs" variant="ghost" icon="pencil-square"
                                            wire:click="openEditKeparahan({{ $item->id }})"
                                            class="opacity-0 transition-opacity group-hover:opacity-100" />
                                        <x-atoms.button size="xs" variant="ghost" icon="trash"
                                            wire:click="confirmDeleteKeparahan({{ $item->id }}, '{{ addslashes($item->keparahan) }}')"
                                            class="text-red-400 opacity-0 transition-opacity group-hover:opacity-100 hover:text-red-600" />
                                    </div>
                                </x-atoms.table-cell>
                            </x-molecules.table-row>
                        @endforeach
                    </x-organisms.table>

                    <x-slot:footer>
                        @if ($keparahans->hasPages())
                            {{ $keparahans->links() }}
                        @endif
                    </x-slot:footer>
                </x-organisms.data-panel>
            @endif
        @endif

        {{-- ================================================================ --}}
        {{-- Tab: Kritisitas                                                  --}}
        {{-- ================================================================ --}}
        @if ($tab === 'kritisitas')
            @if ($kritisitasList->isEmpty())
                <x-ui.empty-state icon="exclamation-triangle" title="Tidak ada data kritisitas"
                    description="Belum ada data kritisitas yang cocok dengan filter." />
            @else
                <x-organisms.data-panel>
                    <x-slot:filter>
                        <div class="flex-1">
                            <flux:input wire:model.live.debounce="searchKritisitas" icon="magnifying-glass"
                                placeholder="Cari kritisitas atau deskripsi..." clearable />
                        </div>
                    </x-slot:filter>

                    <x-organisms.table>
                        <x-slot:headings>
                            <x-atoms.table-heading class="w-16">ID</x-atoms.table-heading>
                            <x-atoms.table-heading>Kritisitas</x-atoms.table-heading>
                            <x-atoms.table-heading>Deskripsi</x-atoms.table-heading>
                            <x-atoms.table-heading class="w-20"></x-atoms.table-heading>
                        </x-slot:headings>

                        @foreach ($kritisitasList as $item)
                            <x-molecules.table-row wire:key="kritisitas-{{ $item->id }}">
                                <x-atoms.table-cell nowrap>
                                    <span class="font-mono text-xs font-bold text-zinc-500 dark:text-primary-dark-400">
                                        {{ $item->id }}
                                    </span>
                                </x-atoms.table-cell>
                                <x-atoms.table-cell>
                                    <span class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">
                                        {{ $item->kritisitas }}
                                    </span>
                                </x-atoms.table-cell>
                                <x-atoms.table-cell class="text-zinc-500 dark:text-primary-dark-400">
                                    {{ $item->deskripsi ?: '—' }}
                                </x-atoms.table-cell>
                                <x-atoms.table-cell :action="true">
                                    <div class="flex items-center justify-end gap-1">
                                        <x-atoms.button size="xs" variant="ghost" icon="pencil-square"
                                            wire:click="openEditKritisitas({{ $item->id }})"
                                            class="opacity-0 transition-opacity group-hover:opacity-100" />
                                        <x-atoms.button size="xs" variant="ghost" icon="trash"
                                            wire:click="confirmDeleteKritisitas({{ $item->id }}, '{{ addslashes($item->kritisitas) }}')"
                                            class="text-red-400 opacity-0 transition-opacity group-hover:opacity-100 hover:text-red-600" />
                                    </div>
                                </x-atoms.table-cell>
                            </x-molecules.table-row>
                        @endforeach
                    </x-organisms.table>

                    <x-slot:footer>
                        @if ($kritisitasList->hasPages())
                            {{ $kritisitasList->links() }}
                        @endif
                    </x-slot:footer>
                </x-organisms.data-panel>
            @endif
        @endif
    @endif

    {{-- ================================================================ --}}
    {{-- Modal: Tambah / Edit Alergi                                       --}}
    {{-- ================================================================ --}}
    <x-organisms.modal wire:model="showAllergyModal" maxWidth="lg" title="">
        <div class="space-y-4">
            <div class="flex items-center gap-4">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900/30">
                    <flux:icon.shield-exclamation class="size-5 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <flux:heading size="lg">{{ $editingAllergyId ? 'Edit Alergi' : 'Tambah Alergi' }}
                    </flux:heading>
                    <flux:subheading>{{ $editingAllergyId ? 'Ubah data alergi.' : 'Tambahkan data alergi baru.' }}
                    </flux:subheading>
                </div>
            </div>

            <div class="space-y-3 border-t border-zinc-100 pt-4 dark:border-primary-dark-700">
                <flux:field>
                    <flux:label>Nama Alergi <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="allergyName" placeholder="Contoh: Penisilin, Udang, Debu..." />
                    <flux:error name="allergyName" />
                </flux:field>

                {{-- Tipe: pilih dari yang ada atau tambah baru --}}
                <flux:field>
                    <flux:label>Tipe</flux:label>
                    <div
                        class="flex gap-4 rounded-lg border border-zinc-200 bg-zinc-50/50 px-3 py-2 dark:border-primary-dark-700 dark:bg-primary-dark-900/30">
                        <label
                            class="flex cursor-pointer items-center gap-2 text-sm text-zinc-700 dark:text-primary-dark-300">
                            <input type="radio" wire:model.live="allergyTipeMode" value="existing"
                                class="text-primary-600 focus:ring-primary-500" />
                            Pilih yang ada
                        </label>
                        <label
                            class="flex cursor-pointer items-center gap-2 text-sm text-zinc-700 dark:text-primary-dark-300">
                            <input type="radio" wire:model.live="allergyTipeMode" value="new"
                                class="text-primary-600 focus:ring-primary-500" />
                            Tambah baru
                        </label>
                    </div>
                    @if ($allergyTipeMode === 'existing')
                        <flux:select wire:model="allergyTipeSelected" class="mt-2">
                            <flux:select.option value="">— Tanpa tipe —</flux:select.option>
                            @foreach ($allergyTypes as $tipe)
                                <flux:select.option value="{{ $tipe }}">{{ $tipe }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    @else
                        <flux:input wire:model="allergyTipeNew" class="mt-2"
                            placeholder="Contoh: makanan, obat, lingkungan"
                            x-on:input="$el.value = $el.value.toLowerCase().replace(/[^a-z0-9]/g, '')" />
                        <flux:description>Hanya huruf kecil (a–z) dan angka, tanpa spasi atau simbol.</flux:description>
                        <flux:error name="allergyTipeNew" />
                    @endif
                </flux:field>

                <flux:field>
                    <flux:label>Keterangan</flux:label>
                    <flux:textarea wire:model="allergyKeterangan" rows="2"
                        placeholder="Keterangan tambahan (opsional)..." />
                    <flux:error name="allergyKeterangan" />
                </flux:field>
            </div>

            
        <x-slot:footer>
            <div class="flex justify-end gap-2 border-t border-zinc-100 pt-4 dark:border-primary-dark-700">
                <x-atoms.button wire:click="$set('showAllergyModal', false)" variant="ghost">Batal</x-atoms.button>
                <x-atoms.button wire:click="saveAllergy" variant="primary">
                    {{ $editingAllergyId ? 'Simpan Perubahan' : 'Tambahkan' }}
                </x-atoms.button>
            </div>
        </x-slot:footer>
    </div>
    </x-organisms.modal>

    {{-- Modal: Hapus Alergi --}}
    <x-organisms.modal wire:model="showDeleteAllergyModal" maxWidth="sm" title="">
        <div class="space-y-4">
            <div class="flex items-center gap-4">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                    <flux:icon.trash class="size-5 text-red-600 dark:text-red-400" />
                </div>
                <div>
                    <flux:heading size="lg">Hapus Alergi</flux:heading>
                    <flux:subheading>Tindakan ini tidak dapat dibatalkan.</flux:subheading>
                </div>
            </div>
            <p class="text-sm text-zinc-600 dark:text-primary-dark-400">
                Yakin ingin menghapus alergi <strong>{{ $deletingAllergyName }}</strong>?
            </p>
            
        <x-slot:footer>
            <div class="flex justify-end gap-2 border-t border-zinc-100 pt-4 dark:border-primary-dark-700">
                <x-atoms.button wire:click="$set('showDeleteAllergyModal', false)" variant="ghost">Batal</x-atoms.button>
                <x-atoms.button wire:click="deleteAllergy" variant="danger" icon="trash">Hapus</x-atoms.button>
            </div>
        </x-slot:footer>
    </div>
    </x-organisms.modal>

    {{-- ================================================================ --}}
    {{-- Modal: Tambah / Edit Reaksi Alergi                               --}}
    {{-- ================================================================ --}}
    <x-organisms.modal wire:model="showReactionModal" maxWidth="lg" title="">
        <div class="space-y-4">
            <div class="flex items-center gap-4">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900/30">
                    <flux:icon.bolt class="size-5 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <flux:heading size="lg">
                        {{ $editingReactionId ? 'Edit Reaksi Alergi' : 'Tambah Reaksi Alergi' }}</flux:heading>
                    <flux:subheading>
                        {{ $editingReactionId ? 'Ubah data reaksi alergi.' : 'Tambahkan data reaksi baru.' }}
                    </flux:subheading>
                </div>
            </div>

            <div class="space-y-3 border-t border-zinc-100 pt-4 dark:border-primary-dark-700">
                <flux:field>
                    <flux:label>Nama Reaksi <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="reactionName" placeholder="Contoh: Gatal-gatal, Sesak napas..." />
                    <flux:error name="reactionName" />
                </flux:field>

                {{-- Kategori: pilih dari yang ada atau tambah baru --}}
                <flux:field>
                    <flux:label>Kategori</flux:label>
                    <div
                        class="flex gap-4 rounded-lg border border-zinc-200 bg-zinc-50/50 px-3 py-2 dark:border-primary-dark-700 dark:bg-primary-dark-900/30">
                        <label
                            class="flex cursor-pointer items-center gap-2 text-sm text-zinc-700 dark:text-primary-dark-300">
                            <input type="radio" wire:model.live="reactionKategoriMode" value="existing"
                                class="text-primary-600 focus:ring-primary-500" />
                            Pilih yang ada
                        </label>
                        <label
                            class="flex cursor-pointer items-center gap-2 text-sm text-zinc-700 dark:text-primary-dark-300">
                            <input type="radio" wire:model.live="reactionKategoriMode" value="new"
                                class="text-primary-600 focus:ring-primary-500" />
                            Tambah baru
                        </label>
                    </div>
                    @if ($reactionKategoriMode === 'existing')
                        <flux:select wire:model="reactionKategoriSelected" class="mt-2">
                            <flux:select.option value="">— Tanpa kategori —</flux:select.option>
                            @foreach ($reactionCategories as $cat)
                                <flux:select.option value="{{ $cat }}">{{ $cat }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    @else
                        <flux:input wire:model="reactionKategoriNew" class="mt-2"
                            placeholder="Contoh: kulit, pernapasan, pencernaan"
                            x-on:input="$el.value = $el.value.toLowerCase().replace(/[^a-z0-9]/g, '')" />
                        <flux:description>Hanya huruf kecil (a–z) dan angka, tanpa spasi atau simbol.</flux:description>
                        <flux:error name="reactionKategoriNew" />
                    @endif
                </flux:field>

                <flux:field>
                    <flux:label>Keterangan</flux:label>
                    <flux:textarea wire:model="reactionKeterangan" rows="2"
                        placeholder="Keterangan tambahan (opsional)..." />
                    <flux:error name="reactionKeterangan" />
                </flux:field>
            </div>

            
        <x-slot:footer>
            <div class="flex justify-end gap-2 border-t border-zinc-100 pt-4 dark:border-primary-dark-700">
                <x-atoms.button wire:click="$set('showReactionModal', false)" variant="ghost">Batal</x-atoms.button>
                <x-atoms.button wire:click="saveReaction" variant="primary">
                    {{ $editingReactionId ? 'Simpan Perubahan' : 'Tambahkan' }}
                </x-atoms.button>
            </div>
        </x-slot:footer>
    </div>
    </x-organisms.modal>

    {{-- Modal: Hapus Reaksi Alergi --}}
    <x-organisms.modal wire:model="showDeleteReactionModal" maxWidth="sm" title="">
        <div class="space-y-4">
            <div class="flex items-center gap-4">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                    <flux:icon.trash class="size-5 text-red-600 dark:text-red-400" />
                </div>
                <div>
                    <flux:heading size="lg">Hapus Reaksi Alergi</flux:heading>
                    <flux:subheading>Tindakan ini tidak dapat dibatalkan.</flux:subheading>
                </div>
            </div>
            <p class="text-sm text-zinc-600 dark:text-primary-dark-400">
                Yakin ingin menghapus reaksi <strong>{{ $deletingReactionName }}</strong>?
            </p>
            
        <x-slot:footer>
            <div class="flex justify-end gap-2 border-t border-zinc-100 pt-4 dark:border-primary-dark-700">
                <x-atoms.button wire:click="$set('showDeleteReactionModal', false)" variant="ghost">Batal</x-atoms.button>
                <x-atoms.button wire:click="deleteReaction" variant="danger" icon="trash">Hapus</x-atoms.button>
            </div>
        </x-slot:footer>
    </div>
    </x-organisms.modal>

    {{-- ================================================================ --}}
    {{-- Modal: Tambah / Edit Tingkat Keparahan                           --}}
    {{-- ================================================================ --}}
    <x-organisms.modal wire:model="showKeparahanModal" maxWidth="lg" title="">
        <div class="space-y-4">
            <div class="flex items-center gap-4">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900/30">
                    <flux:icon.chart-bar class="size-5 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <flux:heading size="lg">
                        {{ $editingKeparahanId ? 'Edit Tingkat Keparahan' : 'Tambah Tingkat Keparahan' }}
                    </flux:heading>
                    <flux:subheading>
                        {{ $editingKeparahanId ? 'Ubah data tingkat keparahan.' : 'Tambahkan tingkat keparahan baru.' }}
                    </flux:subheading>
                </div>
            </div>

            <div class="space-y-3 border-t border-zinc-100 pt-4 dark:border-primary-dark-700">
                <flux:field>
                    <flux:label>Tingkat Keparahan <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="keparahanName" placeholder="Contoh: Ringan, Sedang, Berat..." />
                    <flux:error name="keparahanName" />
                </flux:field>
                <flux:field>
                    <flux:label>Deskripsi</flux:label>
                    <flux:textarea wire:model="keparahanDesc" rows="2"
                        placeholder="Deskripsi tingkat keparahan (opsional)..." />
                    <flux:error name="keparahanDesc" />
                </flux:field>
            </div>

            
        <x-slot:footer>
            <div class="flex justify-end gap-2 border-t border-zinc-100 pt-4 dark:border-primary-dark-700">
                <x-atoms.button wire:click="$set('showKeparahanModal', false)" variant="ghost">Batal</x-atoms.button>
                <x-atoms.button wire:click="saveKeparahan" variant="primary">
                    {{ $editingKeparahanId ? 'Simpan Perubahan' : 'Tambahkan' }}
                </x-atoms.button>
            </div>
        </x-slot:footer>
    </div>
    </x-organisms.modal>

    {{-- Modal: Hapus Tingkat Keparahan --}}
    <x-organisms.modal wire:model="showDeleteKeparahanModal" maxWidth="sm" title="">
        <div class="space-y-4">
            <div class="flex items-center gap-4">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                    <flux:icon.trash class="size-5 text-red-600 dark:text-red-400" />
                </div>
                <div>
                    <flux:heading size="lg">Hapus Tingkat Keparahan</flux:heading>
                    <flux:subheading>Tindakan ini tidak dapat dibatalkan.</flux:subheading>
                </div>
            </div>
            <p class="text-sm text-zinc-600 dark:text-primary-dark-400">
                Yakin ingin menghapus tingkat keparahan <strong>{{ $deletingKeparahanName }}</strong>?
            </p>
            
        <x-slot:footer>
            <div class="flex justify-end gap-2 border-t border-zinc-100 pt-4 dark:border-primary-dark-700">
                <x-atoms.button wire:click="$set('showDeleteKeparahanModal', false)" variant="ghost">Batal</x-atoms.button>
                <x-atoms.button wire:click="deleteKeparahan" variant="danger" icon="trash">Hapus</x-atoms.button>
            </div>
        </x-slot:footer>
    </div>
    </x-organisms.modal>

    {{-- ================================================================ --}}
    {{-- Modal: Tambah / Edit Kritisitas                                  --}}
    {{-- ================================================================ --}}
    <x-organisms.modal wire:model="showKritisitasModal" maxWidth="lg" title="">
        <div class="space-y-4">
            <div class="flex items-center gap-4">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900/30">
                    <flux:icon.exclamation-triangle class="size-5 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <flux:heading size="lg">
                        {{ $editingKritisitasId ? 'Edit Kritisitas' : 'Tambah Kritisitas' }}
                    </flux:heading>
                    <flux:subheading>
                        {{ $editingKritisitasId ? 'Ubah data kritisitas.' : 'Tambahkan kritisitas baru.' }}
                    </flux:subheading>
                </div>
            </div>

            <div class="space-y-3 border-t border-zinc-100 pt-4 dark:border-primary-dark-700">
                <flux:field>
                    <flux:label>Kritisitas <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="kritisitasName" placeholder="Contoh: Rendah, Sedang, Tinggi..." />
                    <flux:error name="kritisitasName" />
                </flux:field>
                <flux:field>
                    <flux:label>Deskripsi</flux:label>
                    <flux:textarea wire:model="kritisitasDesc" rows="2"
                        placeholder="Deskripsi kritisitas (opsional)..." />
                    <flux:error name="kritisitasDesc" />
                </flux:field>
            </div>

            
        <x-slot:footer>
            <div class="flex justify-end gap-2 border-t border-zinc-100 pt-4 dark:border-primary-dark-700">
                <x-atoms.button wire:click="$set('showKritisitasModal', false)" variant="ghost">Batal</x-atoms.button>
                <x-atoms.button wire:click="saveKritisitas" variant="primary">
                    {{ $editingKritisitasId ? 'Simpan Perubahan' : 'Tambahkan' }}
                </x-atoms.button>
            </div>
        </x-slot:footer>
    </div>
    </x-organisms.modal>

    {{-- Modal: Hapus Kritisitas --}}
    <x-organisms.modal wire:model="showDeleteKritisitasModal" maxWidth="sm" title="">
        <div class="space-y-4">
            <div class="flex items-center gap-4">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                    <flux:icon.trash class="size-5 text-red-600 dark:text-red-400" />
                </div>
                <div>
                    <flux:heading size="lg">Hapus Kritisitas</flux:heading>
                    <flux:subheading>Tindakan ini tidak dapat dibatalkan.</flux:subheading>
                </div>
            </div>
            <p class="text-sm text-zinc-600 dark:text-primary-dark-400">
                Yakin ingin menghapus kritisitas <strong>{{ $deletingKritisitasName }}</strong>?
            </p>
            
        <x-slot:footer>
            <div class="flex justify-end gap-2 border-t border-zinc-100 pt-4 dark:border-primary-dark-700">
                <x-atoms.button wire:click="$set('showDeleteKritisitasModal', false)" variant="ghost">Batal</x-atoms.button>
                <x-atoms.button wire:click="deleteKritisitas" variant="danger" icon="trash">Hapus</x-atoms.button>
            </div>
        </x-slot:footer>
    </div>
    </x-organisms.modal>

    {{-- ================================================================ --}}
    {{-- Modal: Daftar Pasien dengan Alergi                               --}}
    {{-- ================================================================ --}}
    <x-organisms.modal wire:model="showPatientsModal" maxWidth="4xl" title="">
        <div class="space-y-4">
            <div class="flex items-center gap-4">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-rose-100 dark:bg-rose-900/30">
                    <flux:icon.user-group class="size-5 text-rose-600 dark:text-rose-400" />
                </div>
                <div>
                    <flux:heading size="lg">Pasien dengan Alergi</flux:heading>
                    <flux:subheading>{{ $viewingAllergyName }}</flux:subheading>
                </div>
            </div>

            @if ($allergyPatients->isEmpty())
                <div class="py-8 text-center text-sm text-zinc-400 dark:text-primary-dark-500">
                    Tidak ada data pasien untuk alergi ini.
                </div>
            @else
                <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-primary-dark-700">
                    <div class="max-h-96 overflow-y-auto">
                        <table class="min-w-full">
                            <thead class="sticky top-0 z-10">
                                <tr
                                    class="border-b border-zinc-100 bg-zinc-50/90 backdrop-blur-sm dark:border-primary-dark-700/60 dark:bg-primary-dark-900/80">
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500">
                                        Pasien</th>
                                    <th
                                        class="w-28 px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500">
                                        Tanggal</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500">
                                        Reaksi</th>
                                    <th
                                        class="w-32 px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500">
                                        Keparahan</th>
                                    <th
                                        class="w-28 px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500">
                                        Kritisitas</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500">
                                        No. Rawat Ref.</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500">
                                        Catatan</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500">
                                        Diinput Oleh</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100/80 dark:divide-primary-dark-700/40">
                                @foreach ($allergyPatients as $ap)
                                    <tr
                                        class="bg-white transition-colors hover:bg-zinc-50/60 dark:bg-primary-dark-800 dark:hover:bg-primary-dark-700/20">
                                        <td class="px-4 py-3">
                                            <p
                                                class="font-mono text-xs font-bold text-zinc-800 dark:text-primary-dark-100">
                                                {{ $ap->no_rkm_medis }}</p>
                                            <p class="mt-0.5 text-xs text-zinc-400 dark:text-primary-dark-500">
                                                {{ $patientNames->get($ap->no_rkm_medis, '—') }}</p>
                                        </td>
                                        <td
                                            class="whitespace-nowrap px-4 py-3 text-xs text-zinc-600 dark:text-primary-dark-400">
                                            {{ $ap->tanggal ? \Carbon\Carbon::parse($ap->tanggal)->format('d M Y') : '—' }}
                                            @if ($ap->jam)
                                                <span class="text-zinc-400"> {{ substr($ap->jam, 0, 5) }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-xs text-zinc-600 dark:text-primary-dark-400">
                                            {{ $ap->reaksi?->nama_reaksi ?? '—' }}
                                        </td>
                                        <td class="px-4 py-3">
                                            @if ($ap->tingkatKeparahan)
                                                <flux:badge color="amber" size="sm">
                                                    {{ $ap->tingkatKeparahan->keparahan }}
                                                </flux:badge>
                                            @else
                                                <span class="text-xs text-zinc-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            @if ($ap->kritisitas)
                                                <flux:badge color="red" size="sm">
                                                    {{ $ap->kritisitas->kritisitas }}
                                                </flux:badge>
                                            @else
                                                <span class="text-xs text-zinc-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            @if ($ap->no_rawat_ref)
                                                <span
                                                    class="font-mono text-xs text-zinc-600 dark:text-primary-dark-400">
                                                    {{ $ap->no_rawat_ref }}
                                                </span>
                                            @else
                                                <span class="text-xs text-zinc-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-xs text-zinc-500 dark:text-primary-dark-400">
                                            {{ $ap->catatan ?: '—' }}</td>
                                        <td class="px-4 py-3">
                                            @if ($ap->nip)
                                                <p
                                                    class="font-mono text-xs font-semibold text-zinc-700 dark:text-primary-dark-300">
                                                    {{ $ap->nip }}</p>
                                                <p class="mt-0.5 text-xs text-zinc-400 dark:text-primary-dark-500">
                                                    {{ $employeeNames->get($ap->nip, '—') }}</p>
                                            @else
                                                <span class="text-xs text-zinc-400">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <p class="text-xs text-zinc-400 dark:text-primary-dark-500">{{ $allergyPatients->count() }} data
                    riwayat
                    ditemukan</p>
            @endif

            
        <x-slot:footer>
            <div class="flex justify-end border-t border-zinc-100 pt-4 dark:border-primary-dark-700">
                <x-atoms.button wire:click="$set('showPatientsModal', false)" variant="ghost">Tutup</x-atoms.button>
            </div>
        </x-slot:footer>
    </div>
    </x-organisms.modal>
</div>
