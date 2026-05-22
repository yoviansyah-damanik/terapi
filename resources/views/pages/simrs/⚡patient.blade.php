<?php

use App\Jobs\HitungKelompokUmurJob;
use App\Models\Simrs\KelompokUmur;
use App\Models\Simrs\KelompokUmurPasien;
use App\Models\Simrs\Pasien;
use App\Models\Simrs\RegPeriksa;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app')] #[Title('Pasien')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';
    #[Url]
    public string $filterKelompok = '';
    #[Url]
    public string $filterHitung = '';
    #[Url]
    public string $filterDinas = '';
    #[Url]
    public string $filterJk = '';
    #[Url]
    public int $perPage = 25;

    public bool $showMasterModal = false;
    public bool $showDetailModal = false;

    // Master Kelompok Umur form
    public string $formKode = '';
    public string $formNama = '';
    public string $formUmurMin = '';
    public string $formUmurMax = '';
    public string $formUrut = '';
    public ?string $editingKode = null;

    // Detail pasien modal
    public ?array $selectedPatient = null;
    public array $riwayatKunjungan = [];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }
    public function updatedFilterKelompok(): void
    {
        $this->resetPage();
    }
    public function updatedFilterHitung(): void
    {
        $this->resetPage();
    }
    public function updatedFilterDinas(): void
    {
        $this->resetPage();
    }
    public function updatedFilterJk(): void
    {
        $this->resetPage();
    }

    // ── Detail Pasien ─────────────────────────────────────────────────────────

    public function showPatientDetail(string $noRkmMedis): void
    {
        $pasien = Pasien::with(['tni.golongan', 'tni.pangkat', 'tni.satuan', 'tni.jabatan', 'polri.golongan', 'polri.pangkat', 'polri.satuan', 'polri.jabatan', 'penjab'])->find($noRkmMedis);

        if (!$pasien) {
            return;
        }

        $kup = KelompokUmurPasien::with('kelompokUmur')->find($noRkmMedis);

        $riwayat = RegPeriksa::with(['dokter', 'poliklinik', 'penjab'])
            ->where('no_rkm_medis', $noRkmMedis)
            ->orderByDesc('tgl_registrasi')
            ->orderByDesc('jam_reg')
            ->limit(15)
            ->get();

        $this->selectedPatient = [
            'no_rkm_medis' => $pasien->no_rkm_medis,
            'nm_pasien' => $pasien->nm_pasien,
            'jk' => $pasien->jk,
            'tgl_lahir' => $pasien->tgl_lahir?->format('d/m/Y'),
            'tmp_lahir' => $pasien->tmp_lahir,
            'no_ktp' => $pasien->no_ktp,
            'alamat' => $pasien->alamat,
            'no_tlp' => $pasien->no_tlp,
            'gol_darah' => $pasien->gol_darah,
            'agama' => $pasien->agama,
            'stts_nikah' => $pasien->marital_status_label,
            'pekerjaan' => $pasien->pekerjaan,
            'penjab' => $pasien->penjab?->nm_pj ?? '-',
            'no_peserta' => $pasien->no_peserta,
            'nm_ibu' => $pasien->nm_ibu,
            'suku_bangsa' => $pasien->suku_bangsa,
            'tgl_daftar' => $pasien->tgl_daftar?->format('d/m/Y'),
            'kelompok_umur' => $kup?->kelompokUmur?->nama,
            'kode_kelompok_umur' => $kup?->kode_kelompok_umur,
            'umur_hari' => $kup?->umur_hari,
            'tanggal_hitung' => $kup?->tanggal_hitung?->format('d/m/Y'),
            'is_tni' => $pasien->tni !== null,
            'tni' => $pasien->tni
                ? [
                    'nrp' => $pasien->tni->nrp,
                    'golongan' => $pasien->tni->golongan?->nama_golongan,
                    'pangkat' => $pasien->tni->pangkat?->nama_pangkat,
                    'satuan' => $pasien->tni->satuan?->nama_satuan,
                    'jabatan' => $pasien->tni->jabatan?->nama_jabatan,
                ]
                : null,
            'is_polri' => $pasien->polri !== null,
            'polri' => $pasien->polri
                ? [
                    'nrp' => $pasien->polri->nrp,
                    'golongan' => $pasien->polri->golongan?->nama_golongan,
                    'pangkat' => $pasien->polri->pangkat?->nama_pangkat,
                    'satuan' => $pasien->polri->satuan?->nama_satuan,
                    'jabatan' => $pasien->polri->jabatan?->nama_jabatan,
                ]
                : null,
        ];

        $this->riwayatKunjungan = $riwayat
            ->map(
                fn($r) => [
                    'no_rawat' => $r->no_rawat,
                    'tgl_registrasi' => $r->tgl_registrasi?->format('d/m/Y'),
                    'dokter' => $r->dokter?->nm_dokter ?? '-',
                    'poli' => $r->poliklinik?->nm_poli ?? '-',
                    'status_lanjut' => $r->status_lanjut,
                    'stts' => $r->stts,
                    'penjab' => $r->penjab?->nm_pj ?? '-',
                ],
            )
            ->toArray();

        $this->showDetailModal = true;
    }

    // ── Master Kelompok Umur CRUD ─────────────────────────────────────────────

    public function openMasterModal(): void
    {
        $this->resetKelompokUmurForm();
        $this->showMasterModal = true;
    }

    public function editKelompokUmur(string $kode): void
    {
        $item = KelompokUmur::find($kode);
        if (!$item) {
            return;
        }
        $this->editingKode = $kode;
        $this->formKode = $item->kode;
        $this->formNama = $item->nama;
        $this->formUmurMin = (string) $item->umur_min;
        $this->formUmurMax = $item->umur_max !== null ? (string) $item->umur_max : '';
        $this->formUrut = (string) $item->urut;
    }

    public function saveKelompokUmur(): void
    {
        $this->validate(
            [
                'formKode' => 'required|string|max:5',
                'formNama' => 'required|string|max:100',
                'formUmurMin' => 'required|integer|min:0',
                'formUmurMax' => 'nullable|integer|min:0',
                'formUrut' => 'required|integer|min:1',
            ],
            [
                'formKode.required' => 'Kode wajib diisi',
                'formNama.required' => 'Nama wajib diisi',
                'formUmurMin.required' => 'Umur minimum wajib diisi',
                'formUrut.required' => 'Urut wajib diisi',
            ],
        );

        $data = [
            'nama' => $this->formNama,
            'umur_min' => (int) $this->formUmurMin,
            'umur_max' => $this->formUmurMax !== '' ? (int) $this->formUmurMax : null,
            'urut' => (int) $this->formUrut,
        ];

        if ($this->editingKode) {
            KelompokUmur::where('kode', $this->editingKode)->update($data);
            $this->toastSuccess("Kelompok umur '{$this->formNama}' berhasil diperbarui.");
        } else {
            if (KelompokUmur::where('kode', $this->formKode)->exists()) {
                $this->addError('formKode', "Kode '{$this->formKode}' sudah digunakan.");
                return;
            }
            KelompokUmur::create(array_merge(['kode' => strtoupper($this->formKode)], $data));
            $this->toastSuccess("Kelompok umur '{$this->formNama}' berhasil ditambahkan.");
        }

        $this->resetKelompokUmurForm();
    }

    public function deleteKelompokUmur(string $kode): void
    {
        if (KelompokUmurPasien::where('kode_kelompok_umur', $kode)->exists()) {
            $this->toastError("Kelompok umur '{$kode}' sedang digunakan oleh data pasien.");
            return;
        }
        KelompokUmur::where('kode', $kode)->delete();
        $this->toastSuccess("Kelompok umur '{$kode}' berhasil dihapus.");
        if ($this->editingKode === $kode) {
            $this->resetKelompokUmurForm();
        }
    }

    public function resetKelompokUmurForm(): void
    {
        $this->editingKode = null;
        $this->formKode = '';
        $this->formNama = '';
        $this->formUmurMin = '';
        $this->formUmurMax = '';
        $this->formUrut = '';
        $this->resetValidation();
    }

    // ── Perhitungan Kelompok Umur ─────────────────────────────────────────────

    public function hitungKelompokUmur(string $noRkmMedis): void
    {
        $pasien = Pasien::find($noRkmMedis);
        if (!$pasien || !$pasien->tgl_lahir) {
            $this->toastError('Data tanggal lahir pasien tidak tersedia.');
            return;
        }

        $umurHari = Carbon::parse($pasien->tgl_lahir)->diffInDays(today());
        $kelompok = KelompokUmur::findByDays($umurHari);

        if (!$kelompok) {
            $this->toastError('Tidak ada kelompok umur yang sesuai. Periksa master kelompok umur.');
            return;
        }

        KelompokUmurPasien::updateOrCreate(['no_rkm_medis' => $noRkmMedis], ['kode_kelompok_umur' => $kelompok->kode, 'umur_hari' => $umurHari, 'tanggal_hitung' => today()]);

        $this->toastSuccess("Kelompok umur {$pasien->nm_pasien} → {$kelompok->nama}.");
    }

    public function hitungSemuaPasien(): void
    {
        HitungKelompokUmurJob::dispatch();
        $this->toastSuccess('Proses hitung kelompok umur seluruh pasien dijadwalkan di background.');
    }

    // ── Data ─────────────────────────────────────────────────────────────────

    private function buildQuery()
    {
        return Pasien::query()
            ->leftJoin('kelompok_umur_pasien as kup', 'pasien.no_rkm_medis', '=', 'kup.no_rkm_medis')
            ->leftJoin('kelompok_umur as ku', 'kup.kode_kelompok_umur', '=', 'ku.kode')
            ->leftJoin('pasien_tni as ptni', 'pasien.no_rkm_medis', '=', 'ptni.no_rkm_medis')
            ->leftJoin('pasien_polri as ppolri', 'pasien.no_rkm_medis', '=', 'ppolri.no_rkm_medis')
            ->select(['pasien.no_rkm_medis', 'pasien.nm_pasien', 'pasien.jk', 'pasien.tgl_lahir', 'pasien.no_ktp', 'kup.kode_kelompok_umur', 'kup.umur_hari', 'kup.tanggal_hitung', 'ku.nama as nama_kelompok_umur', 'ku.urut as urut_kelompok_umur'])
            ->selectRaw('CASE WHEN ptni.no_rkm_medis IS NOT NULL THEN 1 ELSE 0 END as is_tni')
            ->selectRaw('CASE WHEN ppolri.no_rkm_medis IS NOT NULL THEN 1 ELSE 0 END as is_polri')
            ->when(
                $this->search,
                fn($q) => $q->where(
                    fn($sq) => $sq
                        ->where('pasien.nm_pasien', 'like', "%{$this->search}%")
                        ->orWhere('pasien.no_rkm_medis', 'like', "%{$this->search}%")
                        ->orWhere('pasien.no_ktp', 'like', "%{$this->search}%"),
                ),
            )
            ->when($this->filterKelompok, fn($q) => $q->where('kup.kode_kelompok_umur', $this->filterKelompok))
            ->when($this->filterHitung === 'sudah', fn($q) => $q->whereNotNull('kup.kode_kelompok_umur'))
            ->when($this->filterHitung === 'belum', fn($q) => $q->whereNull('kup.kode_kelompok_umur'))
            ->when($this->filterDinas === 'tni', fn($q) => $q->whereNotNull('ptni.no_rkm_medis'))
            ->when($this->filterDinas === 'polri', fn($q) => $q->whereNotNull('ppolri.no_rkm_medis'))
            ->when($this->filterDinas === 'sipil', fn($q) => $q->whereNull('ptni.no_rkm_medis')->whereNull('ppolri.no_rkm_medis'))
            ->when($this->filterJk, fn($q) => $q->where('pasien.jk', $this->filterJk))
            ->orderBy('ku.urut')
            ->orderBy('pasien.nm_pasien');
    }

    public function with(): array
    {
        $kelompoks = Cache::remember('simrs_kelompok_umur', 3600, fn() => KelompokUmur::orderBy('urut')->get());
        $totalPasien = Cache::remember('simrs_total_pasien', 300, fn() => Pasien::count());
        $totalSudah = Cache::remember('simrs_kelompok_umur_pasien_count', 300, fn() => KelompokUmurPasien::count());
        $totalBelum = max(0, $totalPasien - $totalSudah);

        $statJk = Cache::remember('simrs_stat_jk', 300, fn() => Pasien::selectRaw('jk, count(*) as total')->groupBy('jk')->pluck('total', 'jk'));
        $lakiLaki = $statJk['L'] ?? 0;
        $perempuan = $statJk['P'] ?? 0;

        $statTni = Cache::remember('simrs_stat_tni', 300, fn() => \Illuminate\Support\Facades\DB::connection('simrs')->table('pasien_tni')->count());
        $statPolri = Cache::remember('simrs_stat_polri', 300, fn() => \Illuminate\Support\Facades\DB::connection('simrs')->table('pasien_polri')->count());
        $statSipil = max(0, $totalPasien - $statTni - $statPolri);

        $distKelompok = Cache::remember('simrs_dist_kelompok_umur', 300, fn() => KelompokUmurPasien::selectRaw('kode_kelompok_umur, count(*) as total')->groupBy('kode_kelompok_umur')->pluck('total', 'kode_kelompok_umur'));

        return [
            'patients' => $this->buildQuery()->paginate($this->perPage),
            'kelompoks' => $kelompoks,
            'totalPasien' => $totalPasien,
            'totalSudah' => $totalSudah,
            'totalBelum' => $totalBelum,
            'lakiLaki' => $lakiLaki,
            'perempuan' => $perempuan,
            'statTni' => $statTni,
            'statPolri' => $statPolri,
            'statSipil' => $statSipil,
            'distKelompok' => $distKelompok,
        ];
    }
};
?>

<div>
    <x-ui.page-header title="Pasien" subtitle="Data pasien SIMRS beserta informasi kelompok umur dan status dinas">
        <x-slot:actions>
            <x-atoms.button variant="outline" icon="table-cells" wire:click="openMasterModal">
                Kelompok Umur
            </x-atoms.button>
            <x-atoms.button variant="primary" icon="calculator" wire:click="hitungSemuaPasien">
                Hitung Semua
            </x-atoms.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Tab Navigation --}}
    <div x-data="{ tab: 'ringkasan' }" class="space-y-6">

        <x-molecules.section-tabs model="tab" :items="[['key' => 'ringkasan', 'label' => 'Ringkasan'], ['key' => 'data-pasien', 'label' => 'Data Pasien']]" />

        {{-- ══════════════════ TAB: RINGKASAN ══════════════════ --}}
        <div x-show="tab === 'ringkasan'" x-cloak>

            {{-- Baris 1: Kelompok Umur --}}
            <div class="mb-4">
                <p class="mb-3 text-xs font-bold tracking-widest uppercase text-zinc-400 dark:text-primary-dark-500">
                    Kelompok Umur
                </p>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                    <x-organisms.stat-card title="Total Pasien" :value="number_format($totalPasien)" icon="users" color="zinc" />
                    <x-organisms.stat-card title="Sudah Dihitung" :value="number_format($totalSudah)" icon="check-circle" color="emerald"
                        :subtitle="number_format(round($totalPasien > 0 ? ($totalSudah / $totalPasien) * 100 : 0)) .
                            '% dari total'" />
                    <x-organisms.stat-card title="Belum Dihitung" :value="number_format($totalBelum)" icon="clock" color="amber"
                        :subtitle="number_format(round($totalPasien > 0 ? ($totalBelum / $totalPasien) * 100 : 0)) .
                            '% dari total'" />
                    @foreach ($kelompoks as $k)
                        @php $jml = $distKelompok[$k->kode] ?? 0; @endphp
                        <x-organisms.stat-card :title="$k->nama" :value="number_format($jml)" icon="user-group" color="blue"
                            :subtitle="$totalSudah > 0
                                ? number_format(round(($jml / $totalSudah) * 100)) . '% dari terhitung'
                                : '-'" />
                    @endforeach
                </div>
            </div>

            {{-- Baris 2: Jenis Kelamin & Dinas --}}
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

                {{-- Jenis Kelamin --}}
                <div>
                    <p
                        class="mb-3 text-xs font-bold tracking-widest uppercase text-zinc-400 dark:text-primary-dark-500">
                        Jenis Kelamin
                    </p>
                    <div class="grid grid-cols-2 gap-3">
                        <x-organisms.stat-card title="Laki-laki" :value="number_format($lakiLaki)" icon="user" color="blue"
                            :subtitle="$totalPasien > 0
                                ? number_format(round(($lakiLaki / $totalPasien) * 100)) . '% dari total'
                                : '-'" />
                        <x-organisms.stat-card title="Perempuan" :value="number_format($perempuan)" icon="user" color="violet"
                            :subtitle="$totalPasien > 0
                                ? number_format(round(($perempuan / $totalPasien) * 100)) . '% dari total'
                                : '-'" />
                    </div>
                </div>

                {{-- Dinas --}}
                <div>
                    <p
                        class="mb-3 text-xs font-bold tracking-widest uppercase text-zinc-400 dark:text-primary-dark-500">
                        Status Dinas
                    </p>
                    <div class="grid grid-cols-3 gap-3">
                        <x-organisms.stat-card title="TNI" :value="number_format($statTni)" icon="shield-check" color="sky"
                            :subtitle="$totalPasien > 0
                                ? number_format(round(($statTni / $totalPasien) * 100)) . '% dari total'
                                : '-'" />
                        <x-organisms.stat-card title="POLRI" :value="number_format($statPolri)" icon="shield-check" color="indigo"
                            :subtitle="$totalPasien > 0
                                ? number_format(round(($statPolri / $totalPasien) * 100)) . '% dari total'
                                : '-'" />
                        <x-organisms.stat-card title="Sipil" :value="number_format($statSipil)" icon="users" color="zinc"
                            :subtitle="$totalPasien > 0
                                ? number_format(round(($statSipil / $totalPasien) * 100)) . '% dari total'
                                : '-'" />
                    </div>
                </div>

            </div>
        </div>

        {{-- ══════════════════ TAB: DATA PASIEN ══════════════════ --}}
        <div x-show="tab === 'data-pasien'" x-cloak>
            <x-organisms.data-panel>
                <x-slot:filter>
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex-1 min-w-[200px]">
                            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                                placeholder="Cari no. RM, nama, NIK..." clearable />
                        </div>
                        <flux:select wire:model.live="filterDinas" class="sm:w-36">
                            <flux:select.option value="">Semua Dinas</flux:select.option>
                            <flux:select.option value="tni">TNI</flux:select.option>
                            <flux:select.option value="polri">POLRI</flux:select.option>
                            <flux:select.option value="sipil">Sipil</flux:select.option>
                        </flux:select>
                        <flux:select wire:model.live="filterJk" class="sm:w-36">
                            <flux:select.option value="">Semua Jenis Kelamin</flux:select.option>
                            <flux:select.option value="L">Laki-laki</flux:select.option>
                            <flux:select.option value="P">Perempuan</flux:select.option>
                        </flux:select>
                        <flux:select wire:model.live="filterKelompok" class="sm:w-52">
                            <flux:select.option value="">Semua Kelompok Umur</flux:select.option>
                            @foreach ($kelompoks as $k)
                                <flux:select.option value="{{ $k->kode }}">{{ $k->nama }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:select wire:model.live="filterHitung" class="sm:w-44">
                            <flux:select.option value="">Semua Status Hitung</flux:select.option>
                            <flux:select.option value="sudah">Sudah Dihitung</flux:select.option>
                            <flux:select.option value="belum">Belum Dihitung</flux:select.option>
                        </flux:select>
                        <flux:select wire:model.live="perPage" class="w-20">
                            <flux:select.option value="25">25</flux:select.option>
                            <flux:select.option value="50">50</flux:select.option>
                            <flux:select.option value="100">100</flux:select.option>
                        </flux:select>
                    </div>
                </x-slot:filter>

                <x-organisms.table>
                    <x-slot:headings>
                        <x-atoms.table-heading class="w-36">No. RM</x-atoms.table-heading>
                        <x-atoms.table-heading>Nama Pasien</x-atoms.table-heading>
                        <x-atoms.table-heading class="w-32">Jenis Kelamin</x-atoms.table-heading>
                        <x-atoms.table-heading class="w-32">Tgl Lahir</x-atoms.table-heading>
                        <x-atoms.table-heading class="w-64">Kelompok Umur</x-atoms.table-heading>
                        <x-atoms.table-heading class="w-20" align="center">Aksi</x-atoms.table-heading>
                    </x-slot:headings>

                    @forelse ($patients as $p)
                        @php $isOutdated = $p->tanggal_hitung && Carbon::parse($p->tanggal_hitung)->lt(today()); @endphp
                        <x-molecules.table-row wire:key="pat-{{ $p->no_rkm_medis }}">
                            <x-atoms.table-cell nowrap>
                                <span class="font-mono text-xs font-semibold text-zinc-700 dark:text-primary-dark-200">
                                    {{ $p->no_rkm_medis }}
                                </span>
                            </x-atoms.table-cell>

                            <x-atoms.table-cell>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span
                                        class="font-medium text-zinc-900 dark:text-primary-dark-100">{{ $p->nm_pasien }}</span>
                                    @if ($p->is_tni)
                                        <flux:badge size="sm" color="sky">TNI</flux:badge>
                                    @endif
                                    @if ($p->is_polri)
                                        <flux:badge size="sm" color="indigo">POLRI</flux:badge>
                                    @endif
                                </div>
                            </x-atoms.table-cell>

                            <x-atoms.table-cell>
                                <flux:badge size="sm" :color="$p->jk === 'L' ? 'blue' : 'pink'">
                                    {{ $p->jk === 'L' ? 'Laki-laki' : 'Perempuan' }}
                                </flux:badge>
                            </x-atoms.table-cell>

                            <x-atoms.table-cell nowrap class="text-zinc-500 dark:text-primary-dark-400">
                                {{ $p->tgl_lahir ? Carbon::parse($p->tgl_lahir)->format('d/m/Y') : '-' }}
                            </x-atoms.table-cell>

                            <x-atoms.table-cell>
                                @if ($p->kode_kelompok_umur)
                                    <flux:badge size="sm" :color="$isOutdated ? 'amber' : 'emerald'">
                                        {{ $p->kode_kelompok_umur }}
                                    </flux:badge>
                                    {{ $p->nama_kelompok_umur }}
                                @else
                                    <span class="text-xs italic text-zinc-400 dark:text-primary-dark-500">—</span>
                                @endif
                            </x-atoms.table-cell>

                            <x-atoms.table-cell align="center" :action="true">
                                <x-atoms.button variant="ghost" size="sm" icon="eye"
                                    wire:click="showPatientDetail('{{ $p->no_rkm_medis }}')" tooltip="Detail pasien">
                                </x-atoms.button>
                                <x-atoms.button variant="ghost" size="sm" icon="arrow-path"
                                    class="{{ $isOutdated ? 'text-amber-500' : '' }}"
                                    wire:click="hitungKelompokUmur('{{ $p->no_rkm_medis }}')" :tooltip="$isOutdated ? 'Hitung ulang (kedaluwarsa)' : 'Hitung kelompok umur'">
                                </x-atoms.button>
                            </x-atoms.table-cell>
                        </x-molecules.table-row>
                    @empty
                        <x-molecules.table-row>
                            <x-atoms.table-cell colspan="6">
                                <x-ui.empty-state icon="users" title="Tidak ada data pasien"
                                    description="Tidak ada pasien yang sesuai dengan filter yang dipilih." />
                            </x-atoms.table-cell>
                        </x-molecules.table-row>
                    @endforelse
                </x-organisms.table>

                <x-slot:footer>
                    @if ($patients->hasPages())
                        {{ $patients->links() }}
                    @endif
                </x-slot:footer>
            </x-organisms.data-panel>

        </div>{{-- end tab: data-pasien --}}
    </div>{{-- end x-data tab --}}

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- Modal: Detail Pasien                                       --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    <x-organisms.modal wire:model="showDetailModal" maxWidth="4xl">
        <x-slot:title>
            @if ($selectedPatient)
                <div class="flex items-center gap-3">
                    {{-- Avatar inisial --}}
                    <div
                        class="flex items-center justify-center w-11 h-11 rounded-full shrink-0 text-base font-bold text-white
                        {{ ($selectedPatient['jk'] ?? '') === 'L' ? 'bg-blue-500' : 'bg-pink-500' }}">
                        {{ strtoupper(substr($selectedPatient['nm_pasien'], 0, 1)) }}
                    </div>
                    <div>
                        <p class="text-base font-bold text-zinc-900 dark:text-primary-dark-100 leading-tight">
                            {{ $selectedPatient['nm_pasien'] }}
                        </p>
                        <div class="flex flex-wrap items-center gap-1.5 mt-0.5">
                            <span class="font-mono text-xs text-zinc-400 dark:text-primary-dark-500">
                                {{ $selectedPatient['no_rkm_medis'] }}
                            </span>
                            <flux:badge size="sm"
                                :color="($selectedPatient['jk'] ?? '') === 'L' ? 'blue' : 'pink'">
                                {{ ($selectedPatient['jk'] ?? '') === 'L' ? 'Laki-laki' : 'Perempuan' }}
                            </flux:badge>
                            @if ($selectedPatient['is_tni'])
                                <flux:badge size="sm" color="sky">TNI</flux:badge>
                            @endif
                            @if ($selectedPatient['is_polri'])
                                <flux:badge size="sm" color="indigo">POLRI</flux:badge>
                            @endif
                        </div>
                    </div>
                </div>
            @else
                Detail Pasien
            @endif
        </x-slot:title>

        @if ($selectedPatient)
            <div class="space-y-5">

                {{-- ── Data Pribadi ── --}}
                <div>
                    <p
                        class="mb-3 text-[10px] font-bold tracking-widest uppercase text-zinc-400 dark:text-primary-dark-500 flex items-center gap-2">
                        <span class="flex-1 h-px bg-zinc-100 dark:bg-primary-dark-700"></span>
                        Data Pribadi
                        <span class="flex-1 h-px bg-zinc-100 dark:bg-primary-dark-700"></span>
                    </p>
                    <div class="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3">
                        @php
                            $pribadi = [
                                'NIK / No. KTP' => $selectedPatient['no_ktp'] ?: '-',
                                'Tgl & Tmp Lahir' =>
                                    ($selectedPatient['tgl_lahir'] ?? '-') .
                                    ($selectedPatient['tmp_lahir'] ? ', ' . $selectedPatient['tmp_lahir'] : ''),
                                'Gol. Darah' => $selectedPatient['gol_darah'] ?: '-',
                                'Agama' => $selectedPatient['agama'] ?: '-',
                                'Status Nikah' => $selectedPatient['stts_nikah'],
                                'Pekerjaan' => $selectedPatient['pekerjaan'] ?: '-',
                                'No. Telepon' => $selectedPatient['no_tlp'] ?: '-',
                                'Suku Bangsa' => $selectedPatient['suku_bangsa'] ?: '-',
                                'Ibu Kandung' => $selectedPatient['nm_ibu'] ?: '-',
                                'Alamat' => $selectedPatient['alamat'] ?: '-',
                                'Penjamin' => $selectedPatient['penjab'],
                                'No. Peserta' => $selectedPatient['no_peserta'] ?: '-',
                                'Tgl Daftar' => $selectedPatient['tgl_daftar'] ?? '-',
                            ];
                        @endphp
                        @foreach ($pribadi as $label => $value)
                            <div>
                                <p class="text-[11px] font-medium text-zinc-400 dark:text-primary-dark-500">
                                    {{ $label }}</p>
                                <p class="mt-0.5 text-sm text-zinc-800 dark:text-primary-dark-100 break-words">
                                    {{ $value }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- ── Kelompok Umur ── --}}
                <div
                    class="flex flex-wrap items-center gap-4 px-4 py-3 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/40 border border-zinc-100 dark:border-primary-dark-700">
                    <div class="flex items-center gap-2">
                        <div
                            class="flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/30">
                            <flux:icon name="clock" class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <span class="text-xs font-semibold text-zinc-500 dark:text-primary-dark-400">Kelompok
                            Umur</span>
                    </div>
                    <div class="h-4 w-px bg-zinc-200 dark:bg-primary-dark-600"></div>
                    @if ($selectedPatient['kode_kelompok_umur'])
                        <div class="flex items-center gap-2">
                            <flux:badge size="sm" color="emerald">{{ $selectedPatient['kode_kelompok_umur'] }}
                            </flux:badge>
                            <span
                                class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">{{ $selectedPatient['kelompok_umur'] }}</span>
                        </div>
                        @if ($selectedPatient['umur_hari'])
                            <div class="h-4 w-px bg-zinc-200 dark:bg-primary-dark-600"></div>
                            <span class="text-sm text-zinc-600 dark:text-primary-dark-300">
                                <span class="font-semibold">{{ number_format($selectedPatient['umur_hari']) }}</span>
                                hari
                            </span>
                        @endif
                        @if ($selectedPatient['tanggal_hitung'])
                            <div class="h-4 w-px bg-zinc-200 dark:bg-primary-dark-600"></div>
                            <span class="text-xs text-zinc-400 dark:text-primary-dark-500">Dihitung:
                                {{ $selectedPatient['tanggal_hitung'] }}</span>
                        @endif
                    @else
                        <span class="text-sm italic text-zinc-400 dark:text-primary-dark-500">Belum dihitung</span>
                    @endif
                </div>

                {{-- ── Data TNI ── --}}
                @if ($selectedPatient['is_tni'] && $selectedPatient['tni'])
                    <div class="rounded-xl border border-sky-200 dark:border-sky-800/50 overflow-hidden">
                        <div class="flex items-center gap-2 px-4 py-2.5 bg-sky-50 dark:bg-sky-900/20">
                            <flux:icon name="shield-check" class="w-4 h-4 text-sky-600 dark:text-sky-400" />
                            <span
                                class="text-xs font-bold tracking-widest uppercase text-sky-600 dark:text-sky-400">Data
                                TNI</span>
                        </div>
                        <div
                            class="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3 px-4 py-3 bg-white dark:bg-primary-dark-800">
                            @foreach (['NRP' => $selectedPatient['tni']['nrp'] ?? '-', 'Pangkat' => $selectedPatient['tni']['pangkat'] ?? '-', 'Golongan' => $selectedPatient['tni']['golongan'] ?? '-', 'Satuan' => $selectedPatient['tni']['satuan'] ?? '-', 'Jabatan' => $selectedPatient['tni']['jabatan'] ?? '-'] as $label => $value)
                                <div>
                                    <p class="text-[11px] font-medium text-zinc-400 dark:text-primary-dark-500">
                                        {{ $label }}</p>
                                    <p class="mt-0.5 text-sm text-zinc-800 dark:text-primary-dark-100">
                                        {{ $value }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- ── Data POLRI ── --}}
                @if ($selectedPatient['is_polri'] && $selectedPatient['polri'])
                    <div class="rounded-xl border border-indigo-200 dark:border-indigo-800/50 overflow-hidden">
                        <div class="flex items-center gap-2 px-4 py-2.5 bg-indigo-50 dark:bg-indigo-900/20">
                            <flux:icon name="shield-check" class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                            <span
                                class="text-xs font-bold tracking-widest uppercase text-indigo-600 dark:text-indigo-400">Data
                                POLRI</span>
                        </div>
                        <div
                            class="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3 px-4 py-3 bg-white dark:bg-primary-dark-800">
                            @foreach (['NRP' => $selectedPatient['polri']['nrp'] ?? '-', 'Pangkat' => $selectedPatient['polri']['pangkat'] ?? '-', 'Golongan' => $selectedPatient['polri']['golongan'] ?? '-', 'Satuan' => $selectedPatient['polri']['satuan'] ?? '-', 'Jabatan' => $selectedPatient['polri']['jabatan'] ?? '-'] as $label => $value)
                                <div>
                                    <p class="text-[11px] font-medium text-zinc-400 dark:text-primary-dark-500">
                                        {{ $label }}</p>
                                    <p class="mt-0.5 text-sm text-zinc-800 dark:text-primary-dark-100">
                                        {{ $value }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- ── Riwayat Kunjungan ── --}}
                <div>
                    <p
                        class="mb-3 text-[10px] font-bold tracking-widest uppercase text-zinc-400 dark:text-primary-dark-500 flex items-center gap-2">
                        <span class="flex-1 h-px bg-zinc-100 dark:bg-primary-dark-700"></span>
                        Riwayat Kunjungan
                        <span
                            class="text-zinc-300 dark:text-primary-dark-600 font-normal normal-case tracking-normal">(15
                            terakhir)</span>
                        <span class="flex-1 h-px bg-zinc-100 dark:bg-primary-dark-700"></span>
                    </p>

                    @if (count($riwayatKunjungan))
                        <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-primary-dark-700">
                            <x-organisms.table>
                                <x-slot:headings>
                                    <x-atoms.table-heading>No. Rawat</x-atoms.table-heading>
                                    <x-atoms.table-heading class="w-24">Tanggal</x-atoms.table-heading>
                                    <x-atoms.table-heading>Poli / Ruang</x-atoms.table-heading>
                                    <x-atoms.table-heading>Dokter</x-atoms.table-heading>
                                    <x-atoms.table-heading class="w-28">Jenis</x-atoms.table-heading>
                                    <x-atoms.table-heading class="w-24">Status</x-atoms.table-heading>
                                    <x-atoms.table-heading>Penjamin</x-atoms.table-heading>
                                </x-slot:headings>

                                @foreach ($riwayatKunjungan as $r)
                                    <x-molecules.table-row wire:key="rw-{{ $r['no_rawat'] }}">
                                        <x-atoms.table-cell nowrap>
                                            <span
                                                class="font-mono text-xs font-semibold text-zinc-600 dark:text-primary-dark-300">
                                                {{ $r['no_rawat'] }}
                                            </span>
                                        </x-atoms.table-cell>
                                        <x-atoms.table-cell nowrap
                                            class="text-zinc-500 dark:text-primary-dark-400 text-xs">
                                            {{ $r['tgl_registrasi'] }}
                                        </x-atoms.table-cell>
                                        <x-atoms.table-cell class="text-xs">{{ $r['poli'] }}</x-atoms.table-cell>
                                        <x-atoms.table-cell nowrap
                                            class="text-xs">{{ $r['dokter'] }}</x-atoms.table-cell>
                                        <x-atoms.table-cell nowrap>
                                            <flux:badge size="sm"
                                                :color="$r['status_lanjut'] === 'Ranap' ? 'amber' : 'blue'">
                                                {{ $r['status_lanjut'] === 'Ranap' ? 'Ranap' : 'Ralan' }}
                                            </flux:badge>
                                        </x-atoms.table-cell>
                                        <x-atoms.table-cell nowrap
                                            class="text-xs text-zinc-500 dark:text-primary-dark-400">
                                            {{ $r['stts'] }}
                                        </x-atoms.table-cell>
                                        <x-atoms.table-cell class="text-xs text-zinc-500 dark:text-primary-dark-400">
                                            {{ $r['penjab'] }}
                                        </x-atoms.table-cell>
                                    </x-molecules.table-row>
                                @endforeach
                            </x-organisms.table>
                        </div>
                    @else
                        <x-ui.empty-state icon="calendar-days" title="Belum ada riwayat kunjungan"
                            description="Pasien ini belum memiliki data kunjungan." />
                    @endif
                </div>

            </div>
        @endif
    </x-organisms.modal>

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- Modal: Master Kelompok Umur                                 --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    <x-organisms.modal wire:model="showMasterModal" maxWidth="2xl" title="Master Kelompok Umur"
        description="Rentang umur pasien dalam satuan hari">

        <div class="space-y-5">

            {{-- ── Form ── --}}
            <div class="rounded-xl border border-zinc-200 dark:border-primary-dark-700 overflow-hidden">
                <div
                    class="flex items-center gap-2 px-4 py-2.5 bg-zinc-50 dark:bg-primary-dark-900/50 border-b border-zinc-200 dark:border-primary-dark-700">
                    <flux:icon name="{{ $editingKode ? 'pencil-square' : 'plus-circle' }}"
                        class="w-4 h-4 text-zinc-400 dark:text-primary-dark-500" />
                    <span class="text-xs font-semibold text-zinc-600 dark:text-primary-dark-300">
                        {{ $editingKode ? "Edit Kelompok: {$editingKode}" : 'Tambah Kelompok Umur' }}
                    </span>
                </div>
                <div class="p-4 space-y-3 bg-white dark:bg-primary-dark-800">
                    <div class="grid grid-cols-5 gap-3">
                        <div>
                            <flux:label class="text-xs">Kode</flux:label>
                            <flux:input wire:model="formKode" placeholder="NEO" class="mt-1 font-mono"
                                :readonly="(bool) $editingKode" maxlength="5" />
                            <flux:error name="formKode" />
                        </div>
                        <div class="col-span-2">
                            <flux:label class="text-xs">Nama Kelompok</flux:label>
                            <flux:input wire:model="formNama" placeholder="Neonatus" class="mt-1" />
                            <flux:error name="formNama" />
                        </div>
                        <div>
                            <flux:label class="text-xs">Min (hari)</flux:label>
                            <flux:input wire:model="formUmurMin" type="number" min="0" placeholder="0"
                                class="mt-1" />
                            <flux:error name="formUmurMin" />
                        </div>
                        <div>
                            <flux:label class="text-xs">Max (kosong=∞)</flux:label>
                            <flux:input wire:model="formUmurMax" type="number" min="0" placeholder="∞"
                                class="mt-1" />
                        </div>
                    </div>
                    <div class="flex items-end gap-3">
                        <div class="w-28">
                            <flux:label class="text-xs">Urut Tampil</flux:label>
                            <flux:input wire:model="formUrut" type="number" min="1" placeholder="1"
                                class="mt-1" />
                            <flux:error name="formUrut" />
                        </div>
                        <div class="flex items-center gap-2 pb-0.5">
                            <x-atoms.button wire:click="saveKelompokUmur" variant="primary" icon="check">
                                {{ $editingKode ? 'Perbarui' : 'Simpan' }}
                            </x-atoms.button>
                            @if ($editingKode)
                                <x-atoms.button wire:click="deleteKelompokUmur('{{ $editingKode }}')"
                                    wire:confirm="Hapus kelompok umur ini?" variant="danger" icon="trash">
                                    Hapus
                                </x-atoms.button>
                            @endif
                            <x-atoms.button wire:click="resetKelompokUmurForm" variant="ghost">
                                Reset
                            </x-atoms.button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Tabel ── --}}
            <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-primary-dark-700">
                <x-organisms.table>
                    <x-slot:headings>
                        <x-atoms.table-heading class="w-20">Kode</x-atoms.table-heading>
                        <x-atoms.table-heading>Nama</x-atoms.table-heading>
                        <x-atoms.table-heading align="right" class="w-24">Min</x-atoms.table-heading>
                        <x-atoms.table-heading align="right" class="w-24">Max</x-atoms.table-heading>
                        <x-atoms.table-heading align="center" class="w-14">Urut</x-atoms.table-heading>
                        <x-atoms.table-heading class="w-10"></x-atoms.table-heading>
                    </x-slot:headings>

                    @forelse ($kelompoks as $k)
                        <x-molecules.table-row wire:key="ku-{{ $k->kode }}"
                            class="{{ $editingKode === $k->kode ? '!bg-primary-50 dark:!bg-primary-900/20' : '' }}">
                            <x-atoms.table-cell>
                                <flux:badge size="sm" color="zinc" class="font-mono font-bold">
                                    {{ $k->kode }}</flux:badge>
                            </x-atoms.table-cell>
                            <x-atoms.table-cell>
                                <span
                                    class="text-sm font-medium text-zinc-800 dark:text-primary-dark-100">{{ $k->nama }}</span>
                            </x-atoms.table-cell>
                            <x-atoms.table-cell align="right" class="font-mono text-xs text-zinc-500">
                                {{ number_format($k->umur_min) }}
                            </x-atoms.table-cell>
                            <x-atoms.table-cell align="right" class="font-mono text-xs text-zinc-500">
                                {{ $k->umur_max !== null ? number_format($k->umur_max) : '∞' }}
                            </x-atoms.table-cell>
                            <x-atoms.table-cell align="center"
                                class="text-zinc-400 text-xs">{{ $k->urut }}</x-atoms.table-cell>
                            <x-atoms.table-cell align="center" :action="true">
                                <x-atoms.button variant="ghost" size="sm"
                                    wire:click="editKelompokUmur('{{ $k->kode }}')" tooltip="Edit"
                                    icon="pencil-square" />
                            </x-atoms.table-cell>
                        </x-molecules.table-row>
                    @empty
                        <x-molecules.table-row>
                            <x-atoms.table-cell colspan="6">
                                <x-ui.empty-state icon="table-cells" title="Belum ada data"
                                    description="Tambahkan kelompok umur melalui form di atas." />
                            </x-atoms.table-cell>
                        </x-molecules.table-row>
                    @endforelse
                </x-organisms.table>
            </div>

        </div>
    </x-organisms.modal>
</div>
