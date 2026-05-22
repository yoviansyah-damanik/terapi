<?php

use App\Models\Simrs\Bangsal;
use App\Models\Simrs\BangsalGroup;
use App\Models\Simrs\BangsalJenisKelamin;
use App\Models\Simrs\DetailBangsalGroup;
use App\Models\Simrs\KategoriBangsalPelayanan;
use App\Models\Simrs\KategoriBangsalUsia;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Master Data — Bangsal')] class extends Component {
    #[Url]
    public string $tab = 'grouped';

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterStatus = '';

    // -------------------------------------------------------------------------
    // State modal group
    // -------------------------------------------------------------------------

    public bool $showGroupModal = false;
    public ?string $editingGroupId = null;
    public string $groupName = '';
    public string $groupStatus = '1';

    public bool $showDeleteGroupModal = false;
    public ?string $deletingGroupId = null;
    public string $deletingGroupName = '';

    // -------------------------------------------------------------------------
    // State modal tambah bangsal ke group
    // -------------------------------------------------------------------------

    public bool $showAddBangsalModal = false;
    public ?string $targetGroupId = null;
    public string $bangsalSearch = '';
    public array $selectedBangsals = [];

    public bool $showRemoveBangsalModal = false;
    public ?string $removingKdBangsal = null;
    public ?string $removingFromGroupId = null;
    public string $removingBangsalLabel = '';

    // -------------------------------------------------------------------------
    // State modal mapping kategori
    // -------------------------------------------------------------------------

    public bool $showMappingModal = false;
    public ?string $mappingKdBangsal = null;
    public string $mappingBangsalLabel = '';
    public string $mappingType = ''; // 'pelayanan' | 'usia' | 'jenis_kelamin'
    public ?string $selectedPelayanan = null;
    public ?string $selectedUsia = null;
    public ?string $selectedJenisKelamin = null;

    // -------------------------------------------------------------------------
    // State modal CRUD kategori pelayanan
    // -------------------------------------------------------------------------

    public bool $showPelayananModal = false;
    public ?string $editingPelayananId = null;
    public string $pelayananKode = '';
    public string $pelayananNama = '';
    public string $pelayananKeterangan = '';

    public bool $showDeletePelayananModal = false;
    public ?string $deletingPelayananId = null;
    public string $deletingPelayananLabel = '';

    // -------------------------------------------------------------------------
    // State modal CRUD kategori usia
    // -------------------------------------------------------------------------

    public bool $showUsiaModal = false;
    public ?string $editingUsiaId = null;
    public string $usiaKode = '';
    public string $usiaNama = '';
    public string $usiaKeterangan = '';

    public bool $showDeleteUsiaModal = false;
    public ?string $deletingUsiaId = null;
    public string $deletingUsiaLabel = '';

    // -------------------------------------------------------------------------

    public function switchTab(string $tab): void
    {
        $this->tab = $tab;
        $this->search = '';
    }

    public function updatedBangsalSearch(): void
    {
        $this->selectedBangsals = [];
    }

    // -------------------------------------------------------------------------
    // Group CRUD
    // -------------------------------------------------------------------------

    public function openCreateGroup(): void
    {
        $this->editingGroupId = null;
        $this->groupName = '';
        $this->groupStatus = '1';
        $this->showGroupModal = true;
    }

    public function openEditGroup(string $groupId, string $groupName, string $groupStatus): void
    {
        $this->editingGroupId = $groupId;
        $this->groupName = $groupName;
        $this->groupStatus = $groupStatus;
        $this->showGroupModal = true;
    }

    public function saveGroup(): void
    {
        $this->validate(['groupName' => 'required|string|max:100']);

        try {
            if ($this->editingGroupId) {
                BangsalGroup::where('id_group', $this->editingGroupId)->update([
                    'nama_group' => $this->groupName,
                    'status' => $this->groupStatus,
                ]);
                $this->toastSuccess('Grup berhasil diperbarui.');
            } else {
                $lastId = BangsalGroup::max('id_group') ?? '0';
                $newId = (string) ((int) $lastId + 1);
                while (BangsalGroup::where('id_group', $newId)->exists()) {
                    $newId = (string) ((int) $newId + 1);
                }
                BangsalGroup::create([
                    'id_group' => $newId,
                    'nama_group' => $this->groupName,
                    'status' => $this->groupStatus,
                ]);
                $this->toastSuccess('Grup berhasil dibuat.');
            }
        } catch (\Exception $e) {
            $this->toastError('Gagal menyimpan grup: ' . $e->getMessage());
            return;
        }

        $this->showGroupModal = false;
    }

    public function confirmDeleteGroup(string $groupId, string $groupName): void
    {
        $this->deletingGroupId = $groupId;
        $this->deletingGroupName = $groupName;
        $this->showDeleteGroupModal = true;
    }

    public function deleteGroup(): void
    {
        if (!$this->deletingGroupId) {
            return;
        }

        try {
            DetailBangsalGroup::where('id_group', $this->deletingGroupId)->delete();
            BangsalGroup::where('id_group', $this->deletingGroupId)->delete();
            $this->toastSuccess("Grup \"{$this->deletingGroupName}\" berhasil dihapus.");
        } catch (\Exception $e) {
            $this->toastError('Gagal menghapus grup: ' . $e->getMessage());
        }

        $this->showDeleteGroupModal = false;
        $this->deletingGroupId = null;
        $this->deletingGroupName = '';
    }

    // -------------------------------------------------------------------------
    // Anggota bangsal dalam group
    // -------------------------------------------------------------------------

    public function openAddBangsal(string $groupId): void
    {
        $this->targetGroupId = $groupId;
        $this->bangsalSearch = '';
        $this->selectedBangsals = [];
        $this->showAddBangsalModal = true;
    }

    public function toggleBangsal(string $kdBangsal): void
    {
        if (in_array($kdBangsal, $this->selectedBangsals)) {
            $this->selectedBangsals = array_values(array_filter($this->selectedBangsals, fn($k) => $k !== $kdBangsal));
        } else {
            $this->selectedBangsals[] = $kdBangsal;
        }
    }

    public function addBangsalsToGroup(): void
    {
        if (!$this->targetGroupId || empty($this->selectedBangsals)) {
            $this->toastError('Pilih minimal satu bangsal.');
            return;
        }

        try {
            $existing = DetailBangsalGroup::where('id_group', $this->targetGroupId)->pluck('kd_bangsal')->toArray();

            // Bangsal yang sudah tergabung di grup lain tidak boleh ditambahkan
            $otherGroupKds = DetailBangsalGroup::where('id_group', '!=', $this->targetGroupId)->pluck('kd_bangsal')->toArray();

            $toInsert = array_values(array_filter(
                $this->selectedBangsals,
                fn($k) => !in_array($k, $existing) && !in_array($k, $otherGroupKds),
            ));

            foreach ($toInsert as $kdBangsal) {
                DetailBangsalGroup::create(['id_group' => $this->targetGroupId, 'kd_bangsal' => $kdBangsal]);
            }

            $added = count($toInsert);
            $skipped = count($this->selectedBangsals) - $added;
            $msg = "{$added} bangsal berhasil ditambahkan.";
            if ($skipped > 0) {
                $msg .= " {$skipped} dilewati (sudah ada atau sudah tergabung di grup lain).";
            }
            $this->toastSuccess($msg);
        } catch (\Exception $e) {
            $this->toastError('Gagal menambahkan bangsal: ' . $e->getMessage());
            return;
        }

        $this->showAddBangsalModal = false;
        $this->selectedBangsals = [];
        $this->targetGroupId = null;
    }

    public function confirmRemoveBangsal(string $kdBangsal, string $groupId, string $label): void
    {
        $this->removingKdBangsal = $kdBangsal;
        $this->removingFromGroupId = $groupId;
        $this->removingBangsalLabel = $label;
        $this->showRemoveBangsalModal = true;
    }

    public function removeBangsalFromGroup(): void
    {
        if (!$this->removingKdBangsal || !$this->removingFromGroupId) {
            return;
        }

        try {
            DetailBangsalGroup::where('id_group', $this->removingFromGroupId)->where('kd_bangsal', $this->removingKdBangsal)->delete();
            $this->toastSuccess("Bangsal \"{$this->removingBangsalLabel}\" berhasil dilepas dari grup.");
        } catch (\Exception $e) {
            $this->toastError('Gagal melepas bangsal: ' . $e->getMessage());
        }

        $this->showRemoveBangsalModal = false;
        $this->removingKdBangsal = null;
        $this->removingFromGroupId = null;
        $this->removingBangsalLabel = '';
    }

    // -------------------------------------------------------------------------
    // Mapping kategori per bangsal
    // -------------------------------------------------------------------------

    public function openMappingModal(string $type, string $kdBangsal, string $label): void
    {
        $this->mappingType = $type;
        $this->mappingKdBangsal = $kdBangsal;
        $this->mappingBangsalLabel = $label;

        match ($type) {
            'pelayanan'     => $this->selectedPelayanan    = DB::connection('simrs')->table('bangsal_per_pelayanan')->where('kd_bangsal', $kdBangsal)->value('kd_kategori'),
            'usia'          => $this->selectedUsia          = DB::connection('simrs')->table('bangsal_per_usia')->where('kd_bangsal', $kdBangsal)->value('kd_kategori'),
            'jenis_kelamin' => $this->selectedJenisKelamin  = DB::connection('simrs')->table('bangsal_per_jenis_kelamin')->where('kd_bangsal', $kdBangsal)->value('jenis_kelamin'),
            default         => null,
        };

        $this->showMappingModal = true;
    }

    public function saveMapping(): void
    {
        if (!$this->mappingKdBangsal || !$this->mappingType) {
            return;
        }

        try {
            $kd = $this->mappingKdBangsal;
            match ($this->mappingType) {
                'pelayanan'     => $this->savePelayananMapping($kd),
                'usia'          => $this->saveUsiaMapping($kd),
                'jenis_kelamin' => $this->saveJenisKelaminMapping($kd),
                default         => null,
            };
            $this->toastSuccess("Mapping bangsal \"{$this->mappingBangsalLabel}\" berhasil disimpan.");
        } catch (\Exception $e) {
            $this->toastError('Gagal menyimpan mapping: ' . $e->getMessage());
            return;
        }

        $this->showMappingModal = false;
        $this->mappingKdBangsal = null;
        $this->mappingType = '';
    }

    private function savePelayananMapping(string $kd): void
    {
        DB::connection('simrs')->table('bangsal_per_pelayanan')->where('kd_bangsal', $kd)->delete();
        if ($this->selectedPelayanan) {
            DB::connection('simrs')->table('bangsal_per_pelayanan')->insert(['kd_bangsal' => $kd, 'kd_kategori' => $this->selectedPelayanan]);
        }
    }

    private function saveUsiaMapping(string $kd): void
    {
        DB::connection('simrs')->table('bangsal_per_usia')->where('kd_bangsal', $kd)->delete();
        if ($this->selectedUsia) {
            DB::connection('simrs')->table('bangsal_per_usia')->insert(['kd_bangsal' => $kd, 'kd_kategori' => $this->selectedUsia]);
        }
    }

    private function saveJenisKelaminMapping(string $kd): void
    {
        DB::connection('simrs')->table('bangsal_per_jenis_kelamin')->where('kd_bangsal', $kd)->delete();
        if ($this->selectedJenisKelamin) {
            DB::connection('simrs')->table('bangsal_per_jenis_kelamin')->insert(['kd_bangsal' => $kd, 'jenis_kelamin' => $this->selectedJenisKelamin]);
        }
    }

    // -------------------------------------------------------------------------
    // CRUD Kategori Pelayanan
    // -------------------------------------------------------------------------

    public function openCreatePelayanan(): void
    {
        $this->editingPelayananId = null;
        $this->pelayananKode = '';
        $this->pelayananNama = '';
        $this->pelayananKeterangan = '';
        $this->showPelayananModal = true;
    }

    public function openEditPelayanan(string $id, string $kode, string $nama, string $keterangan): void
    {
        $this->editingPelayananId = $id;
        $this->pelayananKode = $kode;
        $this->pelayananNama = $nama;
        $this->pelayananKeterangan = $keterangan;
        $this->showPelayananModal = true;
    }

    public function savePelayanan(): void
    {
        $this->validate([
            'pelayananKode' => 'required|string|max:20',
            'pelayananNama' => 'required|string|max:100',
        ]);

        try {
            if ($this->editingPelayananId) {
                KategoriBangsalPelayanan::where('kd_kategori', $this->editingPelayananId)->update([
                    'nm_kategori' => $this->pelayananNama,
                    'keterangan' => $this->pelayananKeterangan,
                ]);
                $this->toastSuccess('Kategori pelayanan berhasil diperbarui.');
            } else {
                if (KategoriBangsalPelayanan::where('kd_kategori', $this->pelayananKode)->exists()) {
                    $this->addError('pelayananKode', 'Kode sudah digunakan.');
                    return;
                }
                KategoriBangsalPelayanan::create([
                    'kd_kategori' => $this->pelayananKode,
                    'nm_kategori' => $this->pelayananNama,
                    'keterangan' => $this->pelayananKeterangan,
                ]);
                $this->toastSuccess('Kategori pelayanan berhasil ditambahkan.');
            }
        } catch (\Exception $e) {
            $this->toastError('Gagal menyimpan: ' . $e->getMessage());
            return;
        }

        $this->showPelayananModal = false;
    }

    public function confirmDeletePelayanan(string $id, string $label): void
    {
        $this->deletingPelayananId = $id;
        $this->deletingPelayananLabel = $label;
        $this->showDeletePelayananModal = true;
    }

    public function deletePelayanan(): void
    {
        if (!$this->deletingPelayananId) {
            return;
        }

        try {
            DB::connection('simrs')->table('bangsal_per_pelayanan')->where('kd_kategori', $this->deletingPelayananId)->delete();
            KategoriBangsalPelayanan::where('kd_kategori', $this->deletingPelayananId)->delete();
            $this->toastSuccess("Kategori \"{$this->deletingPelayananLabel}\" berhasil dihapus.");
        } catch (\Exception $e) {
            $this->toastError('Gagal menghapus: ' . $e->getMessage());
        }

        $this->showDeletePelayananModal = false;
        $this->deletingPelayananId = null;
    }

    // -------------------------------------------------------------------------
    // CRUD Kategori Usia
    // -------------------------------------------------------------------------

    public function openCreateUsia(): void
    {
        $this->editingUsiaId = null;
        $this->usiaKode = '';
        $this->usiaNama = '';
        $this->usiaKeterangan = '';
        $this->showUsiaModal = true;
    }

    public function openEditUsia(string $id, string $kode, string $nama, string $keterangan): void
    {
        $this->editingUsiaId = $id;
        $this->usiaKode = $kode;
        $this->usiaNama = $nama;
        $this->usiaKeterangan = $keterangan;
        $this->showUsiaModal = true;
    }

    public function saveUsia(): void
    {
        $this->validate([
            'usiaKode' => 'required|string|max:20',
            'usiaNama' => 'required|string|max:100',
        ]);

        try {
            if ($this->editingUsiaId) {
                KategoriBangsalUsia::where('kd_kategori', $this->editingUsiaId)->update([
                    'nm_kategori' => $this->usiaNama,
                    'keterangan' => $this->usiaKeterangan,
                ]);
                $this->toastSuccess('Kategori usia berhasil diperbarui.');
            } else {
                if (KategoriBangsalUsia::where('kd_kategori', $this->usiaKode)->exists()) {
                    $this->addError('usiaKode', 'Kode sudah digunakan.');
                    return;
                }
                KategoriBangsalUsia::create([
                    'kd_kategori' => $this->usiaKode,
                    'nm_kategori' => $this->usiaNama,
                    'keterangan' => $this->usiaKeterangan,
                ]);
                $this->toastSuccess('Kategori usia berhasil ditambahkan.');
            }
        } catch (\Exception $e) {
            $this->toastError('Gagal menyimpan: ' . $e->getMessage());
            return;
        }

        $this->showUsiaModal = false;
    }

    public function confirmDeleteUsia(string $id, string $label): void
    {
        $this->deletingUsiaId = $id;
        $this->deletingUsiaLabel = $label;
        $this->showDeleteUsiaModal = true;
    }

    public function deleteUsia(): void
    {
        if (!$this->deletingUsiaId) {
            return;
        }

        try {
            DB::connection('simrs')->table('bangsal_per_usia')->where('kd_kategori', $this->deletingUsiaId)->delete();
            KategoriBangsalUsia::where('kd_kategori', $this->deletingUsiaId)->delete();
            $this->toastSuccess("Kategori \"{$this->deletingUsiaLabel}\" berhasil dihapus.");
        } catch (\Exception $e) {
            $this->toastError('Gagal menghapus: ' . $e->getMessage());
        }

        $this->showDeleteUsiaModal = false;
        $this->deletingUsiaId = null;
    }

    // -------------------------------------------------------------------------

    public function with(): array
    {
        $simrsError = null;
        $groups = collect();
        $ungrouped = collect();
        $availableBangsals  = collect();
        $otherAssignmentMap = collect(); // kd_bangsal → nama_group (untuk modal tambah bangsal)
        $availableFreeCount = 0;
        $availableMappedCount = 0;
        $totalBangsals = 0;
        $activeBangsals = 0;
        $ungroupedTotal = 0;

        try {
            $allBangsals = Bangsal::with(['kategoriBangsalPelayanan', 'kategoriBangsalUsia', 'jenisKelamin'])->get();

            $totalBangsals = $allBangsals->count();
            $activeBangsals = $allBangsals->where('status', 1)->count();

            $applyFilters = function ($collection) {
                return $collection->when($this->search, fn($c) => $c->filter(fn($b) => str_contains(strtolower($b->kd_bangsal), strtolower($this->search)) || str_contains(strtolower($b->nm_bangsal ?? ''), strtolower($this->search))))->when($this->filterStatus !== '', fn($c) => $c->filter(fn($b) => $this->filterStatus === '1' ? $b->status == 1 : $b->status != 1));
            };

            $assignedKds = DetailBangsalGroup::pluck('kd_bangsal')->unique()->toArray();

            $groups = BangsalGroup::with(['bangsals.kategoriBangsalPelayanan', 'bangsals.kategoriBangsalUsia', 'bangsals.jenisKelamin'])
                ->orderBy('id_group')
                ->get()
                ->map(function ($group) use ($applyFilters) {
                    $filtered = $applyFilters($group->bangsals)->values();
                    return (object) [
                        'id_group' => $group->id_group,
                        'nama_group' => $group->nama_group,
                        'status' => $group->status,
                        'bangsals' => $filtered,
                        'total' => $group->bangsals->count(),
                    ];
                })
                ->when($this->search, fn($c) => $c->filter(fn($g) => $g->bangsals->isNotEmpty() || str_contains(strtolower($g->nama_group), strtolower($this->search))));

            $ungroupedAll = $allBangsals->whereNotIn('kd_bangsal', $assignedKds);
            $ungroupedTotal = $ungroupedAll->count();
            $ungrouped = $applyFilters($ungroupedAll)->sortBy('kd_bangsal')->values();

            if ($this->showAddBangsalModal && $this->targetGroupId) {
                $alreadyInCurrentGroup = DetailBangsalGroup::where('id_group', $this->targetGroupId)
                    ->pluck('kd_bangsal')->flip();

                // Bangsal di grup LAIN: kd_bangsal → nama_group
                $otherRows = DetailBangsalGroup::where('id_group', '!=', $this->targetGroupId)
                    ->get(['kd_bangsal', 'id_group']);

                if ($otherRows->isNotEmpty()) {
                    $groupNames = BangsalGroup::whereIn('id_group', $otherRows->pluck('id_group')->unique())
                        ->pluck('nama_group', 'id_group');
                    $otherAssignmentMap = $otherRows->keyBy('kd_bangsal')
                        ->map(fn($r) => $groupNames->get($r->id_group) ?? "Grup #{$r->id_group}");
                }

                $availableBangsals = $allBangsals
                    ->reject(fn($b) => $alreadyInCurrentGroup->has($b->kd_bangsal))
                    ->when($this->bangsalSearch, fn($c) => $c->filter(
                        fn($b) => str_contains(strtolower($b->kd_bangsal), strtolower($this->bangsalSearch))
                            || str_contains(strtolower($b->nm_bangsal ?? ''), strtolower($this->bangsalSearch))
                    ))
                    ->sortBy(fn($b) => [$otherAssignmentMap->has($b->kd_bangsal) ? 1 : 0, $b->kd_bangsal])
                    ->values();

                $availableMappedCount = $availableBangsals->filter(fn($b) => $otherAssignmentMap->has($b->kd_bangsal))->count();
                $availableFreeCount   = $availableBangsals->count() - $availableMappedCount;
            }
        } catch (\Exception $e) {
            $simrsError = $e->getMessage();
        }

        $kategoriBangsalPelayanan = collect();
        $kategoriBangsalUsia = collect();

        try {
            $kategoriBangsalPelayanan = KategoriBangsalPelayanan::orderBy('kd_kategori')->get();
            $kategoriBangsalUsia = KategoriBangsalUsia::orderBy('kd_kategori')->get();
        } catch (\Exception) {
            // Jika koneksi SIMRS error, kategori tetap kosong
        }

        return compact('simrsError', 'groups', 'ungrouped', 'ungroupedTotal', 'availableBangsals', 'otherAssignmentMap', 'availableFreeCount', 'availableMappedCount', 'totalBangsals', 'activeBangsals', 'kategoriBangsalPelayanan', 'kategoriBangsalUsia');
    }
};

?>

@php
    function bangsalStatusClass(bool $active): string
    {
        return $active
            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
            : 'bg-zinc-100 text-zinc-500 dark:bg-primary-dark-700 dark:text-primary-dark-400';
    }

    function jkBadgeColor(?string $jk): string
    {
        return match ($jk) {
            'laki-laki' => 'blue',
            'perempuan' => 'rose',
            'semua'     => 'teal',
            default     => '',
        };
    }

    function jkLabel(?string $jk): string
    {
        return match ($jk) {
            'laki-laki' => 'Laki-laki',
            'perempuan' => 'Perempuan',
            'semua'     => 'Semua',
            default     => '',
        };
    }
@endphp

<div>
    <x-ui.page-header title="Master Data Bangsal"
        subtitle="Kelola pengelompokan, kategori, dan mapping bangsal rawat inap">
        <x-slot:actions>
            @if ($tab === 'grouped')
                <x-atoms.button wire:click="openCreateGroup" icon="plus" variant="primary">Tambah Grup</x-atoms.button>
            @elseif ($tab === 'pelayanan')
                <x-atoms.button wire:click="openCreatePelayanan" icon="plus" variant="primary">Tambah Kategori
                </x-atoms.button>
            @elseif ($tab === 'usia')
                <x-atoms.button wire:click="openCreateUsia" icon="plus" variant="primary">Tambah
                    Kategori</x-atoms.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Stats --}}
    @unless ($simrsError)
        <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div
                class="rounded-xl border border-zinc-200/80 bg-white px-4 py-3 shadow-sm dark:border-primary-dark-700/60 dark:bg-primary-dark-800">
                <p class="text-xs font-medium text-zinc-400 dark:text-primary-dark-500">Total Bangsal</p>
                <p class="mt-1 text-2xl font-bold text-zinc-800 dark:text-primary-dark-100">{{ $totalBangsals }}</p>
            </div>
            <div
                class="rounded-xl border border-zinc-200/80 bg-white px-4 py-3 shadow-sm dark:border-primary-dark-700/60 dark:bg-primary-dark-800">
                <p class="text-xs font-medium text-zinc-400 dark:text-primary-dark-500">Aktif</p>
                <p class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ $activeBangsals }}</p>
            </div>
            <div
                class="rounded-xl border border-zinc-200/80 bg-white px-4 py-3 shadow-sm dark:border-primary-dark-700/60 dark:bg-primary-dark-800">
                <p class="text-xs font-medium text-zinc-400 dark:text-primary-dark-500">Belum Tergrup</p>
                <p class="mt-1 text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $ungroupedTotal }}</p>
            </div>
            <div
                class="rounded-xl border border-zinc-200/80 bg-white px-4 py-3 shadow-sm dark:border-primary-dark-700/60 dark:bg-primary-dark-800">
                <p class="text-xs font-medium text-zinc-400 dark:text-primary-dark-500">Total Kategori</p>
                <p class="mt-1 text-2xl font-bold text-violet-600 dark:text-violet-400">
                    {{ $kategoriBangsalPelayanan->count() + $kategoriBangsalUsia->count() }}
                </p>
            </div>
        </div>
    @endunless

    {{-- Tab bar --}}
    <x-molecules.tabs class="mb-5">
        @foreach ([
            ['key' => 'grouped',   'label' => 'Tergrup',            'icon' => 'squares-2x2',      'badge' => $groups->count()],
            ['key' => 'ungrouped', 'label' => 'Belum Tergrup',      'icon' => 'exclamation-triangle', 'badge' => $ungroupedTotal],
            ['key' => 'pelayanan', 'label' => 'Kategori Pelayanan', 'icon' => 'building-office-2', 'badge' => $kategoriBangsalPelayanan->count()],
            ['key' => 'usia',      'label' => 'Kategori Usia',      'icon' => 'user-group',        'badge' => $kategoriBangsalUsia->count()],
        ] as $t)
            <x-atoms.tab-item wire:click="switchTab('{{ $t['key'] }}')" :active="$tab === $t['key']">
                <flux:icon name="{{ $t['icon'] }}" class="size-4 mr-1.5 shrink-0" />
                {{ $t['label'] }}
                <span class="ml-1.5 rounded-full px-1.5 py-0.5 text-xs font-semibold
                    {{ $tab === $t['key'] ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400' : 'bg-zinc-100 text-zinc-500 dark:bg-primary-dark-700 dark:text-primary-dark-400' }}">
                    {{ $t['badge'] }}
                </span>
            </x-atoms.tab-item>
        @endforeach
    </x-molecules.tabs>

    {{-- ============================================================ --}}
    {{-- Tabs: Tergrup & Belum Tergrup --}}
    {{-- ============================================================ --}}
    @if (in_array($tab, ['grouped', 'ungrouped']))
        @if ($simrsError)
            <x-ui.empty-state icon="exclamation-triangle" title="Koneksi SIMRS Gagal" :description="$simrsError" />
        @else
            <x-organisms.data-panel class="mb-4">
                <x-slot:filter>
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="min-w-48 flex-1">
                            <flux:input wire:model.live.debounce="search" icon="magnifying-glass"
                                placeholder="{{ $tab === 'grouped' ? 'Cari nama grup atau bangsal...' : 'Cari kode atau nama bangsal...' }}"
                                clearable />
                        </div>
                        <flux:select wire:model.live="filterStatus" class="w-36">
                            <flux:select.option value="">Semua Status</flux:select.option>
                            <flux:select.option value="1">Aktif</flux:select.option>
                            <flux:select.option value="0">Non-aktif</flux:select.option>
                        </flux:select>
                    </div>
                </x-slot:filter>

                {{-- ======================================================== --}}
                {{-- Tab: Tergrup --}}
                {{-- ======================================================== --}}
                @if ($tab === 'grouped')
                    @if ($groups->isEmpty())
                        @php $desc = ($search || $filterStatus !== '') ? 'Tidak ada grup yang cocok.' : 'Belum ada grup. Klik Tambah Grup untuk memulai.'; @endphp
                        <div class="px-5 py-12">
                            <x-ui.empty-state icon="squares-2x2" title="Tidak ada grup" :description="$desc" />
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-zinc-200 dark:divide-primary-dark-700">
                                <thead class="bg-zinc-50 dark:bg-primary-dark-900/50">
                                    <tr>
                                        <th class="w-10 px-3 py-3"></th>
                                        <x-atoms.table-heading>Bangsal</x-atoms.table-heading>
                                        <x-atoms.table-heading>Status</x-atoms.table-heading>
                                        <x-atoms.table-heading>Kategori Pelayanan</x-atoms.table-heading>
                                        <x-atoms.table-heading>Kategori Usia</x-atoms.table-heading>
                                        <x-atoms.table-heading>Jenis Kelamin</x-atoms.table-heading>
                                        <th class="w-10 px-3 py-3"></th>
                                    </tr>
                                </thead>

                                @foreach ($groups as $group)
                                    @php $groupActive = $group->status == 1; @endphp
                                    <tbody x-data="{ open: false }"
                                        class="divide-y divide-zinc-100 dark:divide-primary-dark-700/60 bg-white dark:bg-primary-dark-800">
                                        {{-- Header grup --}}
                                        <tr class="cursor-pointer select-none bg-zinc-50/90 dark:bg-primary-dark-900/40 hover:bg-zinc-100/80 dark:hover:bg-primary-dark-900/70 transition-colors"
                                            @click="open = !open">
                                            <td class="px-3 py-3 text-center text-zinc-400 dark:text-primary-dark-500">
                                                <flux:icon name="chevron-right" class="size-4 transition-transform duration-200" ::class="open ? 'rotate-90' : ''" />
                                            </td>
                                            <td colspan="5" class="px-4 py-3">
                                                <div class="flex items-center gap-2">
                                                    <flux:icon name="folder" class="size-4 shrink-0 text-zinc-400 dark:text-primary-dark-500" />
                                                    <span class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-200">{{ $group->nama_group }}</span>
                                                    <span class="font-mono text-xs text-zinc-400 dark:text-primary-dark-500">#{{ $group->id_group }}</span>
                                                    <flux:badge size="sm" color="zinc">{{ $group->total }} bangsal</flux:badge>
                                                    <flux:badge size="sm" :color="$groupActive ? 'emerald' : 'zinc'">{{ $groupActive ? 'Aktif' : 'Non-aktif' }}</flux:badge>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-right" @click.stop>
                                                <div class="flex items-center justify-end gap-1">
                                                    <x-atoms.button wire:click="openAddBangsal('{{ $group->id_group }}')" icon="plus" size="xs" variant="ghost">Tambah</x-atoms.button>
                                                    <x-atoms.button wire:click="openEditGroup('{{ $group->id_group }}', '{{ addslashes($group->nama_group) }}', '{{ $group->status }}')" icon="pencil-square" size="xs" variant="ghost" />
                                                    <x-atoms.button wire:click="confirmDeleteGroup('{{ $group->id_group }}', '{{ addslashes($group->nama_group) }}')" icon="trash" size="xs" variant="ghost" class="text-red-400 hover:text-red-600" />
                                                </div>
                                            </td>
                                        </tr>

                                        {{-- Baris bangsal --}}
                                        @forelse ($group->bangsals as $bangsal)
                                            @php
                                                $isActive = $bangsal->status == 1;
                                                $katPel   = $bangsal->kategoriBangsalPelayanan->first();
                                                $katUsia  = $bangsal->kategoriBangsalUsia->first();
                                                $jk       = $bangsal->jenisKelamin?->jenis_kelamin;
                                                $kd       = $bangsal->kd_bangsal;
                                                $nm       = addslashes($bangsal->nm_bangsal ?? '');
                                            @endphp
                                            <x-molecules.table-row wire:key="grp-{{ $group->id_group }}-{{ $kd }}" x-show="open" x-cloak>
                                                <td class="w-10 px-3"></td>
                                                <x-atoms.table-cell :nowrap="true">
                                                    <p class="font-mono text-xs font-bold text-zinc-800 dark:text-primary-dark-100">{{ $kd }}</p>
                                                    <p class="mt-0.5 text-xs text-zinc-400 dark:text-primary-dark-500">{{ $bangsal->nm_bangsal }}</p>
                                                </x-atoms.table-cell>
                                                <x-atoms.table-cell :nowrap="true">
                                                    <flux:badge size="sm" :color="$isActive ? 'emerald' : 'zinc'">{{ $isActive ? 'Aktif' : 'Non-aktif' }}</flux:badge>
                                                </x-atoms.table-cell>
                                                {{-- Pelayanan --}}
                                                <x-atoms.table-cell>
                                                    <button type="button" wire:click="openMappingModal('pelayanan', '{{ $kd }}', '{{ $nm }}')"
                                                        class="group/mp flex items-center gap-1.5 transition-colors text-left">
                                                        @if ($katPel)
                                                            <flux:badge size="sm" color="blue">{{ $katPel->nm_kategori }}</flux:badge>
                                                        @else
                                                            <span class="text-xs text-zinc-400 group-hover/mp:text-primary-500 transition-colors flex items-center gap-1">
                                                                <flux:icon name="plus" class="size-3" />Petakan
                                                            </span>
                                                        @endif
                                                        <flux:icon name="pencil-square" class="size-3 text-zinc-300 opacity-0 group-hover/mp:opacity-100 transition-opacity shrink-0" />
                                                    </button>
                                                </x-atoms.table-cell>
                                                {{-- Usia --}}
                                                <x-atoms.table-cell>
                                                    <button type="button" wire:click="openMappingModal('usia', '{{ $kd }}', '{{ $nm }}')"
                                                        class="group/mp flex items-center gap-1.5 transition-colors text-left">
                                                        @if ($katUsia)
                                                            <flux:badge size="sm" color="violet">{{ $katUsia->nm_kategori }}</flux:badge>
                                                        @else
                                                            <span class="text-xs text-zinc-400 group-hover/mp:text-primary-500 transition-colors flex items-center gap-1">
                                                                <flux:icon name="plus" class="size-3" />Petakan
                                                            </span>
                                                        @endif
                                                        <flux:icon name="pencil-square" class="size-3 text-zinc-300 opacity-0 group-hover/mp:opacity-100 transition-opacity shrink-0" />
                                                    </button>
                                                </x-atoms.table-cell>
                                                {{-- Jenis Kelamin --}}
                                                <x-atoms.table-cell :nowrap="true">
                                                    <button type="button" wire:click="openMappingModal('jenis_kelamin', '{{ $kd }}', '{{ $nm }}')"
                                                        class="group/mp flex items-center gap-1.5 transition-colors text-left">
                                                        @if ($jk)
                                                            <flux:badge size="sm" :color="jkBadgeColor($jk)">{{ jkLabel($jk) }}</flux:badge>
                                                        @else
                                                            <span class="text-xs text-zinc-400 group-hover/mp:text-primary-500 transition-colors flex items-center gap-1">
                                                                <flux:icon name="plus" class="size-3" />Petakan
                                                            </span>
                                                        @endif
                                                        <flux:icon name="pencil-square" class="size-3 text-zinc-300 opacity-0 group-hover/mp:opacity-100 transition-opacity shrink-0" />
                                                    </button>
                                                </x-atoms.table-cell>
                                                <x-atoms.table-cell :action="true" align="right">
                                                    <x-atoms.button size="xs" variant="ghost" icon="x-mark"
                                                        wire:click="confirmRemoveBangsal('{{ $kd }}', '{{ $group->id_group }}', '{{ $nm }}')"
                                                        class="text-red-400 hover:text-red-600" />
                                                </x-atoms.table-cell>
                                            </x-molecules.table-row>
                                        @empty
                                            <tr x-show="open" x-cloak>
                                                <td colspan="7" class="py-4 pl-14 pr-5 text-xs italic text-zinc-400 dark:text-primary-dark-500">
                                                    Belum ada bangsal dalam grup ini.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                @endforeach
                            </table>
                        </div>
                    @endif
                @endif

                {{-- ======================================================== --}}
                {{-- Tab: Belum Tergrup --}}
                {{-- ======================================================== --}}
                @if ($tab === 'ungrouped')
                    @if ($ungrouped->isEmpty())
                        @php $desc = ($search || $filterStatus !== '') ? 'Tidak ada bangsal yang cocok.' : 'Seluruh bangsal SIMRS sudah masuk dalam grup.'; @endphp
                        <div class="px-5 py-12">
                            <x-ui.empty-state icon="check-circle" title="Semua bangsal sudah tergrup" :description="$desc" />
                        </div>
                    @else
                        <x-organisms.table>
                            <x-slot:headings>
                                <x-atoms.table-heading>Bangsal</x-atoms.table-heading>
                                <x-atoms.table-heading>Status</x-atoms.table-heading>
                                <x-atoms.table-heading>Kategori Pelayanan</x-atoms.table-heading>
                                <x-atoms.table-heading>Kategori Usia</x-atoms.table-heading>
                                <x-atoms.table-heading>Jenis Kelamin</x-atoms.table-heading>
                            </x-slot:headings>

                            @foreach ($ungrouped as $bangsal)
                                @php
                                    $isActive = $bangsal->status == 1;
                                    $katPel   = $bangsal->kategoriBangsalPelayanan->first();
                                    $katUsia  = $bangsal->kategoriBangsalUsia->first();
                                    $jk       = $bangsal->jenisKelamin?->jenis_kelamin;
                                    $kd       = $bangsal->kd_bangsal;
                                    $nm       = addslashes($bangsal->nm_bangsal ?? '');
                                @endphp
                                <x-molecules.table-row wire:key="ung-{{ $kd }}">
                                    <x-atoms.table-cell :nowrap="true">
                                        <p class="font-mono text-xs font-bold text-zinc-800 dark:text-primary-dark-100">{{ $kd }}</p>
                                        <p class="mt-0.5 text-xs text-zinc-400 dark:text-primary-dark-500">{{ $bangsal->nm_bangsal }}</p>
                                    </x-atoms.table-cell>
                                    <x-atoms.table-cell :nowrap="true">
                                        <flux:badge size="sm" :color="$isActive ? 'emerald' : 'zinc'">{{ $isActive ? 'Aktif' : 'Non-aktif' }}</flux:badge>
                                    </x-atoms.table-cell>
                                    {{-- Pelayanan --}}
                                    <x-atoms.table-cell>
                                        <button type="button" wire:click="openMappingModal('pelayanan', '{{ $kd }}', '{{ $nm }}')"
                                            class="group/mp flex items-center gap-1.5 transition-colors text-left">
                                            @if ($katPel)
                                                <flux:badge size="sm" color="blue">{{ $katPel->nm_kategori }}</flux:badge>
                                            @else
                                                <span class="text-xs text-zinc-400 group-hover/mp:text-primary-500 transition-colors flex items-center gap-1">
                                                    <flux:icon name="plus" class="size-3" />Petakan
                                                </span>
                                            @endif
                                            <flux:icon name="pencil-square" class="size-3 text-zinc-300 opacity-0 group-hover/mp:opacity-100 transition-opacity shrink-0" />
                                        </button>
                                    </x-atoms.table-cell>
                                    {{-- Usia --}}
                                    <x-atoms.table-cell>
                                        <button type="button" wire:click="openMappingModal('usia', '{{ $kd }}', '{{ $nm }}')"
                                            class="group/mp flex items-center gap-1.5 transition-colors text-left">
                                            @if ($katUsia)
                                                <flux:badge size="sm" color="violet">{{ $katUsia->nm_kategori }}</flux:badge>
                                            @else
                                                <span class="text-xs text-zinc-400 group-hover/mp:text-primary-500 transition-colors flex items-center gap-1">
                                                    <flux:icon name="plus" class="size-3" />Petakan
                                                </span>
                                            @endif
                                            <flux:icon name="pencil-square" class="size-3 text-zinc-300 opacity-0 group-hover/mp:opacity-100 transition-opacity shrink-0" />
                                        </button>
                                    </x-atoms.table-cell>
                                    {{-- Jenis Kelamin --}}
                                    <x-atoms.table-cell :nowrap="true">
                                        <button type="button" wire:click="openMappingModal('jenis_kelamin', '{{ $kd }}', '{{ $nm }}')"
                                            class="group/mp flex items-center gap-1.5 transition-colors text-left">
                                            @if ($jk)
                                                <flux:badge size="sm" :color="jkBadgeColor($jk)">{{ jkLabel($jk) }}</flux:badge>
                                            @else
                                                <span class="text-xs text-zinc-400 group-hover/mp:text-primary-500 transition-colors flex items-center gap-1">
                                                    <flux:icon name="plus" class="size-3" />Petakan
                                                </span>
                                            @endif
                                            <flux:icon name="pencil-square" class="size-3 text-zinc-300 opacity-0 group-hover/mp:opacity-100 transition-opacity shrink-0" />
                                        </button>
                                    </x-atoms.table-cell>
                                </x-molecules.table-row>
                            @endforeach
                        </x-organisms.table>
                    @endif
                @endif
            </x-organisms.data-panel>
        @endif
    @endif

    {{-- ============================================================ --}}
    {{-- Tab: Kategori Pelayanan --}}
    {{-- ============================================================ --}}
    @if ($tab === 'pelayanan')
        <div class="mb-4">
            <flux:input wire:model.live.debounce="search" icon="magnifying-glass"
                placeholder="Cari kode atau nama kategori..." clearable class="max-w-sm" />
        </div>

        @php
            $filteredPelayanan = $kategoriBangsalPelayanan->when(
                $search,
                fn($c) => $c->filter(
                    fn($k) => str_contains(strtolower($k->kd_kategori), strtolower($search)) ||
                        str_contains(strtolower($k->nm_kategori), strtolower($search)),
                ),
            );
        @endphp

        @if ($filteredPelayanan->isEmpty())
            <x-ui.empty-state icon="building-office-2" title="Tidak ada kategori pelayanan"
                description="{{ $search ? 'Tidak ada yang cocok.' : 'Belum ada kategori. Klik Tambah Kategori untuk memulai.' }}" />
        @else
            <div
                class="overflow-hidden rounded-2xl border border-zinc-200/80 bg-white shadow-sm dark:border-primary-dark-700/60 dark:bg-primary-dark-800">
                <table class="min-w-full">
                    <thead>
                        <tr
                            class="border-b border-zinc-100 bg-zinc-50/70 dark:border-primary-dark-700/60 dark:bg-primary-dark-900/40">
                            <th
                                class="w-32 px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                Kode</th>
                            <th
                                class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                Nama Kategori</th>
                            <th
                                class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                Keterangan</th>
                            <th class="w-20 px-5 py-3.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100/80 dark:divide-primary-dark-700/40">
                        @foreach ($filteredPelayanan as $kat)
                            <tr wire:key="pel-{{ $kat->kd_kategori }}"
                                class="group transition-colors hover:bg-zinc-50/60 dark:hover:bg-primary-dark-700/20">
                                <td
                                    class="px-5 py-3.5 font-mono text-sm font-bold text-zinc-800 dark:text-primary-dark-100">
                                    {{ $kat->kd_kategori }}</td>
                                <td class="px-5 py-3.5 text-sm text-zinc-700 dark:text-primary-dark-300">
                                    {{ $kat->nm_kategori }}</td>
                                <td class="px-5 py-3.5 text-sm text-zinc-400">{{ $kat->keterangan ?: '—' }}</td>
                                <td class="px-5 py-3 text-right">
                                    <div
                                        class="flex items-center justify-end gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                        <x-atoms.button size="xs" variant="ghost" icon="pencil-square"
                                            wire:click="openEditPelayanan('{{ $kat->kd_kategori }}', '{{ $kat->kd_kategori }}', '{{ addslashes($kat->nm_kategori) }}', '{{ addslashes($kat->keterangan ?? '') }}')" />
                                        <x-atoms.button size="xs" variant="ghost" icon="trash"
                                            wire:click="confirmDeletePelayanan('{{ $kat->kd_kategori }}', '{{ addslashes($kat->nm_kategori) }}')"
                                            class="text-red-400 hover:text-red-600" />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif

    {{-- ============================================================ --}}
    {{-- Tab: Kategori Usia --}}
    {{-- ============================================================ --}}
    @if ($tab === 'usia')
        <div class="mb-4">
            <flux:input wire:model.live.debounce="search" icon="magnifying-glass"
                placeholder="Cari kode atau nama kategori..." clearable class="max-w-sm" />
        </div>

        @php
            $filteredUsia = $kategoriBangsalUsia->when(
                $search,
                fn($c) => $c->filter(
                    fn($k) => str_contains(strtolower($k->kd_kategori), strtolower($search)) ||
                        str_contains(strtolower($k->nm_kategori), strtolower($search)),
                ),
            );
        @endphp

        @if ($filteredUsia->isEmpty())
            <x-ui.empty-state icon="user-group" title="Tidak ada kategori usia"
                description="{{ $search ? 'Tidak ada yang cocok.' : 'Belum ada kategori. Klik Tambah Kategori untuk memulai.' }}" />
        @else
            <div
                class="overflow-hidden rounded-2xl border border-zinc-200/80 bg-white shadow-sm dark:border-primary-dark-700/60 dark:bg-primary-dark-800">
                <table class="min-w-full">
                    <thead>
                        <tr
                            class="border-b border-zinc-100 bg-zinc-50/70 dark:border-primary-dark-700/60 dark:bg-primary-dark-900/40">
                            <th
                                class="w-32 px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                Kode</th>
                            <th
                                class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                Nama Kategori</th>
                            <th
                                class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                Keterangan</th>
                            <th class="w-20 px-5 py-3.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100/80 dark:divide-primary-dark-700/40">
                        @foreach ($filteredUsia as $kat)
                            <tr wire:key="usia-{{ $kat->kd_kategori }}"
                                class="group transition-colors hover:bg-zinc-50/60 dark:hover:bg-primary-dark-700/20">
                                <td
                                    class="px-5 py-3.5 font-mono text-sm font-bold text-zinc-800 dark:text-primary-dark-100">
                                    {{ $kat->kd_kategori }}</td>
                                <td class="px-5 py-3.5 text-sm text-zinc-700 dark:text-primary-dark-300">
                                    {{ $kat->nm_kategori }}</td>
                                <td class="px-5 py-3.5 text-sm text-zinc-400">{{ $kat->keterangan ?: '—' }}</td>
                                <td class="px-5 py-3 text-right">
                                    <div
                                        class="flex items-center justify-end gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                        <x-atoms.button size="xs" variant="ghost" icon="pencil-square"
                                            wire:click="openEditUsia('{{ $kat->kd_kategori }}', '{{ $kat->kd_kategori }}', '{{ addslashes($kat->nm_kategori) }}', '{{ addslashes($kat->keterangan ?? '') }}')" />
                                        <x-atoms.button size="xs" variant="ghost" icon="trash"
                                            wire:click="confirmDeleteUsia('{{ $kat->kd_kategori }}', '{{ addslashes($kat->nm_kategori) }}')"
                                            class="text-red-400 hover:text-red-600" />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif

    {{-- ============================================================ --}}
    {{-- Modal: Buat / Edit Grup --}}
    {{-- ============================================================ --}}
    <x-organisms.modal wire:model="showGroupModal" maxWidth="md" title="">
        <div class="space-y-4">
            <div class="flex items-center gap-4">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900/30">
                    <flux:icon name="squares-2x2" class="size-5 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <flux:heading size="lg">{{ $editingGroupId ? 'Edit Grup Bangsal' : 'Tambah Grup Bangsal' }}
                    </flux:heading>
                    <flux:subheading>
                        {{ $editingGroupId ? 'Ubah detail grup.' : 'Buat grup baru untuk mengelompokkan bangsal.' }}
                    </flux:subheading>
                </div>
            </div>
            <div class="space-y-3 border-t border-zinc-100 pt-4 dark:border-primary-dark-700">
                <flux:field>
                    <flux:label>Nama Grup</flux:label>
                    <flux:input wire:model="groupName" placeholder="Contoh: Rawat Inap Dewasa, VIP..." />
                    <flux:error name="groupName" />
                </flux:field>
                <flux:field>
                    <flux:label>Status</flux:label>
                    <flux:select wire:model="groupStatus">
                        <flux:select.option value="1">Aktif</flux:select.option>
                        <flux:select.option value="0">Non-aktif</flux:select.option>
                    </flux:select>
                </flux:field>
            </div>

            <x-slot:footer>
                <div class="flex justify-end gap-2 border-t border-zinc-100 pt-4 dark:border-primary-dark-700">
                    <x-atoms.button wire:click="$set('showGroupModal', false)" variant="ghost">Batal</x-atoms.button>
                    <x-atoms.button wire:click="saveGroup" variant="primary">
                        {{ $editingGroupId ? 'Simpan Perubahan' : 'Buat Grup' }}</x-atoms.button>
                </div>
            </x-slot:footer>
        </div>
    </x-organisms.modal>

    {{-- Modal: Hapus Grup --}}
    <x-organisms.modal wire:model="showDeleteGroupModal" maxWidth="sm" title="">
        <div class="space-y-4">
            <div class="flex items-center gap-4">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                    <flux:icon name="trash" class="size-5 text-red-600 dark:text-red-400" />
                </div>
                <div>
                    <flux:heading size="lg">Hapus Grup</flux:heading>
                    <flux:subheading>Tindakan ini tidak dapat dibatalkan.</flux:subheading>
                </div>
            </div>
            <p class="text-sm text-zinc-600 dark:text-primary-dark-400">
                Yakin ingin menghapus grup <strong>{{ $deletingGroupName }}</strong>?
                Semua bangsal dalam grup akan dilepas, namun data bangsal di SIMRS tidak terpengaruh.
            </p>

            <x-slot:footer>
                <div class="flex justify-end gap-2 border-t border-zinc-100 pt-4 dark:border-primary-dark-700">
                    <x-atoms.button wire:click="$set('showDeleteGroupModal', false)"
                        variant="ghost">Batal</x-atoms.button>
                    <x-atoms.button wire:click="deleteGroup" variant="danger" icon="trash">Hapus
                        Grup</x-atoms.button>
                </div>
            </x-slot:footer>
        </div>
    </x-organisms.modal>

    {{-- Modal: Tambah Bangsal ke Grup --}}
    <x-organisms.modal wire:model="showAddBangsalModal" maxWidth="xl" title="">
        <div class="space-y-4">
            <div class="flex items-center gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900/30">
                    <flux:icon name="plus-circle" class="size-5 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <flux:heading size="lg">Tambah Bangsal ke Grup</flux:heading>
                    <flux:subheading>Bangsal yang sudah tergabung di grup lain tidak dapat dipilih.</flux:subheading>
                </div>
            </div>

            <flux:input wire:model.live.debounce="bangsalSearch" icon="magnifying-glass"
                placeholder="Cari kode atau nama bangsal..." clearable />

            @if ($availableBangsals->isEmpty())
                <p class="py-6 text-center text-sm text-zinc-400">
                    {{ $bangsalSearch ? 'Tidak ada bangsal yang cocok.' : 'Semua bangsal sudah masuk grup ini.' }}
                </p>
            @else
                {{-- Ringkasan --}}
                @if ($availableMappedCount > 0)
                    <div class="flex items-center gap-2 rounded-xl bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-800/40 px-3.5 py-2.5">
                        <flux:icon name="information-circle" class="size-4 shrink-0 text-amber-500" />
                        <p class="text-xs text-amber-700 dark:text-amber-300">
                            <span class="font-semibold">{{ $availableMappedCount }} bangsal</span> sudah tergabung di grup lain dan tidak dapat dipilih.
                            <span class="font-semibold">{{ $availableFreeCount }} bangsal</span> tersedia untuk ditambahkan.
                        </p>
                    </div>
                @endif

                <div class="max-h-72 divide-y divide-zinc-100 dark:divide-primary-dark-700/60 overflow-y-auto rounded-xl border border-zinc-200 dark:border-primary-dark-700">
                    @foreach ($availableBangsals as $bangsal)
                        @php
                            $kd         = $bangsal->kd_bangsal;
                            $isDisabled = $otherAssignmentMap->has($kd);
                            $otherGroup = $otherAssignmentMap->get($kd);
                            $checked    = !$isDisabled && in_array($kd, $selectedBangsals);
                            $isActive   = $bangsal->status == 1;
                        @endphp

                        @if ($isDisabled)
                            {{-- Bangsal sudah di grup lain — disabled --}}
                            <div class="flex items-center gap-3 px-4 py-2.5 opacity-50 cursor-not-allowed bg-zinc-50/60 dark:bg-primary-dark-900/20">
                                <input type="checkbox" disabled class="rounded border-zinc-300 text-zinc-300 cursor-not-allowed shrink-0" />
                                <div class="min-w-0 flex-1">
                                    <p class="font-mono text-xs font-bold text-zinc-600 dark:text-primary-dark-300">{{ $kd }}</p>
                                    <p class="mt-0.5 text-xs text-zinc-400 dark:text-primary-dark-500">{{ $bangsal->nm_bangsal }}</p>
                                </div>
                                <div class="shrink-0 flex items-center gap-1.5">
                                    <flux:icon name="lock-closed" class="size-3 text-zinc-400" />
                                    <span class="text-xs text-zinc-500 dark:text-primary-dark-400 whitespace-nowrap">{{ $otherGroup }}</span>
                                </div>
                            </div>
                        @else
                            {{-- Bangsal bebas — dapat dipilih --}}
                            <label class="flex cursor-pointer items-center gap-3 px-4 py-2.5 transition-colors
                                hover:bg-zinc-50 dark:hover:bg-primary-dark-700/40
                                {{ $checked ? 'bg-primary-50 dark:bg-primary-900/20' : '' }}">
                                <input type="checkbox"
                                    wire:click="toggleBangsal('{{ $kd }}')"
                                    @checked($checked)
                                    class="rounded border-zinc-300 text-primary-600 focus:ring-primary-500 shrink-0" />
                                <div class="min-w-0 flex-1">
                                    <p class="font-mono text-xs font-bold text-zinc-800 dark:text-primary-dark-100">{{ $kd }}</p>
                                    <p class="mt-0.5 text-xs text-zinc-400 dark:text-primary-dark-500">{{ $bangsal->nm_bangsal }}</p>
                                </div>
                                <flux:badge size="sm" :color="$isActive ? 'emerald' : 'zinc'">
                                    {{ $isActive ? 'Aktif' : 'Non-aktif' }}
                                </flux:badge>
                            </label>
                        @endif
                    @endforeach
                </div>
            @endif

            <div class="flex items-center {{ count($selectedBangsals) > 0 ? 'justify-between' : 'justify-end' }} gap-4 border-t border-zinc-100 dark:border-primary-dark-700 pt-4">
                @if (count($selectedBangsals) > 0)
                    <p class="text-xs font-medium text-primary-600 dark:text-primary-400">
                        {{ count($selectedBangsals) }} bangsal dipilih
                    </p>
                @endif
                <div class="flex gap-2">
                    <x-atoms.button wire:click="$set('showAddBangsalModal', false)" variant="ghost">Batal</x-atoms.button>
                    <x-atoms.button wire:click="addBangsalsToGroup" variant="primary"
                        :disabled="count($selectedBangsals) === 0">Tambahkan</x-atoms.button>
                </div>
            </div>
        </div>

    </x-organisms.modal>

    {{-- Modal: Konfirmasi Lepas Bangsal --}}
    <x-organisms.modal wire:model="showRemoveBangsalModal" maxWidth="sm" title="">
        <div class="space-y-4">
            <div class="flex items-center gap-4">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                    <flux:icon name="x-circle" class="size-5 text-red-600 dark:text-red-400" />
                </div>
                <div>
                    <flux:heading size="lg">Lepas Bangsal dari Grup</flux:heading>
                    <flux:subheading>Data bangsal di SIMRS tidak akan terpengaruh.</flux:subheading>
                </div>
            </div>
            <p class="text-sm text-zinc-600 dark:text-primary-dark-400">
                Yakin ingin melepas bangsal <strong>{{ $removingBangsalLabel }}</strong> dari grup ini?
            </p>

            <x-slot:footer>
                <div class="flex justify-end gap-2 border-t border-zinc-100 pt-4 dark:border-primary-dark-700">
                    <x-atoms.button wire:click="$set('showRemoveBangsalModal', false)"
                        variant="ghost">Batal</x-atoms.button>
                    <x-atoms.button wire:click="removeBangsalFromGroup" variant="danger">Lepas
                        Bangsal</x-atoms.button>
                </div>
            </x-slot:footer>
        </div>
    </x-organisms.modal>

    {{-- ============================================================ --}}
    {{-- Modal: Mapping per Tipe --}}
    {{-- ============================================================ --}}
    @php
        $modalMeta = match ($mappingType) {
            'pelayanan'     => ['title' => 'Mapping Kategori Pelayanan', 'icon' => 'building-office-2', 'iconBg' => 'bg-blue-100 dark:bg-blue-900/30', 'iconColor' => 'text-blue-600 dark:text-blue-400'],
            'usia'          => ['title' => 'Mapping Kategori Usia',      'icon' => 'user-group',        'iconBg' => 'bg-violet-100 dark:bg-violet-900/30', 'iconColor' => 'text-violet-600 dark:text-violet-400'],
            'jenis_kelamin' => ['title' => 'Mapping Jenis Kelamin',      'icon' => 'users',             'iconBg' => 'bg-teal-100 dark:bg-teal-900/30',  'iconColor' => 'text-teal-600 dark:text-teal-400'],
            default         => ['title' => 'Mapping', 'icon' => 'tag', 'iconBg' => 'bg-zinc-100', 'iconColor' => 'text-zinc-500'],
        };
    @endphp
    <x-organisms.modal wire:model="showMappingModal" maxWidth="sm" title="">
        <div class="space-y-4">
            {{-- Header --}}
            <div class="flex items-center gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-full {{ $modalMeta['iconBg'] }}">
                    <flux:icon name="{{ $modalMeta['icon'] }}" class="size-5 {{ $modalMeta['iconColor'] }}" />
                </div>
                <div>
                    <flux:heading size="lg">{{ $modalMeta['title'] }}</flux:heading>
                    <flux:subheading>{{ $mappingBangsalLabel }} <span class="font-mono">({{ $mappingKdBangsal }})</span></flux:subheading>
                </div>
            </div>

            {{-- Konten per tipe --}}
            <div class="border-t border-zinc-100 dark:border-primary-dark-700 pt-4">

                {{-- Pelayanan --}}
                @if ($mappingType === 'pelayanan')
                    @if ($kategoriBangsalPelayanan->isEmpty())
                        <p class="text-xs text-zinc-400">Belum ada kategori pelayanan.</p>
                    @else
                        <div class="space-y-1 max-h-64 overflow-y-auto rounded-xl border border-zinc-200 dark:border-primary-dark-700 p-1.5">
                            <label class="flex items-center gap-2.5 rounded-lg px-3 py-2 cursor-pointer hover:bg-zinc-50 dark:hover:bg-primary-dark-700/40 transition-colors">
                                <input type="radio" wire:model="selectedPelayanan" value="" class="text-primary-600 shrink-0" />
                                <span class="text-sm italic text-zinc-400">Tidak dipetakan</span>
                            </label>
                            @foreach ($kategoriBangsalPelayanan as $kat)
                                <label class="flex items-center gap-2.5 rounded-lg px-3 py-2 cursor-pointer hover:bg-zinc-50 dark:hover:bg-primary-dark-700/40 transition-colors
                                    {{ $selectedPelayanan === $kat->kd_kategori ? 'bg-blue-50 dark:bg-blue-900/20' : '' }}">
                                    <input type="radio" wire:model="selectedPelayanan" value="{{ $kat->kd_kategori }}" class="text-primary-600 shrink-0" />
                                    <span class="text-sm text-zinc-700 dark:text-primary-dark-300 flex-1">{{ $kat->nm_kategori }}</span>
                                    <span class="font-mono text-xs text-zinc-400 dark:text-primary-dark-500">{{ $kat->kd_kategori }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                @endif

                {{-- Usia --}}
                @if ($mappingType === 'usia')
                    @if ($kategoriBangsalUsia->isEmpty())
                        <p class="text-xs text-zinc-400">Belum ada kategori usia.</p>
                    @else
                        <div class="space-y-1 max-h-64 overflow-y-auto rounded-xl border border-zinc-200 dark:border-primary-dark-700 p-1.5">
                            <label class="flex items-center gap-2.5 rounded-lg px-3 py-2 cursor-pointer hover:bg-zinc-50 dark:hover:bg-primary-dark-700/40 transition-colors">
                                <input type="radio" wire:model="selectedUsia" value="" class="text-primary-600 shrink-0" />
                                <span class="text-sm italic text-zinc-400">Tidak dipetakan</span>
                            </label>
                            @foreach ($kategoriBangsalUsia as $kat)
                                <label class="flex items-center gap-2.5 rounded-lg px-3 py-2 cursor-pointer hover:bg-zinc-50 dark:hover:bg-primary-dark-700/40 transition-colors
                                    {{ $selectedUsia === $kat->kd_kategori ? 'bg-violet-50 dark:bg-violet-900/20' : '' }}">
                                    <input type="radio" wire:model="selectedUsia" value="{{ $kat->kd_kategori }}" class="text-primary-600 shrink-0" />
                                    <span class="text-sm text-zinc-700 dark:text-primary-dark-300 flex-1">{{ $kat->nm_kategori }}</span>
                                    <span class="font-mono text-xs text-zinc-400 dark:text-primary-dark-500">{{ $kat->kd_kategori }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                @endif

                {{-- Jenis Kelamin --}}
                @if ($mappingType === 'jenis_kelamin')
                    <div class="space-y-1 rounded-xl border border-zinc-200 dark:border-primary-dark-700 p-1.5">
                        @foreach ([
                            '' => ['label' => 'Tidak dipetakan', 'icon' => null,        'color' => 'zinc',  'activeBg' => ''],
                            'semua'     => ['label' => 'Semua',          'icon' => 'user-group', 'color' => 'teal',  'activeBg' => 'bg-teal-50 dark:bg-teal-900/20'],
                            'laki-laki' => ['label' => 'Laki-laki',      'icon' => 'user',       'color' => 'blue',  'activeBg' => 'bg-blue-50 dark:bg-blue-900/20'],
                            'perempuan' => ['label' => 'Perempuan',      'icon' => 'user',       'color' => 'rose',  'activeBg' => 'bg-rose-50 dark:bg-rose-900/20'],
                        ] as $val => $opt)
                            @php $isSelected = ($selectedJenisKelamin ?? '') === $val; @endphp
                            <label class="flex items-center gap-3 rounded-lg px-3 py-2.5 cursor-pointer hover:bg-zinc-50 dark:hover:bg-primary-dark-700/40 transition-colors
                                {{ $isSelected && $val !== '' ? $opt['activeBg'] : '' }}">
                                <input type="radio" wire:model="selectedJenisKelamin" value="{{ $val }}" class="text-primary-600 shrink-0" />
                                @if ($opt['icon'])
                                    @php
                                        $jkIconColor = match ($val) {
                                            'laki-laki' => 'text-blue-500', 'perempuan' => 'text-rose-500', default => 'text-teal-500',
                                        };
                                    @endphp
                                    <flux:icon name="{{ $opt['icon'] }}" class="size-4 shrink-0 {{ $jkIconColor }}" />
                                @else
                                    <span class="size-4 shrink-0"></span>
                                @endif
                                <span class="text-sm {{ $val === '' ? 'italic text-zinc-400' : 'text-zinc-700 dark:text-primary-dark-300' }}">{{ $opt['label'] }}</span>
                                @if ($val !== '' && $isSelected)
                                    <flux:badge size="sm" :color="$opt['color']" class="ml-auto">Dipilih</flux:badge>
                                @endif
                            </label>
                        @endforeach
                    </div>
                @endif

            </div>

            <x-slot:footer>
                <div class="flex justify-end gap-2 border-t border-zinc-100 pt-4 dark:border-primary-dark-700">
                    <x-atoms.button wire:click="$set('showMappingModal', false)" variant="ghost">Batal</x-atoms.button>
                    <x-atoms.button wire:click="saveMapping" variant="primary">Simpan</x-atoms.button>
                </div>
            </x-slot:footer>
        </div>
    </x-organisms.modal>

    {{-- ============================================================ --}}
    {{-- Modal CRUD Kategori Pelayanan --}}
    {{-- ============================================================ --}}
    <x-organisms.modal wire:model="showPelayananModal" maxWidth="md" title="">
        <div class="space-y-4">
            <div class="flex items-center gap-4">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30">
                    <flux:icon name="building-office-2" class="size-5 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <flux:heading size="lg">
                        {{ $editingPelayananId ? 'Edit Kategori Pelayanan' : 'Tambah Kategori Pelayanan' }}
                    </flux:heading>
                </div>
            </div>
            <div class="space-y-3 border-t border-zinc-100 pt-4 dark:border-primary-dark-700">
                <flux:field>
                    <flux:label>Kode Kategori</flux:label>
                    <flux:input wire:model="pelayananKode" placeholder="Contoh: ANAK, BEDAH..."
                        :readonly="(bool) $editingPelayananId" />
                    <flux:error name="pelayananKode" />
                </flux:field>
                <flux:field>
                    <flux:label>Nama Kategori</flux:label>
                    <flux:input wire:model="pelayananNama" placeholder="Contoh: Bangsal Anak, Bedah..." />
                    <flux:error name="pelayananNama" />
                </flux:field>
                <flux:field>
                    <flux:label>Keterangan <span class="text-zinc-400">(opsional)</span></flux:label>
                    <flux:input wire:model="pelayananKeterangan" placeholder="Deskripsi tambahan..." />
                </flux:field>
            </div>

            <x-slot:footer>
                <div class="flex justify-end gap-2 border-t border-zinc-100 pt-4 dark:border-primary-dark-700">
                    <x-atoms.button wire:click="$set('showPelayananModal', false)"
                        variant="ghost">Batal</x-atoms.button>
                    <x-atoms.button wire:click="savePelayanan" variant="primary">
                        {{ $editingPelayananId ? 'Simpan' : 'Tambah' }}</x-atoms.button>
                </div>
            </x-slot:footer>
        </div>
    </x-organisms.modal>

    <x-organisms.modal wire:model="showDeletePelayananModal" maxWidth="sm" title="">
        <div class="space-y-4">
            <div class="flex items-center gap-4">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                    <flux:icon name="trash" class="size-5 text-red-600 dark:text-red-400" />
                </div>
                <div>
                    <flux:heading size="lg">Hapus Kategori Pelayanan</flux:heading>
                    <flux:subheading>Mapping bangsal yang terkait juga akan dihapus.</flux:subheading>
                </div>
            </div>
            <p class="text-sm text-zinc-600 dark:text-primary-dark-400">Yakin ingin menghapus kategori
                <strong>{{ $deletingPelayananLabel }}</strong>?
            </p>

            <x-slot:footer>
                <div class="flex justify-end gap-2 border-t border-zinc-100 pt-4 dark:border-primary-dark-700">
                    <x-atoms.button wire:click="$set('showDeletePelayananModal', false)"
                        variant="ghost">Batal</x-atoms.button>
                    <x-atoms.button wire:click="deletePelayanan" variant="danger"
                        icon="trash">Hapus</x-atoms.button>
                </div>
            </x-slot:footer>
        </div>
    </x-organisms.modal>

    {{-- ============================================================ --}}
    {{-- Modal CRUD Kategori Usia --}}
    {{-- ============================================================ --}}
    <x-organisms.modal wire:model="showUsiaModal" maxWidth="md" title="">
        <div class="space-y-4">
            <div class="flex items-center gap-4">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-violet-100 dark:bg-violet-900/30">
                    <flux:icon name="user-group" class="size-5 text-violet-600 dark:text-violet-400" />
                </div>
                <div>
                    <flux:heading size="lg">{{ $editingUsiaId ? 'Edit Kategori Usia' : 'Tambah Kategori Usia' }}
                    </flux:heading>
                </div>
            </div>
            <div class="space-y-3 border-t border-zinc-100 pt-4 dark:border-primary-dark-700">
                <flux:field>
                    <flux:label>Kode Kategori</flux:label>
                    <flux:input wire:model="usiaKode" placeholder="Contoh: DEWASA, ANAK, BAYI..."
                        :readonly="(bool) $editingUsiaId" />
                    <flux:error name="usiaKode" />
                </flux:field>
                <flux:field>
                    <flux:label>Nama Kategori</flux:label>
                    <flux:input wire:model="usiaNama" placeholder="Contoh: Dewasa, Anak-anak..." />
                    <flux:error name="usiaNama" />
                </flux:field>
                <flux:field>
                    <flux:label>Keterangan <span class="text-zinc-400">(opsional)</span></flux:label>
                    <flux:input wire:model="usiaKeterangan" placeholder="Deskripsi tambahan..." />
                </flux:field>
            </div>

            <x-slot:footer>
                <div class="flex justify-end gap-2 border-t border-zinc-100 pt-4 dark:border-primary-dark-700">
                    <x-atoms.button wire:click="$set('showUsiaModal', false)" variant="ghost">Batal</x-atoms.button>
                    <x-atoms.button wire:click="saveUsia"
                        variant="primary">{{ $editingUsiaId ? 'Simpan' : 'Tambah' }}
                    </x-atoms.button>
                </div>
            </x-slot:footer>
        </div>
    </x-organisms.modal>

    <x-organisms.modal wire:model="showDeleteUsiaModal" maxWidth="sm" title="">
        <div class="space-y-4">
            <div class="flex items-center gap-4">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                    <flux:icon name="trash" class="size-5 text-red-600 dark:text-red-400" />
                </div>
                <div>
                    <flux:heading size="lg">Hapus Kategori Usia</flux:heading>
                    <flux:subheading>Mapping bangsal yang terkait juga akan dihapus.</flux:subheading>
                </div>
            </div>
            <p class="text-sm text-zinc-600 dark:text-primary-dark-400">Yakin ingin menghapus kategori
                <strong>{{ $deletingUsiaLabel }}</strong>?
            </p>

            <x-slot:footer>
                <div class="flex justify-end gap-2 border-t border-zinc-100 pt-4 dark:border-primary-dark-700">
                    <x-atoms.button wire:click="$set('showDeleteUsiaModal', false)"
                        variant="ghost">Batal</x-atoms.button>
                    <x-atoms.button wire:click="deleteUsia" variant="danger" icon="trash">Hapus</x-atoms.button>
                </div>
            </x-slot:footer>
        </div>
    </x-organisms.modal>

</div>
