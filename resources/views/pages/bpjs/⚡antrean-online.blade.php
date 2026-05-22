<?php

use App\Jobs\PullAntreanJob;
use App\Models\Bpjs\BpjsAntreanBooking;
use App\Models\Bpjs\BpjsAntreanRegistration;
use App\Models\Bpjs\BpjsLog;
use App\Models\Simrs\RegPeriksa;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('BPJS — Antrean Online')] class extends Component {
    #[Url]
    public string $tab = 'rekap';

    // Range untuk Tarik Antrean
    #[Url]
    public string $dateFrom = '';
    #[Url]
    public string $dateTo = '';

    // Filter Tab Antrean Per Tanggal
    #[Url]
    public string $filterDate = '';
    #[Url]
    public string $filterStatus = '';
    #[Url]
    public string $filterSumber = '';

    // Filter Rekap
    #[Url]
    public string $rekapPeriod = 'daily';
    #[Url]
    public string $rekapFrom = '';
    #[Url]
    public string $rekapTo = '';

    // Modal konfirmasi pull
    public bool $showPullModal = false;
    public array $modalConflicts = [];

    public bool $showDatesModal = false;

    // Modal detail rekap
    public bool $showDetailModal = false;
    public string $modalDetailType = '';

    // Paginasi tab Antrean Per Tanggal
    public int $antreanPage = 1;
    public int $perPage = 10;

    // Paginasi tab Riwayat Penarikan
    public int $riwayatPage = 1;
    public int $riwayatPerPage = 20;
    public bool $showLogDetailModal = false;
    public ?string $logDetailId = null;

    /** Timeout per-tanggal dalam detik yang diteruskan ke job */
    public int $pullTimeout = 15;

    public function mount(): void
    {
        $today = now()->format('Y-m-d');
        $this->dateFrom = $this->dateFrom ?: $today;
        $this->dateTo = $this->dateTo ?: $today;
        $this->filterDate = $this->filterDate ?: $today;
        $this->rekapFrom = $this->rekapFrom ?: $today;
        $this->rekapTo = $this->rekapTo ?: $today;
    }

    public function switchTab(string $tab): void
    {
        $this->tab = $tab;
        $this->filterStatus = '';
        $this->filterSumber = '';
        $this->antreanPage = 1;
        $this->riwayatPage = 1;
    }

    public function viewLogDetail(string $id): void
    {
        $this->logDetailId = $id;
        $this->showLogDetailModal = true;
    }

    public function viewDate(string $date): void
    {
        $this->filterDate = $date;
        $this->tab = 'antrean';
        $this->antreanPage = 1;
    }

    public function openDetailModal(string $type): void
    {
        $this->modalDetailType = $type;
        $this->showDetailModal = true;
    }

    public function updatedFilterDate(): void
    {
        $this->antreanPage = 1;
    }
    public function updatedFilterStatus(): void
    {
        $this->antreanPage = 1;
    }
    public function updatedFilterSumber(): void
    {
        $this->antreanPage = 1;
    }

    public function nextPage(): void
    {
        $this->antreanPage++;
    }

    public function prevPage(): void
    {
        $this->antreanPage = max(1, $this->antreanPage - 1);
    }

    public function gotoAntreanPage(int $page): void
    {
        $this->antreanPage = $page;
    }

    public function nextRiwayatPage(): void
    {
        $this->riwayatPage++;
    }

    public function prevRiwayatPage(): void
    {
        $this->riwayatPage = max(1, $this->riwayatPage - 1);
    }

    public function gotoRiwayatPage(int $page): void
    {
        $this->riwayatPage = $page;
    }

    public function updatedRekapPeriod(): void
    {
        $today = now()->format('Y-m-d');
        if ($this->rekapPeriod === 'daily') {
            $this->rekapFrom = $today;
            $this->rekapTo = $today;
        } elseif ($this->rekapPeriod === 'monthly') {
            $this->rekapFrom = now()->format('Y-m');
            $this->rekapTo = '';
        } else {
            $this->rekapFrom = now()->format('Y');
            $this->rekapTo = '';
        }
    }

    /** Cek konflik per tanggal dalam range, tampilkan modal jika ada */
    public function pullData(): void
    {
        if (!$this->dateFrom || !$this->dateTo) {
            $this->dispatch('toast', type: 'warning', message: 'Tentukan range tanggal terlebih dahulu.');
            return;
        }

        $dates = $this->buildDateRange();
        if (empty($dates)) {
            $this->dispatch('toast', type: 'warning', message: 'Range tanggal tidak valid.');
            return;
        }

        if (count($dates) > 31) {
            $this->dispatch('toast', type: 'warning', message: 'Range tanggal maksimal 31 hari.');
            return;
        }

        $conflicts = [];
        foreach ($dates as $date) {
            $count = BpjsAntreanBooking::where('tanggal', $date)->count();
            if ($count > 0) {
                $conflicts[$date] = $count;
            }
        }

        if (!empty($conflicts)) {
            $this->modalConflicts = $conflicts;
            $this->showPullModal = true;
            return;
        }

        $this->dispatchPullJob($dates);
    }

    public function confirmPull(): void
    {
        $this->showPullModal = false;
        $this->modalConflicts = [];
        $this->dispatchPullJob($this->buildDateRange());
    }

    public function cancelPull(): void
    {
        $this->showPullModal = false;
        $this->modalConflicts = [];
    }

    private function dispatchPullJob(array $dates): void
    {
        PullAntreanJob::dispatch($dates, $this->pullTimeout);

        $label = count($dates) > 1 ? Carbon::parse($dates[0])->format('d/m/Y') . ' – ' . Carbon::parse(end($dates))->format('d/m/Y') : Carbon::parse($dates[0])->format('d/m/Y');

        $this->dispatch('toast', type: 'info', message: count($dates) . ' tanggal (' . $label . ') diantrean untuk diproses. Refresh halaman setelah selesai.');
    }

    private function buildDateRange(): array
    {
        try {
            $start = Carbon::parse($this->dateFrom);
            $end = Carbon::parse($this->dateTo);
            if ($start->gt($end)) {
                return [];
            }

            $dates = [];
            $cur = $start->copy();
            while ($cur->lte($end)) {
                $dates[] = $cur->format('Y-m-d');
                $cur->addDay();
            }
            return $dates;
        } catch (\Exception) {
            return [];
        }
    }

    public function with(): array
    {
        // Daftar tanggal tersedia (untuk seluruh tab)
        $availableDates = BpjsAntreanBooking::selectRaw('tanggal, COUNT(*) as cnt')->groupBy('tanggal')->orderByDesc('tanggal')->get();

        // ===== Tab: Antrean Per Tanggal =====
        $hasData = false;
        $filteredRecords = collect();
        $totalFiltered = 0;
        $lastPage = 1;
        $stats = ['total' => 0, 'selesai' => 0, 'batal' => 0, 'belum' => 0, 'sedang' => 0, 'sesuai' => 0, 'tidak' => 0, 'ranap' => 0];
        $sumberOptions = collect();
        $storedCount = 0;
        $lastPulledAt = null;

        if ($this->tab === 'antrean') {
            $bookings = BpjsAntreanBooking::where('tanggal', $this->filterDate)->get();
            $storedCount = $bookings->count();
            $lastPulledAt = $bookings->max('updated_at');
            $hasData = $bookings->isNotEmpty();

            if ($hasData) {
                $localData = collect();
                try {
                    $localData = RegPeriksa::bpjsOnly()
                        ->whereDate('tgl_registrasi', $this->filterDate)
                        ->with(['bridgingSep:no_rawat,no_sep,no_rujukan,noskdp,jnspelayanan'])
                        ->select(['no_rawat', 'status_lanjut'])
                        ->get()
                        ->keyBy('no_rawat');
                } catch (\Exception) {
                }

                $allRecords = $bookings->map(function ($b) use ($localData): array {
                    $local = $localData->get($b->kode_booking);
                    $sep = $local?->bridgingSep;
                    $noSep = $sep?->no_sep ?? '';
                    $noRujukan = $sep?->no_rujukan ?? '';
                    $noSkdp = $sep?->noskdp ?? '';
                    $jnsPel = $sep ? ($sep->jnspelayanan == '1' ? 'Ranap' : 'Ralan') : '';
                    $jnsK = $b->jenis_kunjungan;
                    $noRef = $b->no_referensi ?? '';
                    $isPeserta = $b->is_peserta;
                    $status = $b->status ?? '';

                    if ($jnsPel === 'Ranap') {
                        $kes = 'ranap';
                    } elseif (strtolower($status) === 'batal') {
                        $kes = 'na';
                    } elseif ($isPeserta) {
                        $refL = $jnsK === '3' ? $noSkdp : $noRujukan;
                        $kes = strcasecmp($noRef, $refL) === 0 ? 'sesuai' : 'tidak';
                    } else {
                        $kes = $noSep === '' ? 'sesuai' : 'tidak';
                    }

                    return [
                        'kode_booking' => $b->kode_booking,
                        'kd_poli' => $b->kd_poli ?? '',
                        'kd_dokter' => $b->kd_dokter ?? '',
                        'jam_praktek' => $b->jam_praktek ?? '',
                        'nik' => $b->nik ?? '',
                        'no_kartu' => $b->no_kartu ?? '',
                        'no_rm' => $b->no_rm ?? '',
                        'jenis_kunjungan' => $this->labelJenisKunjungan($jnsK),
                        'no_referensi' => $noRef,
                        'sumber_data' => $b->sumber_data ?? '',
                        'is_peserta' => $isPeserta,
                        'no_antrean' => $b->no_antrean ?? '',
                        'estimasi' => $this->formatTimestamp($b->estimasi_timestamp ?? 0),
                        'status' => $status,
                        'no_rawat' => $local?->no_rawat ?? '',
                        'no_sep' => $noSep,
                        'no_ref_lokal' => $noSkdp !== '' ? strtoupper($noSkdp) : strtoupper($noRujukan),
                        'kesesuaian' => $kes,
                    ];
                });

                $filtered = $allRecords;
                if ($this->filterStatus) {
                    $filtered = $filtered->filter(fn($r) => strtolower($r['status']) === strtolower($this->filterStatus));
                }
                if ($this->filterSumber) {
                    $filtered = $filtered->filter(fn($r) => stripos($r['sumber_data'], $this->filterSumber) !== false);
                }
                $allFiltered = $filtered->values();
                $totalFiltered = $allFiltered->count();
                $lastPage = max(1, (int) ceil($totalFiltered / $this->perPage));
                $filteredRecords = $allFiltered->forPage($this->antreanPage, $this->perPage);

                $stats = [
                    'total' => $allRecords->count(),
                    'selesai' => $allRecords->filter(fn($r) => stripos($r['status'], 'selesai') !== false)->count(),
                    'batal' => $allRecords->filter(fn($r) => strtolower($r['status']) === 'batal')->count(),
                    'belum' => $allRecords->filter(fn($r) => stripos($r['status'], 'belum') !== false)->count(),
                    'sedang' => $allRecords->filter(fn($r) => stripos($r['status'], 'sedang') !== false)->count(),
                    'sesuai' => $allRecords->filter(fn($r) => $r['kesesuaian'] === 'sesuai')->count(),
                    'tidak' => $allRecords->filter(fn($r) => $r['kesesuaian'] === 'tidak')->count(),
                    'ranap' => $allRecords->filter(fn($r) => $r['kesesuaian'] === 'ranap')->count(),
                ];
                $sumberOptions = $allRecords->pluck('sumber_data')->unique()->filter()->sort()->values();
            }
        }

        // ===== Tab: Rekap Antrean =====
        $rekapData = [];
        if ($this->tab === 'rekap') {
            $rekapData = $this->buildRekapData();
        }

        // Log riwayat penarikan
        $riwayatLogs = collect();
        $riwayatTotal = 0;
        $riwayatLastPage = 1;
        if ($this->tab === 'riwayat') {
            $riwayatTotal = BpjsLog::forService('antrean')->count();
            $riwayatLastPage = max(1, (int) ceil($riwayatTotal / $this->riwayatPerPage));
            $riwayatLogs = BpjsLog::forService('antrean')
                ->orderByDesc('created_at')
                ->skip(($this->riwayatPage - 1) * $this->riwayatPerPage)
                ->take($this->riwayatPerPage)
                ->get(['id', 'status', 'method', 'endpoint', 'response_status', 'error_message', 'created_at']);
        }

        // Log detail untuk modal
        $logDetail = $this->showLogDetailModal && $this->logDetailId ? BpjsLog::find($this->logDetailId) : null;

        $hasRunningJob = DB::table('jobs')->where('payload', 'like', '%PullAntreanJob%')->exists();

        return [
            'availableDates' => $availableDates,
            'hasData' => $hasData,
            'storedCount' => $storedCount,
            'lastPulledAt' => $lastPulledAt,
            'filteredRecords' => $filteredRecords,
            'totalFiltered' => $totalFiltered,
            'lastPage' => $lastPage,
            'stats' => $stats,
            'sumberOptions' => $sumberOptions,
            'rekapData' => $rekapData,
            'modalDetailRecords' => $this->showDetailModal ? $this->buildModalDetailRecords() : collect(),
            'riwayatLogs' => $riwayatLogs,
            'riwayatTotal' => $riwayatTotal,
            'riwayatLastPage' => $riwayatLastPage,
            'logDetail' => $logDetail,
            'hasRunningJob' => $hasRunningJob,
        ];
    }

    private function buildRekapData(): array
    {
        $q = BpjsAntreanBooking::query();
        $this->applyPeriodFilter($q);
        $bookings = $q->get();

        if ($bookings->isEmpty()) {
            return ['empty' => true, 'period_label' => $this->buildPeriodLabel()];
        }

        $totalRegs = 0;
        try {
            $rq = BpjsAntreanRegistration::query();
            $this->applyPeriodFilter($rq);
            $totalRegs = $rq->count();
        } catch (\Exception) {
        }

        // Tag setiap booking dengan kategori sumber
        $sumberOrder = ['Mobile JKN', 'Bridging Antrean (Loket)', 'Bridging Antrean (APM)', 'Lainnya'];
        $sumberColors = [
            'Mobile JKN' => 'rgba(139,92,246,.85)',
            'Bridging Antrean (Loket)' => 'rgba(59,130,246,.85)',
            'Bridging Antrean (APM)' => 'rgba(20,184,166,.85)',
            'Lainnya' => 'rgba(156,163,175,.85)',
        ];
        $bookings->each(function ($b) {
            $b->sumber_cat = $this->categorizeSumber($b->sumber_data, $b->kode_booking);
        });

        // By Jenis Kunjungan (stacked by sumber)
        $byJenis = $bookings->groupBy('jenis_kunjungan')->sortByDesc(fn($g) => $g->count());
        $jenisSumberDatasets = collect($sumberOrder)
            ->map(
                fn($s) => [
                    'label' => $s,
                    'data' => $byJenis->map(fn($g) => $g->where('sumber_cat', $s)->count())->values()->toArray(),
                    'backgroundColor' => $sumberColors[$s],
                ],
            )
            ->filter(fn($ds) => array_sum($ds['data']) > 0)
            ->values()
            ->toArray();

        // By Poli (top 15, stacked by sumber)
        $byPoli = $bookings->groupBy('kd_poli')->sortByDesc(fn($g) => $g->count())->take(15);
        $poliSumberDatasets = collect($sumberOrder)
            ->map(
                fn($s) => [
                    'label' => $s,
                    'data' => $byPoli->map(fn($g) => $g->where('sumber_cat', $s)->count())->values()->toArray(),
                    'backgroundColor' => $sumberColors[$s],
                ],
            )
            ->filter(fn($ds) => array_sum($ds['data']) > 0)
            ->values()
            ->toArray();

        // By Sumber (peserta vs non-peserta)
        $sumberGroups = array_fill_keys($sumberOrder, ['p' => 0, 'n' => 0]);
        foreach ($bookings as $b) {
            $b->is_peserta ? $sumberGroups[$b->sumber_cat]['p']++ : $sumberGroups[$b->sumber_cat]['n']++;
        }
        $sumberGroups = array_filter($sumberGroups, fn($g) => $g['p'] + $g['n'] > 0);

        // Distribusi per hari (monthly)
        $byDayChart = null;
        if ($this->rekapPeriod === 'monthly' && $this->rekapFrom) {
            $daysInMonth = \Carbon\Carbon::parse($this->rekapFrom . '-01')->daysInMonth;
            $dayLabels = array_map(fn($d) => str_pad($d, 2, '0', STR_PAD_LEFT), range(1, $daysInMonth));
            $byDay = $bookings->groupBy(fn($b) => \Carbon\Carbon::parse($b->tanggal)->format('d'));
            $daySumberDatasets = collect($sumberOrder)
                ->map(
                    fn($s) => [
                        'label' => $s,
                        'data' => collect($dayLabels)->map(fn($d) => $byDay->get($d, collect())->where('sumber_cat', $s)->count())->toArray(),
                        'backgroundColor' => $sumberColors[$s],
                    ],
                )
                ->filter(fn($ds) => array_sum($ds['data']) > 0)
                ->values()
                ->toArray();
            $dayTotals = collect(range(0, count($dayLabels) - 1))
                ->map(fn($i) => collect($daySumberDatasets)->sum(fn($ds) => $ds['data'][$i] ?? 0))
                ->toArray();
            $byDayChart = [
                'labels' => $dayLabels,
                'datasets' => [
                    ...$daySumberDatasets,
                    [
                        'type' => 'line',
                        'label' => 'Total',
                        'data' => $dayTotals,
                        'borderColor' => 'rgba(239,68,68,0.85)',
                        'backgroundColor' => 'rgba(239,68,68,0.08)',
                        'pointBackgroundColor' => 'rgba(239,68,68,0.85)',
                        'fill' => false,
                        'tension' => 0.4,
                        'borderWidth' => 2,
                        'pointRadius' => 3,
                        'order' => 0,
                    ],
                ],
            ];
        }

        // Distribusi per bulan (yearly)
        $byMonthChart = null;
        if ($this->rekapPeriod === 'yearly' && $this->rekapFrom) {
            $monthKeys = ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'];
            $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'];
            $byMonth = $bookings->groupBy(fn($b) => \Carbon\Carbon::parse($b->tanggal)->format('m'));
            $monthSumberDatasets = collect($sumberOrder)
                ->map(
                    fn($s) => [
                        'label' => $s,
                        'data' => collect($monthKeys)->map(fn($m) => $byMonth->get($m, collect())->where('sumber_cat', $s)->count())->toArray(),
                        'backgroundColor' => $sumberColors[$s],
                    ],
                )
                ->filter(fn($ds) => array_sum($ds['data']) > 0)
                ->values()
                ->toArray();
            $monthTotals = collect(range(0, 11))->map(fn($i) => collect($monthSumberDatasets)->sum(fn($ds) => $ds['data'][$i] ?? 0))->toArray();
            $byMonthChart = [
                'labels' => $monthNames,
                'datasets' => [
                    ...$monthSumberDatasets,
                    [
                        'type' => 'line',
                        'label' => 'Total',
                        'data' => $monthTotals,
                        'borderColor' => 'rgba(239,68,68,0.85)',
                        'backgroundColor' => 'rgba(239,68,68,0.08)',
                        'pointBackgroundColor' => 'rgba(239,68,68,0.85)',
                        'fill' => false,
                        'tension' => 0.4,
                        'borderWidth' => 2,
                        'pointRadius' => 4,
                        'order' => 0,
                    ],
                ],
            ];
        }

        return [
            'empty' => false,
            'period_label' => $this->buildPeriodLabel(),
            'total_antrean' => $bookings->count(),
            'total_regs' => $totalRegs,
            'belum_antrean' => max(0, $totalRegs - $bookings->count()),
            'by_jenis_chart' => [
                'labels' => $byJenis->keys()->map(fn($k) => $this->labelJenisKunjungan($k))->values()->toArray(),
                'datasets' => $jenisSumberDatasets,
            ],
            'by_poli_chart' => [
                'labels' => $byPoli->keys()->toArray(),
                'datasets' => $poliSumberDatasets,
            ],
            'by_sumber_chart' => [
                'labels' => array_keys($sumberGroups),
                'datasets' => [['label' => 'Peserta', 'data' => array_values(array_map(fn($g) => $g['p'], $sumberGroups)), 'backgroundColor' => 'rgba(16,185,129,.85)'], ['label' => 'Non-Peserta', 'data' => array_values(array_map(fn($g) => $g['n'], $sumberGroups)), 'backgroundColor' => 'rgba(245,158,11,.85)']],
            ],
            'by_sumber_table' => array_map(fn($label, $g) => ['label' => $label, 'peserta' => $g['p'], 'non_peserta' => $g['n'], 'total' => $g['p'] + $g['n']], array_keys($sumberGroups), $sumberGroups),
            ...$byDayChart ? ['by_day_chart' => $byDayChart] : [],
            ...$byMonthChart ? ['by_month_chart' => $byMonthChart] : [],
        ];
    }

    /**
     * Bangun daftar pasien untuk modal detail kartu rekap.
     * - regs    : semua registrasi BPJS dari SIMRS pada periode
     * - antrean : semua booking yang berhasil terbit pada periode
     * - belum   : registrasi pada slot (tanggal+poli+dokter) yang sama sekali tidak punya booking
     */
    private function buildModalDetailRecords(): \Illuminate\Support\Collection
    {
        $limit = 500;

        if ($this->modalDetailType === 'regs') {
            $q = BpjsAntreanRegistration::query()->orderBy('tanggal')->orderBy('no_rawat');
            $this->applyPeriodFilter($q);
            return $q->limit($limit)->get(['no_rawat', 'tanggal', 'kd_poli', 'kd_dokter', 'status_lanjut']);
        }

        if ($this->modalDetailType === 'antrean') {
            $q = BpjsAntreanBooking::query()->orderBy('tanggal')->orderBy('no_antrean');
            $this->applyPeriodFilter($q);
            return $q->limit($limit)->get(['kode_booking', 'tanggal', 'kd_poli', 'kd_dokter', 'nik', 'no_kartu', 'no_rm', 'no_antrean', 'status', 'sumber_data', 'is_peserta']);
        }

        if ($this->modalDetailType === 'belum') {
            // Registrasi BPJS pada periode, kecuali yang Batal
            $rq = BpjsAntreanRegistration::query()->where('status_lanjut', '!=', 'Batal')->orderBy('tanggal')->orderBy('no_rawat');
            $this->applyPeriodFilter($rq);
            $regs = $rq->limit($limit)->get(['no_rawat', 'tanggal', 'kd_poli', 'nm_poli', 'kd_dokter', 'nm_dokter', 'status_lanjut']);

            if ($regs->isEmpty()) {
                return collect();
            }

            // Cek no_rawat yang sudah terbit Antrean via SIMRS referensi_mobilejkn_bpjs
            $bookedNoRawat = BpjsAntreanRegistration::whereHas('antrean', fn($q) => $q->where('status', '!=', 'Batal'))->pluck('no_rawat')->flip();

            return $regs->filter(fn($r) => !$bookedNoRawat->has($r->no_rawat))->values();
        }

        return collect();
    }

    private function applyPeriodFilter($query, string $col = 'tanggal'): void
    {
        if ($this->rekapPeriod === 'daily') {
            if ($this->rekapFrom) {
                $query->where($col, '>=', $this->rekapFrom);
            }
            if ($this->rekapTo) {
                $query->where($col, '<=', $this->rekapTo);
            }
        } else {
            if ($this->rekapFrom) {
                $query->where($col, 'like', $this->rekapFrom . '-%');
            }
        }
    }

    private function buildPeriodLabel(): string
    {
        if ($this->rekapPeriod === 'daily') {
            if ($this->rekapFrom && $this->rekapTo && $this->rekapFrom !== $this->rekapTo) {
                return Carbon::parse($this->rekapFrom)->translatedFormat('d M Y') . ' – ' . Carbon::parse($this->rekapTo)->translatedFormat('d M Y');
            }
            return $this->rekapFrom ? Carbon::parse($this->rekapFrom)->translatedFormat('d F Y') : 'Semua data';
        }
        if ($this->rekapPeriod === 'monthly' && $this->rekapFrom) {
            return Carbon::parse($this->rekapFrom . '-01')->translatedFormat('F Y');
        }
        return $this->rekapFrom ?: 'Semua data';
    }

    private function categorizeSumber(?string $sumber, string $kodeBooking): string
    {
        if (!$sumber) {
            return 'Lainnya';
        }
        $sl = strtolower($sumber);
        if (str_contains($sl, 'mobile')) {
            return 'Mobile JKN';
        } else {
            // Fallback ke suffix kodeBooking
            if (str_starts_with($kodeBooking, 'A')) {
                return 'Bridging Antrean (Loket)';
            }
            if (str_starts_with($kodeBooking, 'D')) {
                return 'Bridging Antrean (APM)';
            }
        }

        return 'Lainnya';
    }

    private function labelJenisKunjungan(string $kode): string
    {
        return match ($kode) {
            '1' => '1. Rujukan FKTP',
            '2' => '2. Rujukan Internal',
            '3' => '3. Kontrol',
            '4' => '4. Rujukan RS',
            default => $kode ?: '-',
        };
    }

    private function formatTimestamp(string|int $ts): string
    {
        if (!$ts || $ts === '0') {
            return '-';
        }
        $ts = (int) $ts;
        if ($ts > 9_999_999_999) {
            $ts = intdiv($ts, 1000);
        }
        try {
            return Carbon::createFromTimestamp($ts)->format('d/m/Y H:i');
        } catch (\Exception) {
            return (string) $ts;
        }
    }
}; ?>

<div>
    {{-- Poll saat ada job berjalan --}}
    @if ($hasRunningJob)
        <div wire:poll.5000ms="$refresh" class="hidden"></div>
    @endif

    <x-ui.page-header title="BPJS — Antrean Online"
        subtitle="Data antrean pendaftaran dari Mobile JKN & Bridging Antrean">
        <x-slot:actions>
            <div class="flex items-center gap-2">
                <flux:input wire:model.live="dateFrom" type="date" class="w-38" />
                <span class="text-zinc-400 text-sm">–</span>
                <flux:input wire:model.live="dateTo" type="date" class="w-38" />
            </div>
            <div class="flex items-center gap-1.5">
                <label class="text-xs text-zinc-500 dark:text-primary-dark-400 whitespace-nowrap">Timeout</label>
                <flux:select wire:model.live="pullTimeout" class="w-20">
                    <flux:select.option value="10">10 dtk</flux:select.option>
                    <flux:select.option value="15">15 dtk</flux:select.option>
                    <flux:select.option value="30">30 dtk</flux:select.option>
                    <flux:select.option value="60">60 dtk</flux:select.option>
                </flux:select>
            </div>
            <x-atoms.button wire:click="pullData" icon="arrow-down-tray" wire:loading.attr="disabled"
                wire:target="pullData,confirmPull">
                <span wire:loading.remove wire:target="pullData,confirmPull">Tarik Antrean</span>
                <span wire:loading wire:target="pullData,confirmPull">Menyiapkan...</span>
            </x-atoms.button>
            <x-atoms.button wire:click="$set('showDatesModal', true)" variant="ghost" icon="calendar-days">
                List Tersedia
            </x-atoms.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Running Job Banner --}}
    @if ($hasRunningJob)
        <div
            class="flex items-center gap-3 p-3.5 mb-5 rounded-xl bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800/50">
            <svg class="animate-spin size-4 text-blue-500 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none"
                viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                </circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <p class="text-sm text-blue-700 dark:text-blue-300">
                <strong>Job sedang berjalan</strong> — antrean sedang ditarik di background. Halaman akan diperbarui
                otomatis setiap 5 detik.
            </p>
        </div>
    @endif

    {{-- Tab Navigation --}}
    <x-molecules.tabs class="mb-6">
        @foreach ([
        'rekap' => ['icon' => 'chart-bar', 'label' => 'Rekap Antrean'],
        'antrean' => ['icon' => 'queue-list', 'label' => 'Antrean Per Tanggal'],
        'riwayat' => ['icon' => 'clock', 'label' => 'Riwayat Penarikan'],
    ] as $key => $item)
            <x-atoms.tab-item icon="{{ $item['icon'] }}" wire:click="switchTab('{{ $key }}')"
                :active="$tab === $key">
                {{ $item['label'] }}
            </x-atoms.tab-item>
        @endforeach
    </x-molecules.tabs>

    {{-- Loading for tab/date switch --}}
    <div wire:loading.flex wire:target="switchTab,viewDate"
        class="flex flex-col items-center justify-center py-16 gap-3 text-zinc-400">
        <svg class="animate-spin size-6 text-primary-500" xmlns="http://www.w3.org/2000/svg" fill="none"
            viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
            </circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        <span class="text-sm">Memuat...</span>
    </div>

    <div wire:loading.remove wire:target="switchTab,viewDate">

        {{-- ===== TAB: REKAP ANTREAN ===== --}}
        @if ($tab === 'rekap')

            {{-- Period Filter --}}
            <div class="flex flex-col gap-3 mb-6 sm:flex-row sm:items-center">
                <div
                    class="flex bg-white dark:bg-primary-dark-800 rounded-xl border border-zinc-200 dark:border-primary-dark-700 p-0.5 gap-0.5 shadow-sm text-sm">
                    @foreach (['daily' => 'Per Tanggal', 'monthly' => 'Bulanan', 'yearly' => 'Tahunan'] as $val => $lbl)
                        <x-atoms.button :variant="$rekapPeriod == $val ? 'primary' : 'ghost'" wire:click="$set('rekapPeriod', '{{ $val }}')">
                            {{ $lbl }}
                        </x-atoms.button>
                    @endforeach
                </div>
                <div class="flex items-center gap-2">
                    @if ($rekapPeriod === 'daily')
                        <flux:input wire:model.live="rekapFrom" type="date" class="w-40" />
                        <span class="text-zinc-400">–</span>
                        <flux:input wire:model.live="rekapTo" type="date" class="w-40" />
                    @elseif ($rekapPeriod === 'monthly')
                        <flux:input wire:model.live="rekapFrom" type="month" class="w-44" />
                    @else
                        <flux:input wire:model.live="rekapFrom" type="number" min="2020" max="2099"
                            class="w-28" placeholder="Tahun" />
                    @endif
                </div>
            </div>

            @if (empty($rekapData) || ($rekapData['empty'] ?? true))
                <x-ui.empty-state icon="chart-bar" title="Belum ada data"
                    description="Belum ada data untuk periode ini. Tarik antrean terlebih dahulu." />
            @else
                {{-- Period Label --}}
                <div class="flex items-center gap-2 mb-5">
                    <flux:icon name="calendar" class="size-4 text-zinc-400" />
                    <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Periode:
                        <strong
                            class="text-zinc-700 dark:text-primary-dark-200">{{ $rekapData['period_label'] }}</strong>
                    </span>
                </div>

                {{-- Summary Cards --}}
                @php
                    $summaryCards = [
                        [
                            'label' => 'Pasien BPJS',
                            'sub' => 'dari SIMRS',
                            'value' => $rekapData['total_regs'],
                            'icon' => 'users',
                            'color' => 'zinc',
                            'modalType' => 'regs',
                        ],
                        [
                            'label' => 'Terbit Antrean',
                            'sub' => null,
                            'value' => $rekapData['total_antrean'],
                            'icon' => 'check-circle',
                            'color' => 'emerald',
                            'modalType' => 'antrean',
                        ],
                        [
                            'label' => 'Belum Antrean',
                            'sub' => null,
                            'value' => $rekapData['belum_antrean'],
                            'icon' => 'clock',
                            'color' => 'amber',
                            'modalType' => 'belum',
                        ],
                    ];
                    $cardColors = [
                        'zinc' => [
                            'hover' => 'hover:border-zinc-300 dark:hover:border-primary-dark-600',
                            'iconWrap' => 'bg-zinc-100 text-zinc-600 dark:bg-primary-dark-700 dark:text-zinc-300',
                            'label' => 'text-zinc-500 dark:text-primary-dark-400',
                            'value' => 'text-zinc-800 dark:text-zinc-100',
                            'bar' => 'bg-zinc-300 dark:bg-primary-dark-500',
                            'barBg' => 'bg-zinc-100 dark:bg-primary-dark-700/50',
                        ],
                        'emerald' => [
                            'hover' => 'hover:border-emerald-200 dark:hover:border-emerald-800/80',
                            'iconWrap' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400',
                            'label' => 'text-emerald-600 dark:text-emerald-400',
                            'value' => 'text-emerald-700 dark:text-emerald-400',
                            'bar' => 'bg-emerald-400 dark:bg-emerald-500',
                            'barBg' => 'bg-emerald-50 dark:bg-emerald-900/40',
                        ],
                        'amber' => [
                            'hover' => 'hover:border-amber-200 dark:hover:border-amber-800/80',
                            'iconWrap' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400',
                            'label' => 'text-amber-600 dark:text-amber-400',
                            'value' => 'text-amber-700 dark:text-amber-400',
                            'bar' => 'bg-amber-400 dark:bg-amber-500',
                            'barBg' => 'bg-amber-50 dark:bg-amber-900/40',
                        ],
                    ];
                @endphp
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-7">
                    @foreach ($summaryCards as $card)
                        @php
                            $cc = $cardColors[$card['color']];
                            $pct =
                                $rekapData['total_regs'] > 0 && $card['color'] !== 'zinc'
                                    ? min(100, round(($card['value'] / $rekapData['total_regs']) * 100))
                                    : null;
                        @endphp
                        <button type="button" wire:click="openDetailModal('{{ $card['modalType'] }}')"
                            class="group w-full text-left flex flex-col gap-3 rounded-2xl border border-zinc-100 dark:border-primary-dark-700/80 bg-white dark:bg-primary-dark-800 p-5 shadow-[0_2px_8px_-2px_rgba(0,0,0,0.05)] transition-all duration-300 hover:-translate-y-1 hover:shadow-md {{ $cc['hover'] }} focus:outline-none focus:ring-2 focus:ring-primary-500/50">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <span
                                        class="text-xs font-semibold tracking-wide uppercase {{ $cc['label'] }}">{{ $card['label'] }}</span>
                                    <p
                                        class="mt-2 text-2xl font-black tracking-tight leading-none {{ $cc['value'] }}">
                                        {{ number_format($card['value']) }}</p>
                                    <p class="mt-1.5 text-xs font-medium text-zinc-400 dark:text-primary-dark-500">
                                        @if ($pct !== null)
                                            {{ number_format($pct, 1) }}% dari pasien BPJS
                                        @else
                                            {{ $card['sub'] }}
                                        @endif
                                    </p>
                                </div>
                                <div
                                    class="{{ $cc['iconWrap'] }} flex h-10 w-10 shrink-0 items-center justify-center rounded-xl transition-transform duration-300 group-hover:scale-110">
                                    <flux:icon name="{{ $card['icon'] }}" variant="outline" class="size-5" />
                                </div>
                            </div>
                            @if ($pct !== null)
                                <div class="h-1.5 {{ $cc['barBg'] }} rounded-full overflow-hidden">
                                    <div class="h-full {{ $cc['bar'] }} rounded-full transition-all duration-700"
                                        style="width: {{ $pct }}%"></div>
                                </div>
                            @endif
                        </button>
                    @endforeach
                </div>

                {{-- Charts --}}
                <div wire:key="charts-{{ $rekapPeriod }}-{{ $rekapFrom }}-{{ $rekapTo }}"
                    class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-7">
                    @php
                        $barCharts = [
                            [
                                'id' => 'chart-jenis',
                                'title' => 'Jenis Kunjungan',
                                'badge' => null,
                                'icon' => 'queue-list',
                                'iconBg' => 'bg-blue-50 dark:bg-blue-900/30',
                                'iconText' => 'text-blue-500',
                                'horizontal' => 'false',
                                'stacked' => 'true',
                                'data' => $rekapData['by_jenis_chart'],
                            ],
                            [
                                'id' => 'chart-poli',
                                'title' => 'Poliklinik',
                                'badge' => 'Top 15',
                                'icon' => 'building-office-2',
                                'iconBg' => 'bg-violet-50 dark:bg-violet-900/30',
                                'iconText' => 'text-violet-500',
                                'horizontal' => 'true',
                                'stacked' => 'true',
                                'data' => $rekapData['by_poli_chart'],
                            ],
                        ];
                    @endphp
                    @foreach ($barCharts as $chart)
                        <div
                            class="overflow-hidden bg-white border rounded-2xl dark:bg-primary-dark-800 border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
                            <div
                                class="flex items-center gap-2.5 px-5 py-4 border-b border-zinc-100 dark:border-primary-dark-700/60">
                                <div class="p-1.5 rounded-lg {{ $chart['iconBg'] }}">
                                    <flux:icon name="{{ $chart['icon'] }}"
                                        class="size-4 {{ $chart['iconText'] }}" />
                                </div>
                                <h3 class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-200">
                                    {{ $chart['title'] }}
                                    @if ($chart['badge'])
                                        <span
                                            class="ml-1 text-xs font-normal text-zinc-400">({{ $chart['badge'] }})</span>
                                    @endif
                                </h3>
                            </div>
                            <div class="p-5" x-data="antreanChartEl()" data-canvas="{{ $chart['id'] }}"
                                data-horizontal="{{ $chart['horizontal'] }}"
                                data-stacked="{{ $chart['stacked'] ?? 'false' }}"
                                data-chart='@json($chart['data'])'>
                                <canvas id="{{ $chart['id'] }}" height="220"></canvas>
                            </div>
                        </div>
                    @endforeach

                    {{-- Chart: Distribusi per Hari (monthly) --}}
                    @if ($rekapPeriod === 'monthly' && !empty($rekapData['by_day_chart']['datasets']))
                        <div
                            class="overflow-hidden bg-white border rounded-2xl dark:bg-primary-dark-800 border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm lg:col-span-2">
                            <div
                                class="flex items-center gap-2.5 px-5 py-4 border-b border-zinc-100 dark:border-primary-dark-700/60">
                                <div class="p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-900/30">
                                    <flux:icon name="calendar-days" class="size-4 text-emerald-500" />
                                </div>
                                <h3 class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-200">
                                    Distribusi per Hari
                                </h3>
                            </div>
                            <div class="p-5" x-data="antreanChartEl()" data-canvas="chart-by-day"
                                data-horizontal="false" data-stacked="true"
                                data-chart='@json($rekapData['by_day_chart'])'>
                                <canvas id="chart-by-day" height="80"></canvas>
                            </div>
                        </div>
                    @endif

                    {{-- Chart: Distribusi per Bulan (yearly) --}}
                    @if ($rekapPeriod === 'yearly' && !empty($rekapData['by_month_chart']['datasets']))
                        <div
                            class="overflow-hidden bg-white border rounded-2xl dark:bg-primary-dark-800 border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm lg:col-span-2">
                            <div
                                class="flex items-center gap-2.5 px-5 py-4 border-b border-zinc-100 dark:border-primary-dark-700/60">
                                <div class="p-1.5 rounded-lg bg-amber-50 dark:bg-amber-900/30">
                                    <flux:icon name="calendar" class="size-4 text-amber-500" />
                                </div>
                                <h3 class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-200">
                                    Distribusi per Bulan
                                </h3>
                            </div>
                            <div class="p-5" x-data="antreanChartEl()" data-canvas="chart-by-month"
                                data-horizontal="false" data-stacked="true"
                                data-chart='@json($rekapData['by_month_chart'])'>
                                <canvas id="chart-by-month" height="140"></canvas>
                            </div>
                        </div>
                    @endif

                    {{-- Chart: Sumber Data --}}
                    <div
                        class="overflow-hidden bg-white border rounded-2xl dark:bg-primary-dark-800 border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm lg:col-span-2">
                        <div
                            class="flex items-center gap-2.5 px-5 py-4 border-b border-zinc-100 dark:border-primary-dark-700/60">
                            <div class="p-1.5 rounded-lg bg-teal-50 dark:bg-teal-900/30">
                                <flux:icon name="signal" class="size-4 text-teal-500" />
                            </div>
                            <h3 class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-200">Sumber Data
                                Antrean</h3>
                        </div>
                        <div
                            class="grid grid-cols-1 lg:grid-cols-2 divide-y lg:divide-y-0 lg:divide-x divide-zinc-100 dark:divide-primary-dark-700/60">
                            <div class="p-5" x-data="antreanChartEl()" data-canvas="chart-sumber"
                                data-horizontal="false" data-chart='@json($rekapData['by_sumber_chart'])'>
                                <canvas id="chart-sumber" height="200"></canvas>
                            </div>
                            <div class="p-5">
                                <x-organisms.table>
                                    <x-slot:headings>
                                        @foreach (['Sumber' => 'text-left', 'Peserta' => 'text-right', 'Non-Peserta' => 'text-right', 'Total' => 'text-right'] as $th => $align)
                                            <x-atoms.table-heading
                                                class="{{ $align }}">{{ $th }}</x-atoms.table-heading>
                                        @endforeach
                                    </x-slot:headings>
                                    @foreach ($rekapData['by_sumber_table'] as $row)
                                        @php
                                            $bc = match (true) {
                                                str_contains($row['label'], 'Mobile') => 'purple',
                                                str_contains($row['label'], '(Loket)') => 'blue',
                                                str_contains($row['label'], '(APM)') => 'teal',
                                                default => 'zinc',
                                            };
                                        @endphp
                                        <x-molecules.table-row>
                                            <x-atoms.table-cell>
                                                <flux:badge :color="$bc" size="sm">{{ $row['label'] }}
                                                </flux:badge>
                                            </x-atoms.table-cell>
                                            <x-atoms.table-cell align="right"
                                                class="font-mono font-semibold text-zinc-700 dark:text-primary-dark-200">{{ number_format($row['peserta']) }}</x-atoms.table-cell>
                                            <x-atoms.table-cell align="right"
                                                class="font-mono font-semibold text-zinc-500 dark:text-primary-dark-400">{{ number_format($row['non_peserta']) }}</x-atoms.table-cell>
                                            <x-atoms.table-cell align="right"
                                                class="font-mono font-bold text-zinc-800 dark:text-primary-dark-100">{{ number_format($row['total']) }}</x-atoms.table-cell>
                                        </x-molecules.table-row>
                                    @endforeach
                                </x-organisms.table>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @endif

        {{-- ===== TAB: ANTREAN PER TANGGAL ===== --}}
        @if ($tab === 'antrean')
            {{-- Date filter --}}
            <div class="flex items-center gap-3 mb-5">
                <flux:input wire:model.live="filterDate" type="date" class="w-44!" />
                @if ($lastPulledAt)
                    <span class="text-xs text-zinc-400 dark:text-primary-dark-500">
                        Ditarik {{ Carbon::parse($lastPulledAt)->diffForHumans() }}
                    </span>
                @endif
            </div>

            @if (!$hasData)
                <x-ui.empty-state icon="calendar-days" title="Belum ada data"
                    description="Belum ada data untuk tanggal ini. Gunakan tombol Tarik Antrean." />
            @else
                {{-- Stats Cards --}}
                <div class="space-y-3 mb-5">
                    {{-- Status Pelayanan --}}
                    <div>
                        <p
                            class="text-[11px] font-semibold uppercase tracking-widest text-zinc-400 dark:text-primary-dark-500 mb-2 px-0.5">
                            Status Pelayanan</p>
                        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                            <x-organisms.stat-card class="col-span-2 sm:col-span-1" title="Total Booking"
                                :value="number_format($stats['total'])" icon="list-bullet" color="zinc" />
                            <x-organisms.stat-card title="Selesai" :value="number_format($stats['selesai'])" icon="check-circle"
                                color="emerald" />
                            <x-organisms.stat-card title="Batal" :value="number_format($stats['batal'])" icon="x-circle" color="red" />
                            <x-organisms.stat-card title="Belum Dilayani" :value="number_format($stats['belum'])" icon="clock"
                                color="amber" />
                            <x-organisms.stat-card title="Sedang Dilayani" :value="number_format($stats['sedang'])" icon="arrow-path"
                                color="blue" />
                        </div>
                    </div>
                    {{-- Kesesuaian Referensi --}}
                    <div>
                        <p
                            class="text-[11px] font-semibold uppercase tracking-widest text-zinc-400 dark:text-primary-dark-500 mb-2 px-0.5">
                            Kesesuaian Referensi</p>
                        <div class="grid grid-cols-3 gap-3">
                            <x-organisms.stat-card title="Sesuai" :value="number_format($stats['sesuai'])" icon="check-badge"
                                color="sky" />
                            <x-organisms.stat-card title="Tidak Sesuai" :value="number_format($stats['tidak'])" icon="exclamation-circle"
                                color="violet" />
                            <x-organisms.stat-card title="Ranap" :value="number_format($stats['ranap'])" icon="building-office"
                                color="indigo" />
                        </div>
                    </div>
                </div>

                <x-organisms.data-panel :padding="false">
                    <x-slot:filter>
                        <div class="flex flex-wrap items-center gap-3">
                            <flux:select wire:model.live="filterStatus" class="w-48">
                                <flux:select.option value="">Semua Status</flux:select.option>
                                <flux:select.option value="selesai dilayani">Selesai Dilayani</flux:select.option>
                                <flux:select.option value="sedang dilayani">Sedang Dilayani</flux:select.option>
                                <flux:select.option value="batal">Batal</flux:select.option>
                                <flux:select.option value="belum dilayani">Belum Dilayani</flux:select.option>
                            </flux:select>
                            <flux:select wire:model.live="filterSumber" class="w-48">
                                <flux:select.option value="">Semua Sumber</flux:select.option>
                                @foreach ($sumberOptions as $opt)
                                    <flux:select.option value="{{ $opt }}">{{ $opt }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                            <div
                                class="ml-auto flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-zinc-50 dark:bg-primary-dark-900/50 rounded-xl border border-zinc-200 dark:border-primary-dark-700 whitespace-nowrap">
                                <span class="size-2 rounded-full bg-blue-400 shrink-0"></span>
                                <span
                                    class="font-semibold text-zinc-700 dark:text-primary-dark-200">{{ number_format($totalFiltered) }}</span>
                                <span class="text-zinc-400">/</span>
                                <span
                                    class="text-zinc-500 dark:text-primary-dark-400">{{ number_format($stats['total']) }}
                                    booking</span>
                            </div>
                        </div>
                    </x-slot:filter>

                    <div class="overflow-x-auto">
                        <x-organisms.table>
                            <x-slot:headings>
                                @foreach (['Kode Booking', 'Kd Poli', 'Kd Dokter', 'Jam', 'NIK', 'No. Kartu', 'RM', 'Jenis Kunjungan', 'No. Ref', 'Sumber', 'Peserta', 'No. Antrean', 'Estimasi', 'Status', 'No. Rawat', 'No. Ref (Lokal)', 'Sesuai?'] as $th)
                                    <x-atoms.table-heading
                                        class="{{ $loop->last || $loop->index === 10 ? 'text-center' : '' }}">{{ $th }}</x-atoms.table-heading>
                                @endforeach
                            </x-slot:headings>

                            @forelse ($filteredRecords as $row)
                                @php
                                    $sl = strtolower($row['status']);
                                    $statusColor = match (true) {
                                        str_contains($sl, 'selesai') => 'emerald',
                                        $sl === 'batal' => 'red',
                                        str_contains($sl, 'sedang') => 'blue',
                                        default => 'amber',
                                    };
                                @endphp
                                <x-molecules.table-row>
                                    <x-atoms.table-cell :nowrap="true">
                                        <span
                                            class="font-mono text-xs font-bold px-2 py-1 rounded-md bg-zinc-100 dark:bg-primary-dark-700 text-zinc-600 dark:text-primary-dark-300 ring-1 ring-zinc-200 dark:ring-primary-dark-600">{{ $row['kode_booking'] }}</span>
                                    </x-atoms.table-cell>
                                    <x-atoms.table-cell class="text-sm text-zinc-600 dark:text-primary-dark-400"
                                        :nowrap="true">{{ $row['kd_poli'] }}</x-atoms.table-cell>
                                    <x-atoms.table-cell class="text-sm text-zinc-600 dark:text-primary-dark-400"
                                        :nowrap="true">{{ $row['kd_dokter'] }}</x-atoms.table-cell>
                                    <x-atoms.table-cell class="text-sm text-zinc-600 dark:text-primary-dark-400"
                                        :nowrap="true">{{ $row['jam_praktek'] }}</x-atoms.table-cell>
                                    <x-atoms.table-cell
                                        class="font-mono text-xs text-zinc-600 dark:text-primary-dark-400"
                                        :nowrap="true">{{ $row['nik'] }}</x-atoms.table-cell>
                                    <x-atoms.table-cell
                                        class="font-mono text-xs text-zinc-600 dark:text-primary-dark-400"
                                        :nowrap="true">{{ $row['no_kartu'] }}</x-atoms.table-cell>
                                    <x-atoms.table-cell class="text-sm text-zinc-600 dark:text-primary-dark-400"
                                        :nowrap="true">{{ $row['no_rm'] }}</x-atoms.table-cell>
                                    <x-atoms.table-cell class="text-sm text-zinc-600 dark:text-primary-dark-400"
                                        :nowrap="true">{{ $row['jenis_kunjungan'] }}</x-atoms.table-cell>
                                    <x-atoms.table-cell
                                        class="font-mono text-xs text-zinc-600 dark:text-primary-dark-400"
                                        :nowrap="true"
                                        title="{{ $row['no_referensi'] }}">{{ $row['no_referensi'] }}</x-atoms.table-cell>
                                    <x-atoms.table-cell :nowrap="true">
                                        <flux:badge size="sm"
                                            :color="stripos($row['sumber_data'], 'Mobile') !== false ? 'purple' : 'blue'">
                                            {{ $row['sumber_data'] }}</flux:badge>
                                    </x-atoms.table-cell>
                                    <x-atoms.table-cell align="center" :nowrap="true">
                                        <flux:badge size="sm" :color="$row['is_peserta'] ? 'teal' : 'zinc'">
                                            {{ $row['is_peserta'] ? 'Ya' : 'Tidak' }}</flux:badge>
                                    </x-atoms.table-cell>
                                    <x-atoms.table-cell
                                        class="font-mono text-xs text-zinc-600 dark:text-primary-dark-400"
                                        :nowrap="true">{{ $row['no_antrean'] }}</x-atoms.table-cell>
                                    <x-atoms.table-cell class="text-sm text-zinc-600 dark:text-primary-dark-400"
                                        :nowrap="true">{{ $row['estimasi'] }}</x-atoms.table-cell>
                                    <x-atoms.table-cell :nowrap="true">
                                        <flux:badge size="sm" :color="$statusColor">{{ $row['status'] }}
                                        </flux:badge>
                                    </x-atoms.table-cell>
                                    <x-atoms.table-cell
                                        class="font-mono text-xs text-zinc-600 dark:text-primary-dark-400"
                                        :nowrap="true">{{ $row['no_rawat'] ?: '-' }}</x-atoms.table-cell>
                                    <x-atoms.table-cell
                                        class="font-mono text-xs text-zinc-600 dark:text-primary-dark-400"
                                        :nowrap="true"
                                        title="{{ $row['no_ref_lokal'] }}">{{ $row['no_ref_lokal'] ?: '-' }}</x-atoms.table-cell>
                                    <x-atoms.table-cell align="center" :nowrap="true">
                                        @if ($row['kesesuaian'] === 'sesuai')
                                            <span class="text-emerald-600 dark:text-emerald-400 font-bold">✓</span>
                                        @elseif ($row['kesesuaian'] === 'tidak')
                                            <span class="text-red-600 dark:text-red-400 font-bold">✗</span>
                                        @elseif ($row['kesesuaian'] === 'ranap')
                                            <flux:badge size="sm" color="blue">Ranap</flux:badge>
                                        @else
                                            <span class="text-zinc-400">—</span>
                                        @endif
                                    </x-atoms.table-cell>
                                </x-molecules.table-row>
                            @empty
                                <x-molecules.table-row>
                                    <x-atoms.table-cell colspan="17" class="py-12 text-center">
                                        <x-ui.empty-state icon="queue-list" title="Tidak ada data"
                                            description="Tidak ada booking yang sesuai filter." />
                                    </x-atoms.table-cell>
                                </x-molecules.table-row>
                            @endforelse
                        </x-organisms.table>
                    </div>

                    @if ($lastPage > 1)
                        <x-slot:footer>
                            @php
                                $antreanPageNumbers = [];
                                if ($lastPage <= 7) {
                                    $antreanPageNumbers = range(1, $lastPage);
                                } elseif ($antreanPage <= 4) {
                                    $antreanPageNumbers = [...range(1, 5), null, $lastPage];
                                } elseif ($antreanPage >= $lastPage - 3) {
                                    $antreanPageNumbers = [1, null, ...range($lastPage - 4, $lastPage)];
                                } else {
                                    $antreanPageNumbers = [
                                        1,
                                        null,
                                        $antreanPage - 1,
                                        $antreanPage,
                                        $antreanPage + 1,
                                        null,
                                        $lastPage,
                                    ];
                                }
                            @endphp
                            <x-molecules.pagination :page="$antreanPage" :total-page="$lastPage" :total="$totalFiltered"
                                :page-numbers="$antreanPageNumbers" on-prev="prevPage" on-next="nextPage" on-goto="gotoAntreanPage" />
                        </x-slot:footer>
                    @endif
                </x-organisms.data-panel>
            @endif
        @endif

        {{-- ===== TAB: RIWAYAT PENARIKAN ===== --}}
        @if ($tab === 'riwayat')
            @if ($riwayatLogs->isEmpty())
                <x-ui.empty-state icon="clock" title="Belum ada riwayat"
                    description="Belum ada aktivitas penarikan antrean yang tercatat." />
            @else
                <x-organisms.data-panel :padding="false">
                    <x-slot:filter>
                        <div class="flex items-center gap-3">
                            <div
                                class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-zinc-50 dark:bg-primary-dark-900/50 rounded-xl border border-zinc-200 dark:border-primary-dark-700 whitespace-nowrap">
                                <span class="size-2 rounded-full bg-blue-400 shrink-0"></span>
                                <span
                                    class="font-semibold text-zinc-700 dark:text-primary-dark-200">{{ number_format($riwayatTotal) }}</span>
                                <span class="text-zinc-400 dark:text-primary-dark-500">total log</span>
                            </div>
                            <div class="ml-auto">
                                <x-atoms.button wire:click="$refresh" variant="ghost"
                                    icon="arrow-path">Refresh</x-atoms.button>
                            </div>
                        </div>
                    </x-slot:filter>

                    <div class="overflow-x-auto">
                        <x-organisms.table>
                            <x-slot:headings>
                                <x-atoms.table-heading>Status</x-atoms.table-heading>
                                <x-atoms.table-heading>Method</x-atoms.table-heading>
                                <x-atoms.table-heading>Endpoint</x-atoms.table-heading>
                                <x-atoms.table-heading align="center">Kode</x-atoms.table-heading>
                                <x-atoms.table-heading>Pesan Error</x-atoms.table-heading>
                                <x-atoms.table-heading>Waktu</x-atoms.table-heading>
                                <x-atoms.table-heading align="center" class="w-16">Detail</x-atoms.table-heading>
                            </x-slot:headings>

                            @foreach ($riwayatLogs as $log)
                                @php
                                    $isSuccess = $log->status === 'success';
                                    $statusColor = $isSuccess ? 'emerald' : 'red';
                                    $statusLabel = $isSuccess ? 'Berhasil' : 'Gagal';
                                @endphp
                                <x-molecules.table-row>
                                    <x-atoms.table-cell :nowrap="true">
                                        <flux:badge size="sm" :color="$statusColor">{{ $statusLabel }}
                                        </flux:badge>
                                    </x-atoms.table-cell>
                                    <x-atoms.table-cell :nowrap="true">
                                        <x-atoms.method-badge :method="$log->method ?? 'GET'" />
                                    </x-atoms.table-cell>
                                    <x-atoms.table-cell :nowrap="true">
                                        <p class="font-mono text-xs text-zinc-600 dark:text-primary-dark-300 max-w-xs truncate"
                                            title="{{ $log->endpoint }}">{{ $log->endpoint }}</p>
                                    </x-atoms.table-cell>
                                    <x-atoms.table-cell align="center" :nowrap="true">
                                        @if ($log->response_status)
                                            <span
                                                class="font-mono text-xs font-semibold {{ $log->response_status == 200 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                                                {{ $log->response_status }}
                                            </span>
                                        @else
                                            <span class="text-zinc-400">—</span>
                                        @endif
                                    </x-atoms.table-cell>
                                    <x-atoms.table-cell :nowrap="true">
                                        @if ($log->error_message)
                                            <p class="text-xs text-red-500 dark:text-red-400 max-w-xs truncate"
                                                title="{{ $log->error_message }}">{{ $log->error_message }}</p>
                                        @else
                                            <span class="text-zinc-300 dark:text-primary-dark-600 text-xs">—</span>
                                        @endif
                                    </x-atoms.table-cell>
                                    <x-atoms.table-cell :nowrap="true">
                                        <p class="text-xs text-zinc-600 dark:text-primary-dark-400">
                                            {{ $log->created_at->format('d/m/Y H:i:s') }}</p>
                                        <p class="text-[11px] text-zinc-400 dark:text-primary-dark-500 mt-0.5">
                                            {{ $log->created_at->diffForHumans() }}</p>
                                    </x-atoms.table-cell>
                                    <x-atoms.table-cell align="center" :action="true">
                                        <x-atoms.button wire:click="viewLogDetail('{{ $log->id }}')"
                                            size="sm" variant="ghost" icon="eye" />
                                    </x-atoms.table-cell>
                                </x-molecules.table-row>
                            @endforeach
                        </x-organisms.table>
                    </div>

                    @if ($riwayatLastPage > 1)
                        <x-slot:footer>
                            @php
                                $riwayatPageNumbers = [];
                                if ($riwayatLastPage <= 7) {
                                    $riwayatPageNumbers = range(1, $riwayatLastPage);
                                } elseif ($riwayatPage <= 4) {
                                    $riwayatPageNumbers = [...range(1, 5), null, $riwayatLastPage];
                                } elseif ($riwayatPage >= $riwayatLastPage - 3) {
                                    $riwayatPageNumbers = [1, null, ...range($riwayatLastPage - 4, $riwayatLastPage)];
                                } else {
                                    $riwayatPageNumbers = [
                                        1,
                                        null,
                                        $riwayatPage - 1,
                                        $riwayatPage,
                                        $riwayatPage + 1,
                                        null,
                                        $riwayatLastPage,
                                    ];
                                }
                            @endphp
                            <x-molecules.pagination :page="$riwayatPage" :total-page="$riwayatLastPage" :total="$riwayatTotal"
                                :page-numbers="$riwayatPageNumbers" on-prev="prevRiwayatPage" on-next="nextRiwayatPage"
                                on-goto="gotoRiwayatPage" />
                        </x-slot:footer>
                    @endif
                </x-organisms.data-panel>
            @endif
        @endif

    </div>

    {{-- Modal: Detail Log Penarikan --}}
    <x-organisms.modal wire:model="showLogDetailModal" title="Detail Log Penarikan" maxWidth="2xl">
        @if ($showLogDetailModal && $logDetail)
            @php
                $isSuccess = $logDetail->status === 'success';
            @endphp
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div
                        class="flex flex-col gap-1 p-3 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/50 border border-zinc-200 dark:border-primary-dark-700">
                        <span class="text-xs text-zinc-400 dark:text-primary-dark-500">Status</span>
                        <flux:badge size="sm" :color="$isSuccess ? 'emerald' : 'red'">
                            {{ $isSuccess ? 'Berhasil' : 'Gagal' }}</flux:badge>
                    </div>
                    <div
                        class="flex flex-col gap-1 p-3 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/50 border border-zinc-200 dark:border-primary-dark-700">
                        <span class="text-xs text-zinc-400 dark:text-primary-dark-500">Kode Respons</span>
                        <span
                            class="font-mono font-semibold {{ $logDetail->response_status == 200 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500 dark:text-red-400' }}">
                            {{ $logDetail->response_status ?? '—' }}
                        </span>
                    </div>
                    <div
                        class="col-span-2 flex flex-col gap-1 p-3 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/50 border border-zinc-200 dark:border-primary-dark-700">
                        <span class="text-xs text-zinc-400 dark:text-primary-dark-500">Endpoint</span>
                        <span
                            class="font-mono text-xs text-zinc-700 dark:text-primary-dark-200 break-all">{{ $logDetail->endpoint }}</span>
                    </div>
                    <div
                        class="col-span-2 flex flex-col gap-1 p-3 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/50 border border-zinc-200 dark:border-primary-dark-700">
                        <span class="text-xs text-zinc-400 dark:text-primary-dark-500">Waktu</span>
                        <span
                            class="text-xs text-zinc-700 dark:text-primary-dark-200">{{ $logDetail->created_at->translatedFormat('d F Y, H:i:s') }}</span>
                    </div>
                </div>

                @if ($logDetail->error_message)
                    <div
                        class="p-3 rounded-xl bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800/50">
                        <p class="text-xs font-semibold text-red-700 dark:text-red-400 mb-1">Pesan Error</p>
                        <p class="text-xs text-red-600 dark:text-red-300 break-all">{{ $logDetail->error_message }}
                        </p>
                    </div>
                @endif

                @if ($logDetail->response_payload)
                    <div>
                        <p class="text-xs font-semibold text-zinc-500 dark:text-primary-dark-400 mb-1.5">Response
                            Payload</p>
                        <pre
                            class="text-[11px] font-mono text-zinc-700 dark:text-primary-dark-200 bg-zinc-50 dark:bg-primary-dark-900/60 border border-zinc-200 dark:border-primary-dark-700 rounded-xl p-3 overflow-auto max-h-60">{{ json_encode($logDetail->response_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </div>
                @endif
            </div>
        @endif
        <x-slot:footer>
            <div class="flex justify-end">
                <x-atoms.button variant="ghost" wire:click="$set('showLogDetailModal', false)">Tutup</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal: Konfirmasi Overwrite --}}
    <x-organisms.modal wire:model="showPullModal" title="Data sudah pernah ditarik" maxWidth="lg"
        description="Tanggal berikut sudah memiliki data tersimpan. Lanjutkan untuk memperbarui?">
        <div class="overflow-hidden rounded-xl border border-amber-200 dark:border-amber-700/50">
            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading class="text-amber-700 dark:text-amber-400">Tanggal</x-atoms.table-heading>
                    <x-atoms.table-heading align="right"
                        class="text-amber-700 dark:text-amber-400">Tersimpan</x-atoms.table-heading>
                </x-slot:headings>
                @foreach ($modalConflicts as $date => $count)
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-sm text-zinc-700 dark:text-primary-dark-300">
                            {{ Carbon::parse($date)->translatedFormat('d F Y') }}
                        </x-atoms.table-cell>
                        <x-atoms.table-cell align="right"
                            class="font-semibold text-zinc-700 dark:text-primary-dark-300">
                            {{ number_format($count) }} booking
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @endforeach
            </x-organisms.table>
        </div>
        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <x-atoms.button wire:click="cancelPull" variant="ghost">Batal</x-atoms.button>
                <x-atoms.button wire:click="confirmPull" variant="primary" wire:loading.attr="disabled"
                    wire:target="confirmPull">
                    Lanjutkan & Perbarui
                </x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal: Detail Rekap --}}
    @php
        $detailMeta = match ($modalDetailType) {
            'regs' => ['title' => 'Daftar Pasien BPJS', 'color' => 'zinc', 'note' => null],
            'antrean' => ['title' => 'Daftar Antrean Terbit', 'color' => 'emerald', 'note' => null],
            'belum' => [
                'title' => 'Estimasi Belum Antrean (per Slot)',
                'color' => 'amber',
                'note' =>
                    'Menampilkan registrasi BPJS (non-Batal) yang tidak memiliki antrean — kemungkinan pasien tersebut belum mendapat nomor Antrean.',
            ],
            default => ['title' => '-', 'color' => 'zinc', 'note' => null],
        };
    @endphp
    <x-organisms.modal wire:model="showDetailModal" :title="$detailMeta['title']" maxWidth="6xl">
        @if ($showDetailModal)
            <div class="space-y-4">
                <div class="flex items-center gap-2">
                    <flux:badge color="{{ $detailMeta['color'] }}" size="sm">{{ $modalDetailRecords->count() }}
                        data</flux:badge>
                </div>
                @if ($detailMeta['note'])
                    <div
                        class="flex items-start gap-2 p-3 rounded-xl bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-800/50 text-xs text-amber-700 dark:text-amber-300">
                        <flux:icon name="information-circle" class="size-4 shrink-0 mt-0.5" />
                        {{ $detailMeta['note'] }}
                    </div>
                @endif
                @if ($modalDetailRecords->isEmpty())
                    <x-ui.empty-state icon="inbox" title="Tidak ada data"
                        description="Tidak ada data untuk periode ini." />
                @else
                    <x-organisms.card-box :padding="false" class="max-h-[60vh] overflow-y-auto">
                        <x-organisms.table>
                            <x-slot:headings>
                                @if ($modalDetailType === 'antrean')
                                    @foreach (['#', 'Kode Booking', 'Tanggal', 'Kd Poli', 'Kd Dokter', 'NIK', 'No. Kartu', 'No. RM', 'No. Antrean', 'Sumber', 'Peserta', 'Status'] as $th)
                                        <x-atoms.table-heading>{{ $th }}</x-atoms.table-heading>
                                    @endforeach
                                @elseif ($modalDetailType === 'belum')
                                    @foreach (['#', 'No. Rawat', 'Tanggal', 'Poliklinik', 'Dokter', 'Status'] as $th)
                                        <x-atoms.table-heading>{{ $th }}</x-atoms.table-heading>
                                    @endforeach
                                @else
                                    @foreach (['#', 'No. Rawat', 'Tanggal', 'Kd Poli', 'Kd Dokter', 'Status Lanjut'] as $th)
                                        <x-atoms.table-heading>{{ $th }}</x-atoms.table-heading>
                                    @endforeach
                                @endif
                            </x-slot:headings>
                            @foreach ($modalDetailRecords as $i => $rec)
                                <x-molecules.table-row>
                                    <x-atoms.table-cell
                                        class="text-xs text-zinc-400 tabular-nums">{{ $i + 1 }}</x-atoms.table-cell>
                                    @if ($modalDetailType === 'antrean')
                                        <x-atoms.table-cell :nowrap="true"><span
                                                class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-zinc-600 dark:text-primary-dark-300">{{ $rec->kode_booking }}</span></x-atoms.table-cell>
                                        <x-atoms.table-cell
                                            class="font-mono text-xs text-zinc-600 dark:text-primary-dark-400"
                                            :nowrap="true">{{ $rec->tanggal }}</x-atoms.table-cell>
                                        <x-atoms.table-cell class="text-sm text-zinc-600 dark:text-primary-dark-400"
                                            :nowrap="true">{{ $rec->kd_poli }}</x-atoms.table-cell>
                                        <x-atoms.table-cell class="text-sm text-zinc-600 dark:text-primary-dark-400"
                                            :nowrap="true">{{ $rec->kd_dokter }}</x-atoms.table-cell>
                                        <x-atoms.table-cell
                                            class="font-mono text-xs text-zinc-600 dark:text-primary-dark-400"
                                            :nowrap="true">{{ $rec->nik ?: '-' }}</x-atoms.table-cell>
                                        <x-atoms.table-cell
                                            class="font-mono text-xs text-zinc-600 dark:text-primary-dark-400"
                                            :nowrap="true">{{ $rec->no_kartu ?: '-' }}</x-atoms.table-cell>
                                        <x-atoms.table-cell
                                            class="font-mono text-xs text-zinc-600 dark:text-primary-dark-400"
                                            :nowrap="true">{{ $rec->no_rm ?: '-' }}</x-atoms.table-cell>
                                        <x-atoms.table-cell
                                            class="font-mono text-xs text-zinc-600 dark:text-primary-dark-400"
                                            :nowrap="true">{{ $rec->no_antrean ?: '-' }}</x-atoms.table-cell>
                                        <x-atoms.table-cell :nowrap="true">
                                            <flux:badge size="sm"
                                                :color="stripos($rec->sumber_data ?? '', 'Mobile') !== false ? 'purple' : 'blue'">
                                                {{ $rec->sumber_data ?: '-' }}</flux:badge>
                                        </x-atoms.table-cell>
                                        <x-atoms.table-cell :nowrap="true">
                                            <flux:badge size="sm" :color="$rec->is_peserta ? 'teal' : 'zinc'">
                                                {{ $rec->is_peserta ? 'Ya' : 'Tidak' }}</flux:badge>
                                        </x-atoms.table-cell>
                                        <x-atoms.table-cell :nowrap="true">
                                            @php
                                                $sl = strtolower($rec->status ?? '');
                                                $sc = match (true) {
                                                    str_contains($sl, 'selesai') => 'emerald',
                                                    $sl === 'batal' => 'red',
                                                    str_contains($sl, 'sedang') => 'blue',
                                                    default => 'amber',
                                                };
                                            @endphp
                                            <flux:badge size="sm" :color="$sc">
                                                {{ $rec->status ?: '-' }}</flux:badge>
                                        </x-atoms.table-cell>
                                    @elseif ($modalDetailType === 'belum')
                                        <x-atoms.table-cell
                                            class="font-mono text-xs font-semibold text-zinc-700 dark:text-primary-dark-300"
                                            :nowrap="true">{{ $rec->no_rawat }}</x-atoms.table-cell>
                                        <x-atoms.table-cell
                                            class="font-mono text-xs text-zinc-600 dark:text-primary-dark-400"
                                            :nowrap="true">{{ $rec->tanggal }}</x-atoms.table-cell>
                                        <x-atoms.table-cell :nowrap="true">
                                            <p class="text-xs font-medium text-zinc-700 dark:text-primary-dark-200">
                                                {{ $rec->kd_poli }}</p>
                                            @if ($rec->nm_poli)
                                                <p class="text-[11px] text-zinc-400 dark:text-primary-dark-500">
                                                    {{ $rec->nm_poli }}</p>
                                            @endif
                                        </x-atoms.table-cell>
                                        <x-atoms.table-cell :nowrap="true">
                                            <p class="text-xs font-medium text-zinc-700 dark:text-primary-dark-200">
                                                {{ $rec->kd_dokter }}</p>
                                            @if ($rec->nm_dokter)
                                                <p class="text-[11px] text-zinc-400 dark:text-primary-dark-500">
                                                    {{ $rec->nm_dokter }}</p>
                                            @endif
                                        </x-atoms.table-cell>
                                        <x-atoms.table-cell :nowrap="true">
                                            <flux:badge size="sm"
                                                :color="$rec->status_lanjut === 'Ranap' ? 'blue' : 'zinc'">
                                                {{ $rec->status_lanjut ?: '-' }}</flux:badge>
                                        </x-atoms.table-cell>
                                    @else
                                        <x-atoms.table-cell
                                            class="font-mono text-xs text-zinc-700 dark:text-primary-dark-300"
                                            :nowrap="true">{{ $rec->no_rawat }}</x-atoms.table-cell>
                                        <x-atoms.table-cell
                                            class="font-mono text-xs text-zinc-600 dark:text-primary-dark-400"
                                            :nowrap="true">{{ $rec->tanggal }}</x-atoms.table-cell>
                                        <x-atoms.table-cell class="text-sm text-zinc-600 dark:text-primary-dark-400"
                                            :nowrap="true">{{ $rec->kd_poli }}</x-atoms.table-cell>
                                        <x-atoms.table-cell class="text-sm text-zinc-600 dark:text-primary-dark-400"
                                            :nowrap="true">{{ $rec->kd_dokter }}</x-atoms.table-cell>
                                        <x-atoms.table-cell :nowrap="true">
                                            <flux:badge size="sm"
                                                :color="$rec->status_lanjut === 'Ranap' ? 'blue' : 'zinc'">
                                                {{ $rec->status_lanjut ?: '-' }}</flux:badge>
                                        </x-atoms.table-cell>
                                    @endif
                                </x-molecules.table-row>
                            @endforeach
                        </x-organisms.table>
                    </x-organisms.card-box>
                    @if ($modalDetailRecords->count() >= 500)
                        <p class="text-xs text-zinc-400 text-right mt-2">Ditampilkan maks. 500 data pertama.</p>
                    @endif
                @endif
            </div>
        @endif
        <x-slot:footer>
            <div class="flex justify-end">
                <x-atoms.button variant="ghost" wire:click="$set('showDetailModal', false)">Tutup</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal: List Antrean Tersedia --}}
    <x-organisms.modal wire:model="showDatesModal" title="List Antrean Tersedia"
        description="Tanggal yang sudah memiliki data booking tersimpan." maxWidth="xl">
        <div class="space-y-4">
            @if ($availableDates->isEmpty())
                <x-ui.empty-state icon="calendar-days" title="Belum ada data"
                    description="Belum ada data yang ditarik." />
            @else
                <div
                    class="overflow-hidden rounded-xl border border-zinc-200 dark:border-primary-dark-700 max-h-96 overflow-y-auto shadow-sm">
                    <x-organisms.table>
                        <x-slot:headings>
                            <x-atoms.table-heading>Tanggal</x-atoms.table-heading>
                            <x-atoms.table-heading align="right">Booking</x-atoms.table-heading>
                            <x-atoms.table-heading align="center" class="w-24">Aksi</x-atoms.table-heading>
                        </x-slot:headings>
                        @foreach ($availableDates as $item)
                            <x-molecules.table-row>
                                <x-atoms.table-cell>
                                    <span
                                        class="font-medium text-zinc-800 dark:text-primary-dark-200">{{ Carbon::parse($item->tanggal)->translatedFormat('d M Y') }}</span>
                                    <span class="ml-1.5 font-mono text-xs text-zinc-400">{{ $item->tanggal }}</span>
                                </x-atoms.table-cell>
                                <x-atoms.table-cell align="right"
                                    class="font-mono font-semibold text-zinc-700 dark:text-primary-dark-200">{{ number_format($item->cnt) }}</x-atoms.table-cell>
                                <x-atoms.table-cell align="center" :action="true">
                                    <x-atoms.button wire:click="viewDate('{{ $item->tanggal }}')" size="sm"
                                        variant="ghost" icon="eye">Lihat</x-atoms.button>
                                </x-atoms.table-cell>
                            </x-molecules.table-row>
                        @endforeach
                    </x-organisms.table>
                </div>
                <p class="text-xs text-zinc-400 text-right">{{ $availableDates->count() }} tanggal tersedia</p>
            @endif
        </div>
        <x-slot:footer>
            <div class="flex justify-end">
                <x-atoms.button variant="ghost" wire:click="$set('showDatesModal', false)">Tutup</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

</div>

@pushOnce('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script>
        function antreanChartEl() {
            return {
                init() {
                    const el = this.$el;
                    const canvasId = el.dataset.canvas;
                    const data = JSON.parse(el.dataset.chart);
                    const horizontal = el.dataset.horizontal === 'true';
                    const stacked = el.dataset.stacked === 'true';
                    const canvas = document.getElementById(canvasId);
                    if (!canvas) return;
                    const existing = Chart.getChart(canvas);
                    if (existing) existing.destroy();
                    new Chart(canvas, {
                        type: 'bar',
                        data: data,
                        options: {
                            indexAxis: horizontal ? 'y' : 'x',
                            responsive: true,
                            maintainAspectRatio: true,
                            interaction: {
                                mode: 'index',
                                intersect: false,
                            },
                            plugins: {
                                legend: {
                                    display: data.datasets.length > 1,
                                    labels: {
                                        color: '#9ca3af',
                                        font: {
                                            size: 11
                                        },
                                    },
                                },
                                tooltip: {
                                    callbacks: {
                                        footer: (items) => {
                                            const total = items.reduce((s, i) => s + (horizontal ? i.parsed.x :
                                                i.parsed.y), 0);
                                            return 'Total: ' + total;
                                        },
                                    },
                                },
                            },
                            scales: {
                                x: {
                                    stacked: stacked,
                                    ticks: {
                                        color: '#9ca3af',
                                        font: {
                                            size: 11
                                        }
                                    },
                                    grid: {
                                        color: 'rgba(156,163,175,.15)'
                                    },
                                },
                                y: {
                                    stacked: stacked,
                                    ticks: {
                                        color: '#9ca3af',
                                        font: {
                                            size: 11
                                        }
                                    },
                                    grid: {
                                        color: 'rgba(156,163,175,.15)'
                                    },
                                },
                            },
                        },
                    });
                },
            };
        }
    </script>
@endPushOnce
