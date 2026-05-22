<?php

use App\Models\Simrs\BridgingSep;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('BPJS — vClaim SEP')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterJenis = '';

    // Mode filter tanggal: '' | 'tanggal' | 'bulan' | 'tahun'
    #[Url]
    public string $filterMode = '';

    #[Url]
    public string $filterTanggal = '';

    #[Url]
    public string $filterTanggalSampai = '';

    #[Url]
    public string $filterBulan = '';

    #[Url]
    public string $filterTahun = '';

    public int $perPage = 25;

    public bool $showDetailModal = false;
    public ?array $selectedSep = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }
    public function updatedFilterJenis(): void
    {
        $this->resetPage();
    }
    public function updatedFilterTanggal(): void { $this->resetPage(); }
    public function updatedFilterTanggalSampai(): void { $this->resetPage(); }
    public function updatedFilterBulan(): void
    {
        $this->resetPage();
    }
    public function updatedFilterTahun(): void
    {
        $this->resetPage();
    }

    public function setFilterMode(string $mode): void
    {
        $isToggleOff = $this->filterMode === $mode;
        $this->filterMode = $isToggleOff ? '' : $mode;
        $this->filterTanggal = !$isToggleOff && $mode === 'tanggal' ? now()->format('Y-m-d') : '';
        $this->filterTanggalSampai = '';
        $this->filterBulan = '';
        $this->filterTahun = '';
        $this->resetPage();
    }

    public function showDetail(string $noSep): void
    {
        try {
            $sep = BridgingSep::where('no_sep', $noSep)->first();
            if (!$sep) {
                return;
            }

            $this->selectedSep = [
                'no_sep' => $sep->no_sep,
                'no_rawat' => $sep->no_rawat,
                'nomr' => $sep->nomr,
                'nama_pasien' => $sep->nama_pasien,
                'no_kartu' => $sep->no_kartu,
                'peserta' => $sep->peserta,
                'jkel' => $sep->jkel,
                'tanggal_lahir' => $sep->tanggal_lahir,
                'notelep' => $sep->notelep,
                'tglsep' => $sep->tglsep?->format('d/m/Y'),
                'tglrujukan' => $sep->tglrujukan?->format('d/m/Y'),
                'no_rujukan' => $sep->no_rujukan,
                'is_igd' => $sep->isIgd(),
                'jnspelayanan' => $sep->jnspelayanan,
                'kdpolitujuan' => $sep->kdpolitujuan,
                'nmpolitujuan' => $sep->nmpolitujuan,
                'diagawal' => $sep->diagawal,
                'nmdiagnosaawal' => $sep->nmdiagnosaawal,
                'klsrawat' => $sep->klsrawat,
                'klsnaik' => $sep->klsnaik,
                'kdppkrujukan' => $sep->kdppkrujukan,
                'nmppkrujukan' => $sep->nmppkrujukan,
                'kddpjp' => $sep->kddpjp,
                'nmdpdjp' => $sep->nmdpdjp,
                'catatan' => $sep->catatan,
                'backdate' => $sep->backdate,
                'suplesi' => $sep->suplesi,
                'no_sep_suplesi' => $sep->no_sep_suplesi,
                'flagprosedur' => $sep->flagprosedur,
                'cob' => $sep->cob,
                'katarak' => $sep->katarak,
            ];
            $this->showDetailModal = true;
        } catch (\Throwable) {
        }
    }

    private function applyDateFilter($query): void
    {
        if ($this->filterMode === 'tanggal' && $this->filterTanggal) {
            if ($this->filterTanggalSampai) {
                $query->whereDate('tglsep', '>=', $this->filterTanggal)
                      ->whereDate('tglsep', '<=', $this->filterTanggalSampai);
            } else {
                $query->whereDate('tglsep', $this->filterTanggal);
            }
        } elseif ($this->filterMode === 'bulan' && $this->filterTahun) {
            $query->whereYear('tglsep', $this->filterTahun);
            if ($this->filterBulan) {
                $query->whereMonth('tglsep', $this->filterBulan);
            }
        } elseif ($this->filterMode === 'tahun' && $this->filterTahun) {
            $query->whereYear('tglsep', $this->filterTahun);
        }
    }

    public function with(): array
    {
        $totalRalan = $totalRanap = $totalIgd = $totalAll = 0;

        try {
            $qRalan = BridgingSep::ralanOnly();
            $qRanap = BridgingSep::where('jnspelayanan', '1');
            $qIgd   = BridgingSep::igd();

            $this->applyDateFilter($qRalan);
            $this->applyDateFilter($qRanap);
            $this->applyDateFilter($qIgd);

            $totalRalan = $qRalan->count();
            $totalRanap = $qRanap->count();
            $totalIgd   = $qIgd->count();
            $totalAll   = $totalRalan + $totalRanap + $totalIgd;
        } catch (\Throwable) {}

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator([], 0, $this->perPage);

        try {
            $query = BridgingSep::query();

            if ($search = trim($this->search)) {
                $query->search($search);
            }

            if ($this->filterJenis === 'ralan') {
                $query->ralanOnly();
            } elseif ($this->filterJenis === 'ranap') {
                $query->where('jnspelayanan', '1');
            } elseif ($this->filterJenis === 'igd') {
                $query->igd();
            }

            $this->applyDateFilter($query);

            $paginator = $query->orderByDesc('tglsep')->paginate($this->perPage);
        } catch (\Throwable) {}


        $years = range(now()->year, max(now()->year - 5, 2020));
        $months = [
            '1' => 'Januari',
            '2' => 'Februari',
            '3' => 'Maret',
            '4' => 'April',
            '5' => 'Mei',
            '6' => 'Juni',
            '7' => 'Juli',
            '8' => 'Agustus',
            '9' => 'September',
            '10' => 'Oktober',
            '11' => 'November',
            '12' => 'Desember',
        ];

        return compact('totalAll', 'totalRalan', 'totalRanap', 'totalIgd', 'paginator', 'years', 'months');
    }
};

?>

<div class="space-y-6 pb-12">
    <x-ui.page-header title="BPJS — vClaim SEP"
        subtitle="Data pasien yang memiliki Surat Elegibilitas Peserta (SEP) dari SIMRS.">
        <x-slot:actions>
            <div class="flex items-center gap-2 flex-wrap justify-end">
                {{-- Toggle mode filter tanggal --}}
                @foreach (['tanggal' => 'Per Tanggal', 'bulan' => 'Per Bulan', 'tahun' => 'Per Tahun'] as $mode => $label)
                    <x-atoms.button wire:click="setFilterMode('{{ $mode }}')" :variant="$filterMode === $mode ? 'primary' : 'outline'">
                        {{ $label }}
                    </x-atoms.button>
                @endforeach

                {{-- Input dinamis berdasarkan mode --}}
                @if ($filterMode === 'tanggal')
                    <flux:input type="date" wire:model.live="filterTanggal" class="!w-38" />
                    <span class="text-xs text-zinc-400 shrink-0">s/d</span>
                    <flux:input type="date" wire:model.live="filterTanggalSampai" class="!w-38"
                        :min="$filterTanggal" />
                @elseif ($filterMode === 'bulan')
                    <flux:select wire:model.live="filterBulan" class="!w-32">
                        <flux:select.option value="">Semua Bulan</flux:select.option>
                        @foreach ($months as $num => $nama)
                            <flux:select.option value="{{ $num }}">{{ $nama }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="filterTahun" class="!w-24">
                        <flux:select.option value="">Tahun</flux:select.option>
                        @foreach ($years as $y)
                            <flux:select.option value="{{ $y }}">{{ $y }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @elseif ($filterMode === 'tahun')
                    <flux:select wire:model.live="filterTahun" class="!w-24">
                        <flux:select.option value="">Tahun</flux:select.option>
                        @foreach ($years as $y)
                            <flux:select.option value="{{ $y }}">{{ $y }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Stats --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <x-organisms.stat-card title="Total SEP" :value="number_format($totalAll)" color="zinc" icon="document-text" />
        <x-organisms.stat-card title="Rawat Jalan" :value="number_format($totalRalan)" color="blue" icon="user" />
        <x-organisms.stat-card title="Rawat Inap" :value="number_format($totalRanap)" color="emerald" icon="home-modern" />
        <x-organisms.stat-card title="IGD" :value="number_format($totalIgd)" color="red" icon="exclamation-triangle" />
    </div>

    <x-organisms.data-panel>
        <x-slot:filter>
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-[220px]">
                    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                        placeholder="Cari No. SEP, No. Rawat, No. MR, nama pasien..." clearable />
                </div>
                <flux:select wire:model.live="filterJenis" class="sm:w-44">
                    <flux:select.option value="">Semua Jenis</flux:select.option>
                    <flux:select.option value="ralan">Rawat Jalan</flux:select.option>
                    <flux:select.option value="ranap">Rawat Inap</flux:select.option>
                    <flux:select.option value="igd">IGD</flux:select.option>
                </flux:select>
                <flux:select wire:model.live="perPage" class="w-36 shrink-0">
                    <flux:select.option value="25">25 / hal</flux:select.option>
                    <flux:select.option value="50">50 / hal</flux:select.option>
                    <flux:select.option value="100">100 / hal</flux:select.option>
                </flux:select>
            </div>
        </x-slot:filter>

        <x-organisms.table>
            <x-slot:headings>
                <x-atoms.table-heading>No. SEP</x-atoms.table-heading>
                <x-atoms.table-heading>Pasien</x-atoms.table-heading>
                <x-atoms.table-heading class="hidden md:table-cell">No. Kartu</x-atoms.table-heading>
                <x-atoms.table-heading class="hidden lg:table-cell">Tgl SEP</x-atoms.table-heading>
                <x-atoms.table-heading class="hidden lg:table-cell">Jenis</x-atoms.table-heading>
                <x-atoms.table-heading class="hidden xl:table-cell">Poliklinik Tujuan</x-atoms.table-heading>
                <x-atoms.table-heading class="hidden xl:table-cell">Diagnosa Awal</x-atoms.table-heading>
                <x-atoms.table-heading align="center" class="w-24">Aksi</x-atoms.table-heading>
            </x-slot:headings>

            @forelse ($paginator as $row)
                @php $isIgd = $row->isIgd(); @endphp
                <x-molecules.table-row wire:key="sep-{{ $row->no_sep }}">
                    {{-- No. SEP --}}
                    <x-atoms.table-cell nowrap>
                        <span
                            class="font-mono text-xs font-semibold text-zinc-700 dark:text-primary-dark-200">{{ $row->no_sep }}</span>
                    </x-atoms.table-cell>

                    {{-- Pasien --}}
                    <x-atoms.table-cell>
                        <p class="text-sm font-medium text-zinc-800 dark:text-primary-dark-100">{{ $row->nama_pasien }}
                        </p>
                        <p class="text-xs text-zinc-400 font-mono">{{ $row->nomr }} · {{ $row->no_rawat }}</p>
                    </x-atoms.table-cell>

                    {{-- No. Kartu --}}
                    <x-atoms.table-cell class="hidden md:table-cell" nowrap>
                        <span
                            class="font-mono text-xs text-zinc-600 dark:text-primary-dark-300">{{ $row->no_kartu ?: '-' }}</span>
                    </x-atoms.table-cell>

                    {{-- Tgl SEP --}}
                    <x-atoms.table-cell class="hidden lg:table-cell" nowrap>
                        <span
                            class="text-sm text-zinc-600 dark:text-primary-dark-300">{{ $row->tglsep?->format('d/m/Y') ?? '-' }}</span>
                    </x-atoms.table-cell>

                    {{-- Jenis Pelayanan --}}
                    <x-atoms.table-cell class="hidden lg:table-cell" nowrap>
                        @if ((string) $row->jnspelayanan === '1')
                            <flux:badge color="emerald" size="sm">Rawat Inap</flux:badge>
                        @elseif ($isIgd)
                            <flux:badge color="red" size="sm">IGD</flux:badge>
                        @else
                            <flux:badge color="blue" size="sm">Rawat Jalan</flux:badge>
                        @endif
                    </x-atoms.table-cell>

                    {{-- Poliklinik Tujuan --}}
                    <x-atoms.table-cell class="hidden xl:table-cell">
                        <span
                            class="text-sm text-zinc-600 dark:text-primary-dark-300">{{ $row->nmpolitujuan ?: '-' }}</span>
                    </x-atoms.table-cell>

                    {{-- Diagnosa Awal --}}
                    <x-atoms.table-cell class="hidden xl:table-cell">
                        @if ($row->diagawal)
                            <span class="inline-flex items-center gap-1.5">
                                <flux:badge color="zinc" size="sm">{{ $row->diagawal }}</flux:badge>
                                <span class="text-xs text-zinc-500 dark:text-primary-dark-400 truncate max-w-[160px]"
                                    title="{{ $row->nmdiagnosaawal }}">{{ $row->nmdiagnosaawal }}</span>
                            </span>
                        @else
                            <span class="text-xs text-zinc-400">-</span>
                        @endif
                    </x-atoms.table-cell>

                    {{-- Aksi --}}
                    <x-atoms.table-cell :action="true" align="center" nowrap>
                        <div class="flex items-center justify-center gap-1">
                            <x-atoms.button wire:click="showDetail('{{ $row->no_sep }}')" size="sm"
                                variant="ghost" icon="eye" tooltip="Detail SEP" />
                            <a href="{{ route('erm.detail', ['noRawat' => $row->no_rawat]) }}"
                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg hover:bg-zinc-100 dark:hover:bg-primary-dark-700 transition-colors text-zinc-500 dark:text-primary-dark-400 hover:text-primary-600"
                                title="Buka eRM">
                                <flux:icon name="document-text" class="w-4 h-4" />
                            </a>
                        </div>
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @empty
                <x-molecules.table-row>
                    <x-atoms.table-cell colspan="8" align="center" class="py-16">
                        <div class="flex flex-col items-center gap-3">
                            <div
                                class="flex h-14 w-14 items-center justify-center rounded-2xl bg-zinc-100 dark:bg-primary-dark-700">
                                <flux:icon name="document-text"
                                    class="h-7 w-7 text-zinc-300 dark:text-primary-dark-500" />
                            </div>
                            <p class="text-sm font-semibold text-zinc-500 dark:text-primary-dark-400">Tidak ada data SEP
                            </p>
                        </div>
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @endforelse
        </x-organisms.table>

        @if ($paginator->hasPages())
            <x-slot:footer>
                {{ $paginator->links() }}
            </x-slot:footer>
        @endif
    </x-organisms.data-panel>

    {{-- Modal Detail SEP --}}
    <x-organisms.modal wire:model="showDetailModal" maxWidth="3xl" title="Detail SEP">
        @if ($selectedSep)
            <div class="space-y-6">
                {{-- Header --}}
                <div class="flex items-center gap-4 p-1">
                    <div
                        class="flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-100 dark:bg-blue-900/30 shrink-0 text-blue-600 dark:text-blue-400 text-2xl font-black">
                        {{ strtoupper(substr($selectedSep['nama_pasien'] ?? '?', 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-lg font-bold text-zinc-900 dark:text-primary-dark-100 truncate">
                            {{ $selectedSep['nama_pasien'] }}
                        </h3>
                        <div class="flex flex-wrap items-center gap-3 mt-1">
                            <span class="font-mono text-xs text-zinc-500 dark:text-primary-dark-400">MR:
                                {{ $selectedSep['nomr'] }}</span>
                            <span class="font-mono text-xs text-zinc-500 dark:text-primary-dark-400">No. Kartu:
                                {{ $selectedSep['no_kartu'] ?: '-' }}</span>
                            @if ((string) $selectedSep['jnspelayanan'] === '1')
                                <flux:badge color="emerald" size="sm">Rawat Inap</flux:badge>
                            @elseif ($selectedSep['is_igd'])
                                <flux:badge color="red" size="sm">IGD</flux:badge>
                            @else
                                <flux:badge color="blue" size="sm">Rawat Jalan</flux:badge>
                            @endif
                            @if ($selectedSep['backdate'])
                                <flux:badge color="orange" size="sm">Backdate</flux:badge>
                            @endif
                            @if ($selectedSep['suplesi'])
                                <flux:badge color="purple" size="sm">Suplesi</flux:badge>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Data SEP --}}
                    <div class="space-y-4">
                        <p class="text-[10px] font-black uppercase tracking-widest text-zinc-400">Data SEP</p>
                        <div class="grid grid-cols-2 gap-x-6 gap-y-3">
                            <div>
                                <p class="text-[11px] font-bold text-zinc-400 uppercase mb-0.5">No. SEP</p>
                                <p
                                    class="font-mono text-xs font-semibold text-zinc-700 dark:text-primary-dark-200 break-all">
                                    {{ $selectedSep['no_sep'] }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] font-bold text-zinc-400 uppercase mb-0.5">No. Rawat</p>
                                <p class="font-mono text-xs text-zinc-600 dark:text-primary-dark-300">
                                    {{ $selectedSep['no_rawat'] }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] font-bold text-zinc-400 uppercase mb-0.5">Tgl SEP</p>
                                <p class="text-sm text-zinc-700 dark:text-primary-dark-200">
                                    {{ $selectedSep['tglsep'] ?? '-' }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] font-bold text-zinc-400 uppercase mb-0.5">Tgl Rujukan</p>
                                <p class="text-sm text-zinc-700 dark:text-primary-dark-200">
                                    {{ $selectedSep['tglrujukan'] ?? '-' }}</p>
                            </div>
                            <div class="col-span-2">
                                <p class="text-[11px] font-bold text-zinc-400 uppercase mb-0.5">No. Rujukan</p>
                                <p class="font-mono text-xs text-zinc-600 dark:text-primary-dark-300">
                                    {{ $selectedSep['no_rujukan'] ?: '-' }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] font-bold text-zinc-400 uppercase mb-0.5">Kelas Rawat</p>
                                <p class="text-sm text-zinc-700 dark:text-primary-dark-200">
                                    {{ $selectedSep['klsrawat'] ?: '-' }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] font-bold text-zinc-400 uppercase mb-0.5">Naik Kelas</p>
                                <p class="text-sm text-zinc-700 dark:text-primary-dark-200">
                                    {{ $selectedSep['klsnaik'] ?: '-' }}</p>
                            </div>
                            <div class="col-span-2">
                                <p class="text-[11px] font-bold text-zinc-400 uppercase mb-0.5">Diagnosa Awal</p>
                                <p class="text-sm text-zinc-700 dark:text-primary-dark-200">
                                    <span class="font-mono font-bold">{{ $selectedSep['diagawal'] ?: '-' }}</span>
                                    @if ($selectedSep['nmdiagnosaawal'])
                                        — {{ $selectedSep['nmdiagnosaawal'] }}
                                    @endif
                                </p>
                            </div>
                            <div class="col-span-2">
                                <p class="text-[11px] font-bold text-zinc-400 uppercase mb-0.5">Poliklinik Tujuan</p>
                                <p class="text-sm text-zinc-700 dark:text-primary-dark-200">
                                    {{ $selectedSep['nmpolitujuan'] ?: '-' }}</p>
                            </div>
                        </div>
                    </div>

                    {{-- Data Peserta & Fasilitas --}}
                    <div class="space-y-4">
                        <p class="text-[10px] font-black uppercase tracking-widest text-zinc-400">Peserta & Fasilitas
                        </p>
                        <div class="grid grid-cols-2 gap-x-6 gap-y-3">
                            <div>
                                <p class="text-[11px] font-bold text-zinc-400 uppercase mb-0.5">Jenis Peserta</p>
                                <p class="text-sm text-zinc-700 dark:text-primary-dark-200">
                                    {{ $selectedSep['peserta'] ?: '-' }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] font-bold text-zinc-400 uppercase mb-0.5">Jenis Kelamin</p>
                                <p class="text-sm text-zinc-700 dark:text-primary-dark-200">
                                    {{ $selectedSep['jkel'] ?: '-' }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] font-bold text-zinc-400 uppercase mb-0.5">Tgl Lahir</p>
                                <p class="text-sm text-zinc-700 dark:text-primary-dark-200">
                                    {{ $selectedSep['tanggal_lahir'] ?: '-' }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] font-bold text-zinc-400 uppercase mb-0.5">No. Telepon</p>
                                <p class="font-mono text-sm text-zinc-700 dark:text-primary-dark-200">
                                    {{ $selectedSep['notelep'] ?: '-' }}</p>
                            </div>
                            <div class="col-span-2">
                                <p class="text-[11px] font-bold text-zinc-400 uppercase mb-0.5">PPK Perujuk</p>
                                <p class="text-sm text-zinc-700 dark:text-primary-dark-200">
                                    <span class="font-mono text-xs">{{ $selectedSep['kdppkrujukan'] ?: '-' }}</span>
                                    @if ($selectedSep['nmppkrujukan'])
                                        — {{ $selectedSep['nmppkrujukan'] }}
                                    @endif
                                </p>
                            </div>
                            <div class="col-span-2">
                                <p class="text-[11px] font-bold text-zinc-400 uppercase mb-0.5">DPJP</p>
                                <p class="text-sm text-zinc-700 dark:text-primary-dark-200">
                                    <span class="font-mono text-xs">{{ $selectedSep['kddpjp'] ?: '-' }}</span>
                                    @if ($selectedSep['nmdpdjp'])
                                        — {{ $selectedSep['nmdpdjp'] }}
                                    @endif
                                </p>
                            </div>
                            @if ($selectedSep['catatan'])
                                <div class="col-span-2">
                                    <p class="text-[11px] font-bold text-zinc-400 uppercase mb-0.5">Catatan</p>
                                    <p class="text-sm text-zinc-600 dark:text-primary-dark-300 italic">
                                        {{ $selectedSep['catatan'] }}</p>
                                </div>
                            @endif
                            @if ($selectedSep['suplesi'] && $selectedSep['no_sep_suplesi'])
                                <div class="col-span-2">
                                    <p class="text-[11px] font-bold text-zinc-400 uppercase mb-0.5">No. SEP Suplesi</p>
                                    <p class="font-mono text-xs text-purple-600 dark:text-purple-400">
                                        {{ $selectedSep['no_sep_suplesi'] }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <x-slot:footer>
            <div class="flex justify-between items-center w-full">
                @if ($selectedSep)
                    <a href="{{ route('erm.detail', ['noRawat' => $selectedSep['no_rawat']]) }}"
                        class="inline-flex items-center gap-2 text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400">
                        <flux:icon name="document-text" class="w-4 h-4" />
                        Buka eRM Detail
                    </a>
                @else
                    <span></span>
                @endif
                <x-atoms.button wire:click="$set('showDetailModal', false)" variant="ghost">Tutup</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>
</div>
