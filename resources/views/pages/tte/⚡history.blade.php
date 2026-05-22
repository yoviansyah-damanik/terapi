<?php

use App\Models\Simrs\Pegawai;
use App\Models\TteDocument;
use App\Services\TteService;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

new #[Layout('layouts::app')] #[Title('Riwayat TTE')] class extends Component {
    use WithPagination;

    #[Url]
    public string $activeTab = 'list';

    // --- Tab Daftar Berkas ---
    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $filterSource = '';

    #[Url]
    public string $filterAction = '';

    #[Url]
    public string $filterDate = '';

    public ?string $documentId = null;
    public int $verifyFileIndex = 0;
    public ?array $verifyResult = null;
    public string $verifyError = '';

    // --- Tab Laporan ---
    public string $reportRange = '30d';
    public string $reportDateFrom = '';
    public string $reportDateTo = '';
    public string $employeeSearch = '';

    public function updatedActiveTab(): void
    {
        $this->dispatch('tte-refresh-charts');
    }

    public function updatedReportRange(): void
    {
        $this->dispatch('tte-refresh-charts');
    }

    public function updatedReportDateFrom(): void
    {
        $this->dispatch('tte-refresh-charts');
    }

    public function updatedReportDateTo(): void
    {
        $this->dispatch('tte-refresh-charts');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }
    public function updatingFilterSource(): void
    {
        $this->resetPage();
    }
    public function updatingFilterAction(): void
    {
        $this->resetPage();
    }
    public function updatingFilterDate(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filterSource = '';
        $this->filterAction = '';
        $this->filterDate = '';
        $this->resetPage();
    }

    /** Buka modal gabungan preview + verifikasi */
    public function openDocument(string $id): void
    {
        $this->documentId = $id;
        $this->verifyFileIndex = 0;
        $this->verifyResult = null;
        $this->verifyError = '';
        $this->dispatch('open-document-modal');
    }

    /** Ganti file yang diverifikasi (dipanggil dari Alpine saat tab berkas berganti) */
    public function selectVerifyFile(int $index): void
    {
        $this->verifyFileIndex = $index;
        $this->verifyResult = null;
        $this->verifyError = '';
    }

    /** Jalankan verifikasi pada file yang sedang dipilih */
    public function runVerification(): void
    {
        if (!$this->documentId) {
            return;
        }

        $doc = TteDocument::find($this->documentId);
        if (!$doc) {
            $this->verifyError = 'Dokumen tidak ditemukan.';
            return;
        }

        $files = $doc->signed_files ?? [];
        if (empty($files)) {
            $this->verifyError = 'Tidak ada berkas tersimpan untuk dokumen ini.';
            return;
        }

        $path = $files[$this->verifyFileIndex] ?? $files[0];
        if (!Storage::disk('tte_signed')->exists($path)) {
            $this->verifyError = 'File tidak tersedia di server.';
            return;
        }

        $this->verifyResult = app(TteService::class)->verifyPdf(base64_encode(Storage::disk('tte_signed')->get($path)));
    }

    /** Kembalikan [Carbon $from, Carbon $to] sesuai rentang yang dipilih */
    private function reportDateRange(): array
    {
        return match ($this->reportRange) {
            'today' => [today(), today()],
            '7d' => [now()->subDays(6)->startOfDay(), today()],
            'month' => [now()->startOfMonth(), today()],
            'custom' => $this->reportDateFrom && $this->reportDateTo ? [\Carbon\Carbon::parse($this->reportDateFrom), \Carbon\Carbon::parse($this->reportDateTo)->min(\Carbon\Carbon::parse($this->reportDateFrom)->addDays(59))] : [now()->subDays(29)->startOfDay(), today()],
            default => [now()->subDays(29)->startOfDay(), today()],
        };
    }

    private function reportQuery()
    {
        $q = TteDocument::query();
        [$from, $to] = $this->reportDateRange();

        return $q->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
    }

    public function with(): array
    {
        $stats = TteDocument::selectRaw(
            "
            COUNT(*) as total,
            COUNT(DISTINCT nik) as total_employees,
            SUM(action = 'sign_pdf') as sign_count,
            SUM(action = 'seal_pdf') as seal_count
        ",
        )->first();

        $listQuery = TteDocument::orderByDesc('created_at')->when($this->search, fn($q) => $q->search($this->search))->when($this->filterSource, fn($q) => $q->forSource($this->filterSource))->when($this->filterAction, fn($q) => $q->forAction($this->filterAction))->when($this->filterDate, fn($q) => $q->whereDate('created_at', $this->filterDate));

        $rawRows = $this->reportQuery()
            ->selectRaw(
                "
                DATE(created_at) as date,
                SUM(action = 'sign_pdf') as sign_count,
                SUM(action = 'seal_pdf') as seal_count,
                COUNT(*) as total
            ",
            )
            ->groupByRaw('DATE(created_at)')
            ->orderByDesc('date')
            ->get();

        $reportRows = $rawRows;

        [$dateFrom, $dateTo] = $this->reportDateRange();
        $byDate = $rawRows->keyBy('date');
        $reportChartRows = collect();
        $period = \Carbon\CarbonPeriod::create($dateFrom->toDateString(), $dateTo->toDateString());
        foreach ($period as $date) {
            $key = $date->toDateString();
            $reportChartRows->push(
                (object) [
                    'date' => $key,
                    'sign_count' => (int) ($byDate->get($key)?->sign_count ?? 0),
                    'seal_count' => (int) ($byDate->get($key)?->seal_count ?? 0),
                    'total' => (int) ($byDate->get($key)?->total ?? 0),
                ],
            );
        }

        $employeeRowsAll = TteDocument::whereNotNull('nik')
            ->selectRaw(
                "
                nik,
                SUM(action = 'sign_pdf') as sign_count,
                SUM(action = 'seal_pdf') as seal_count,
                COUNT(*) as total,
                MAX(created_at) as last_used
            ",
            )
            ->groupBy('nik')
            ->orderByDesc('total')
            ->get();

        $nikMap = [];
        try {
            $niks = $employeeRowsAll->pluck('nik')->filter()->unique()->values()->all();
            if (!empty($niks)) {
                $nikMap = Pegawai::whereIn('no_ktp', $niks)->pluck('nama', 'no_ktp')->all();
            }
        } catch (\Throwable) {
        }

        $employeeRows = $employeeRowsAll;
        if ($this->employeeSearch) {
            $term = strtolower($this->employeeSearch);
            $employeeRows = $employeeRowsAll->filter(fn($r) => str_contains(strtolower($r->nik), $term) || str_contains(strtolower($nikMap[$r->nik] ?? ''), $term))->values();
        }

        return [
            'stats' => $stats,
            'documents' => $listQuery->paginate(20),
            'reportRows' => $reportRows,
            'reportChartRows' => $reportChartRows,
            'employeeRows' => $employeeRows,
            'employeeChartRows' => $employeeRowsAll->take(15),
            'nikMap' => $nikMap,
            'activeDoc' => $this->documentId ? TteDocument::find($this->documentId) : null,
        ];
    }
};
?>

<div @open-document-modal.window="$flux.modal('tte-document').show()">
    <x-ui.page-header title="Riwayat TTE" subtitle="Seluruh dokumen yang telah berhasil ditandatangani atau disegel" />

    {{-- Stats --}}
    <div class="grid grid-cols-2 gap-3 mb-5 sm:grid-cols-4">
        <x-organisms.stat-card title="Total Berkas" value="{{ number_format($stats->total ?? 0) }}" icon="document-text" color="zinc" />
        <x-organisms.stat-card title="Total Pegawai" value="{{ number_format($stats->total_employees ?? 0) }}" icon="users" color="blue" />
        <x-organisms.stat-card title="Sign PDF" value="{{ number_format($stats->sign_count ?? 0) }}" icon="pencil-square" color="emerald" />
        <x-organisms.stat-card title="Seal PDF" value="{{ number_format($stats->seal_count ?? 0) }}" icon="shield-check" color="violet" />
    </div>

    {{-- Tabs --}}
    <x-molecules.tabs class="mb-4">
        <x-atoms.tab-item wire:click="$set('activeTab', 'list')" :active="$activeTab === 'list'">
            <flux:icon name="document-text" class="w-4 h-4" />
            Daftar Berkas
        </x-atoms.tab-item>
        <x-atoms.tab-item wire:click="$set('activeTab', 'report')" :active="$activeTab === 'report'">
            <flux:icon name="chart-bar" class="w-4 h-4" />
            Laporan
        </x-atoms.tab-item>
    </x-molecules.tabs>

    {{-- ==================== TAB: DAFTAR BERKAS ==================== --}}
    @if ($activeTab === 'list')
        <div class="flex flex-wrap gap-3 mb-4">
            <div class="flex-1 min-w-48">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari NIK, user API, IP…"
                    icon="magnifying-glass" clearable />
            </div>
            <flux:select wire:model.live="filterSource" class="w-40">
                <flux:select.option value="">Semua Sumber</flux:select.option>
                <flux:select.option value="api">API</flux:select.option>
                <flux:select.option value="simulation">Simulasi</flux:select.option>
            </flux:select>
            <flux:select wire:model.live="filterAction" class="w-40">
                <flux:select.option value="">Semua Aksi</flux:select.option>
                <flux:select.option value="sign_pdf">Sign PDF</flux:select.option>
                <flux:select.option value="seal_pdf">Seal PDF</flux:select.option>
            </flux:select>
            <flux:input wire:model.live="filterDate" type="date" class="w-44" />
            @if ($search || $filterSource || $filterAction || $filterDate)
                <x-atoms.button wire:click="resetFilters" variant="ghost" icon="x-mark" size="sm">Reset</x-atoms.button>
            @endif
        </div>

        <x-organisms.table>
            <x-slot:headings>
                <x-atoms.table-heading class="w-36">Waktu</x-atoms.table-heading>
                <x-atoms.table-heading class="w-24">Sumber</x-atoms.table-heading>
                <x-atoms.table-heading class="w-24">Aksi</x-atoms.table-heading>
                <x-atoms.table-heading class="hidden md:table-cell">NIK</x-atoms.table-heading>
                <x-atoms.table-heading class="hidden lg:table-cell w-24">Mode</x-atoms.table-heading>
                <x-atoms.table-heading class="hidden xl:table-cell">Sumber API</x-atoms.table-heading>
                <x-atoms.table-heading class="w-16" align="center">Berkas</x-atoms.table-heading>
                <x-atoms.table-heading class="w-16" align="center"></x-atoms.table-heading>
            </x-slot:headings>

            @forelse ($documents as $doc)
                <x-molecules.table-row :key="$doc->id">
                    <x-atoms.table-cell :nowrap="true">
                        <p class="text-xs font-medium text-zinc-700 dark:text-primary-dark-300">
                            {{ $doc->created_at?->format('d/m/Y') }}</p>
                        <p class="text-[11px] text-zinc-400 dark:text-primary-dark-500">
                            {{ $doc->created_at?->format('H:i:s') }}</p>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        <flux:badge color="{{ $doc->source === 'api' ? 'blue' : 'zinc' }}" size="sm">
                            {{ $doc->source === 'api' ? 'API' : 'Simulasi' }}</flux:badge>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        <flux:badge color="{{ $doc->action === 'sign_pdf' ? 'emerald' : 'violet' }}" size="sm">
                            {{ $doc->action === 'sign_pdf' ? 'Sign PDF' : 'Seal PDF' }}
                        </flux:badge>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell class="hidden md:table-cell">
                        <span class="font-mono text-xs text-zinc-600 dark:text-primary-dark-400">{{ $doc->nik ?? '—' }}</span>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell class="hidden lg:table-cell">
                        <span class="text-xs text-zinc-400 dark:text-primary-dark-500">{{ $doc->getModeLabel() }}</span>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell class="hidden xl:table-cell">
                        @if ($doc->source === 'api')
                            <div class="flex flex-col gap-0.5">
                                <span class="text-xs text-zinc-700 dark:text-primary-dark-300">{{ $doc->api_user_name ?? '—' }}</span>
                                <span class="font-mono text-[11px] text-zinc-400 dark:text-primary-dark-500">{{ $doc->ip_address }}</span>
                            </div>
                        @else
                            <span class="text-zinc-300 dark:text-primary-dark-600">—</span>
                        @endif
                    </x-atoms.table-cell>
                    <x-atoms.table-cell align="center">
                        <span class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-300">{{ $doc->file_count }}</span>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell :action="true" align="center">
                        <x-atoms.button variant="ghost" size="sm" icon="document-magnifying-glass"
                            tooltip="Lihat & verifikasi berkas"
                            wire:click="openDocument('{{ $doc->id }}')" />
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @empty
                <x-molecules.table-row>
                    <x-atoms.table-cell colspan="8" class="py-16 text-center">
                        <div class="flex flex-col items-center gap-3">
                            <div class="flex items-center justify-center w-14 h-14 rounded-2xl bg-zinc-100 dark:bg-primary-dark-700">
                                <flux:icon name="document-text" class="w-7 h-7 text-zinc-300 dark:text-primary-dark-500" />
                            </div>
                            <p class="text-sm text-zinc-400 dark:text-primary-dark-500">
                                {{ $search || $filterSource || $filterAction || $filterDate ? 'Tidak ada berkas yang sesuai filter.' : 'Belum ada dokumen TTE yang tersimpan.' }}
                            </p>
                        </div>
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @endforelse

            @if ($documents->hasPages())
                <x-slot:footer>
                    <div class="px-5 py-4">{{ $documents->links() }}</div>
                </x-slot:footer>
            @endif
        </x-organisms.table>
    @endif

    {{-- ==================== TAB: LAPORAN ==================== --}}
    @if ($activeTab === 'report')
        <div class="flex flex-wrap items-center gap-2 mb-5">
            @foreach ([['value' => 'today', 'label' => 'Hari Ini'], ['value' => '7d', 'label' => '7 Hari'], ['value' => '30d', 'label' => '30 Hari'], ['value' => 'month', 'label' => 'Bulan Ini'], ['value' => 'custom', 'label' => 'Custom']] as $opt)
                <x-atoms.button wire:click="$set('reportRange', '{{ $opt['value'] }}')"
                    variant="{{ $reportRange === $opt['value'] ? 'primary' : 'ghost' }}" size="sm">
                    {{ $opt['label'] }}
                </x-atoms.button>
            @endforeach
            @if ($reportRange === 'custom')
                <div class="flex items-center gap-2 ml-1">
                    <flux:input wire:model.live="reportDateFrom" type="date" class="w-40" />
                    <span class="text-zinc-400 text-sm">—</span>
                    <flux:input wire:model.live="reportDateTo" type="date" class="w-40" />
                </div>
            @endif
        </div>

        @php
            $chartData = [
                'labels' => $reportChartRows->pluck('date')->values(),
                'sign' => $reportChartRows->pluck('sign_count')->values(),
                'seal' => $reportChartRows->pluck('seal_count')->values(),
            ];
        @endphp
        <script type="application/json" id="tte-hit-chart-data">{!! json_encode($chartData) !!}</script>

        {{-- Chart: Hit per Tanggal --}}
        <x-organisms.card title="Jumlah Hit per Tanggal" class="mb-5">
            @if ($reportRows->isNotEmpty())
                <div wire:ignore>
                    <div style="height: 280px"><canvas id="tte-hit-chart"></canvas></div>
                </div>
            @else
                <div class="flex flex-col items-center justify-center py-12 gap-3">
                    <flux:icon name="chart-bar" class="w-10 h-10 text-zinc-300 dark:text-primary-dark-600" />
                    <p class="text-sm text-zinc-400 dark:text-primary-dark-500">Tidak ada data untuk rentang yang dipilih.</p>
                </div>
            @endif
        </x-organisms.card>

        {{-- Tabel: Ringkasan Hit per Tanggal --}}
        @if ($reportRows->isNotEmpty())
            <x-organisms.card title="Ringkasan Hit per Tanggal" :padding="false" class="mb-6">
                <x-organisms.table>
                    <x-slot:headings>
                        <x-atoms.table-heading>Tanggal</x-atoms.table-heading>
                        <x-atoms.table-heading align="right" class="w-28">Sign PDF</x-atoms.table-heading>
                        <x-atoms.table-heading align="right" class="w-28">Seal PDF</x-atoms.table-heading>
                        <x-atoms.table-heading align="right" class="w-28">Total</x-atoms.table-heading>
                    </x-slot:headings>

                    @foreach ($reportRows as $row)
                        <x-molecules.table-row>
                            <x-atoms.table-cell>
                                {{ \Carbon\Carbon::parse($row->date)->isoFormat('D MMM YYYY') }}
                            </x-atoms.table-cell>
                            <x-atoms.table-cell align="right">
                                <span class="font-mono text-sm text-emerald-600 dark:text-emerald-400">{{ number_format($row->sign_count) }}</span>
                            </x-atoms.table-cell>
                            <x-atoms.table-cell align="right">
                                <span class="font-mono text-sm text-violet-600 dark:text-violet-400">{{ number_format($row->seal_count) }}</span>
                            </x-atoms.table-cell>
                            <x-atoms.table-cell align="right">
                                <span class="font-semibold font-mono text-sm text-zinc-700 dark:text-primary-dark-300">{{ number_format($row->total) }}</span>
                            </x-atoms.table-cell>
                        </x-molecules.table-row>
                    @endforeach
                </x-organisms.table>
            </x-organisms.card>
        @endif

        @php
            $empChartData = [
                'labels' => $employeeChartRows->map(fn($r) => $nikMap[$r->nik] ?? $r->nik)->values(),
                'sign' => $employeeChartRows->pluck('sign_count')->map(fn($v) => (int) $v)->values(),
                'seal' => $employeeChartRows->pluck('seal_count')->map(fn($v) => (int) $v)->values(),
            ];
        @endphp
        <script type="application/json" id="tte-employee-chart-data">{!! json_encode($empChartData) !!}</script>

        {{-- Chart: Top Pengguna TTE --}}
        <x-organisms.card title="Top Pengguna TTE" class="mb-5">
            @if ($employeeChartRows->isNotEmpty())
                <div wire:ignore>
                    <div style="height: {{ max($employeeChartRows->count() * 36 + 40, 80) }}px">
                        <canvas id="tte-employee-chart"></canvas>
                    </div>
                </div>
            @else
                <div class="flex flex-col items-center justify-center py-10 gap-3">
                    <flux:icon name="users" class="w-10 h-10 text-zinc-300 dark:text-primary-dark-600" />
                    <p class="text-sm text-zinc-400 dark:text-primary-dark-500">Belum ada data penggunaan TTE per pegawai.</p>
                </div>
            @endif
        </x-organisms.card>

        {{-- Tabel: Penggunaan per Pegawai --}}
        <x-organisms.card :padding="false">
            <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-200 dark:border-primary-dark-700">
                <h3 class="text-base font-semibold text-zinc-900 dark:text-primary-dark-100">Penggunaan per Pegawai</h3>
                <div class="w-56">
                    <flux:input wire:model.live.debounce.300ms="employeeSearch" placeholder="Cari NIK / nama…"
                        icon="magnifying-glass" clearable size="sm" />
                </div>
            </div>
            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading>NIK</x-atoms.table-heading>
                    <x-atoms.table-heading class="hidden md:table-cell">Nama Pegawai</x-atoms.table-heading>
                    <x-atoms.table-heading align="right" class="w-28">Sign PDF</x-atoms.table-heading>
                    <x-atoms.table-heading align="right" class="w-28">Seal PDF</x-atoms.table-heading>
                    <x-atoms.table-heading align="right" class="w-24">Total</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-36 hidden lg:table-cell">Terakhir</x-atoms.table-heading>
                </x-slot:headings>

                @forelse ($employeeRows as $row)
                    @php $nama = $nikMap[$row->nik] ?? null; @endphp
                    <x-molecules.table-row>
                        <x-atoms.table-cell>
                            <span class="font-mono text-xs text-zinc-700 dark:text-primary-dark-300">{{ $row->nik }}</span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell class="hidden md:table-cell">
                            @if ($nama)
                                <span class="text-sm text-zinc-700 dark:text-primary-dark-300">{{ $nama }}</span>
                            @else
                                <span class="text-sm text-zinc-400 dark:text-primary-dark-500 italic">(tidak ditemukan)</span>
                            @endif
                        </x-atoms.table-cell>
                        <x-atoms.table-cell align="right">
                            <span class="font-mono text-sm text-emerald-600 dark:text-emerald-400">{{ number_format($row->sign_count) }}</span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell align="right">
                            <span class="font-mono text-sm text-violet-600 dark:text-violet-400">{{ number_format($row->seal_count) }}</span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell align="right">
                            <span class="font-semibold font-mono text-sm text-zinc-700 dark:text-primary-dark-300">{{ number_format($row->total) }}</span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell class="hidden lg:table-cell">
                            <span class="text-xs text-zinc-400 dark:text-primary-dark-500">{{ $row->last_used ? \Carbon\Carbon::parse($row->last_used)->format('d/m/Y H:i') : '—' }}</span>
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @empty
                    <x-molecules.table-row>
                        <x-atoms.table-cell colspan="6" class="py-10 text-center">
                            <p class="text-sm text-zinc-400 dark:text-primary-dark-500">
                                {{ $employeeSearch ? 'Tidak ada pegawai yang sesuai pencarian.' : 'Belum ada data penggunaan TTE per pegawai.' }}
                            </p>
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @endforelse
            </x-organisms.table>
        </x-organisms.card>
    @endif

    {{-- ==================== MODAL: BERKAS + VERIFIKASI ==================== --}}
    <x-organisms.modal name="tte-document" @close="$wire.set('documentId', null); $wire.set('verifyResult', null)" maxWidth="6xl" title="">
        @if ($activeDoc)
            @php $fileList = $activeDoc->signed_files ?? []; @endphp
            <div class="flex flex-col gap-0" style="max-height: 85vh">

                {{-- Header --}}
                <div class="flex items-center gap-3 pb-4 mb-4 border-b border-zinc-200 dark:border-primary-dark-700 shrink-0">
                    <div class="flex items-center justify-center w-9 h-9 rounded-full bg-blue-100 dark:bg-blue-900/30 shrink-0">
                        <flux:icon name="document-magnifying-glass" class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-base font-semibold text-zinc-900 dark:text-primary-dark-100">Berkas TTE</h3>
                        <p class="text-xs text-zinc-400 dark:text-primary-dark-500">
                            {{ $activeDoc->action_label }} · {{ $activeDoc->created_at?->format('d/m/Y H:i:s') }}
                            @if ($activeDoc->nik)
                                · NIK {{ $activeDoc->nik }}
                            @endif
                            · {{ count($fileList) }} berkas
                        </p>
                    </div>
                </div>

                @if (empty($fileList))
                    <div class="flex flex-col items-center justify-center py-16 gap-3">
                        <flux:icon name="document-text" class="w-10 h-10 text-zinc-300 dark:text-primary-dark-600" />
                        <p class="text-sm text-zinc-400 dark:text-primary-dark-500">Tidak ada berkas tersimpan untuk dokumen ini.</p>
                    </div>
                @else
                    <div x-data="{ active: 0 }" class="flex flex-col min-h-0 flex-1 gap-3">
                        @if (count($fileList) > 1)
                            <div class="flex flex-wrap gap-2 shrink-0">
                                @foreach ($fileList as $fi => $_)
                                    <button
                                        x-on:click="active = {{ $fi }}; $wire.selectVerifyFile({{ $fi }})"
                                        x-bind:class="active === {{ $fi }}
                                            ? 'bg-primary-600 text-white border-primary-600'
                                            : 'bg-white dark:bg-primary-dark-800 text-zinc-600 dark:text-primary-dark-400 border-zinc-200 dark:border-primary-dark-700 hover:border-zinc-400'"
                                        class="px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors">
                                        Berkas {{ $fi + 1 }}
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        <div class="grid grid-cols-1 lg:grid-cols-5 gap-4 min-h-0 flex-1">
                            {{-- Kiri: Preview PDF --}}
                            <div class="lg:col-span-3 flex flex-col gap-2">
                                @foreach ($fileList as $fi => $_)
                                    @php
                                        $previewUrl = route('tte.signed-file', [$activeDoc->id, $fi]);
                                        $downloadUrl = route('tte.signed-file', [$activeDoc->id, $fi]) . '?dl=1';
                                    @endphp
                                    <div x-show="active === {{ $fi }}" x-cloak class="flex flex-col gap-2 h-full">
                                        <div class="flex items-center justify-between shrink-0">
                                            <p class="text-xs text-zinc-400 dark:text-primary-dark-500 font-mono">Berkas {{ $fi + 1 }} / {{ count($fileList) }}</p>
                                            <div class="flex items-center gap-2">
                                                <a href="{{ $previewUrl }}" target="_blank"
                                                    class="flex items-center gap-1.5 text-xs font-medium text-zinc-500 dark:text-primary-dark-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors">
                                                    <flux:icon name="arrow-top-right-on-square" class="w-3.5 h-3.5" />
                                                    Tab baru
                                                </a>
                                                <a href="{{ $downloadUrl }}"
                                                    class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-primary-600 hover:bg-primary-700 text-white transition-colors">
                                                    <flux:icon name="arrow-down-tray" class="w-3.5 h-3.5" />Unduh
                                                </a>
                                            </div>
                                        </div>
                                        <div wire:ignore
                                            class="rounded-xl overflow-hidden border border-zinc-200 dark:border-primary-dark-700 bg-zinc-100 dark:bg-primary-dark-900"
                                            style="height: 560px">
                                            <iframe src="{{ $previewUrl }}" class="w-full h-full"
                                                title="Preview berkas {{ $fi + 1 }}" loading="lazy"></iframe>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            {{-- Kanan: Verifikasi --}}
                            <div class="lg:col-span-2 flex flex-col gap-3 overflow-y-auto" style="max-height: 600px">
                                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500 shrink-0">
                                    Verifikasi Berkas
                                    {{ count($fileList) > 1 ? '(Berkas ' . ($verifyFileIndex + 1) . ')' : '' }}
                                </p>

                                {{-- Metadata --}}
                                @php
                                    $metaRows = array_filter(
                                        [
                                            ['label' => 'Aksi', 'value' => $activeDoc->action_label],
                                            ['label' => 'Mode', 'value' => $activeDoc->getModeLabel()],
                                            [
                                                'label' => 'Sumber',
                                                'value' => $activeDoc->source === 'api'
                                                    ? 'API — ' . ($activeDoc->api_user_name ?? '?')
                                                    : 'Simulasi',
                                            ],
                                            [
                                                'label' => 'Resp.',
                                                'value' => $activeDoc->response_time_ms
                                                    ? number_format($activeDoc->response_time_ms) . ' ms'
                                                    : null,
                                            ],
                                        ],
                                        fn($r) => !is_null($r['value']),
                                    );
                                @endphp
                                <div class="grid grid-cols-2 gap-1.5 shrink-0">
                                    @foreach ($metaRows as $meta)
                                        <div class="flex flex-col gap-0.5 p-2.5 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                                            <span class="text-[10px] font-semibold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500">{{ $meta['label'] }}</span>
                                            <span class="text-xs font-medium text-zinc-700 dark:text-primary-dark-300 truncate">{{ $meta['value'] }}</span>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="border-t border-zinc-100 dark:border-primary-dark-700/60 shrink-0"></div>

                                @if ($verifyError)
                                    <div class="p-3 rounded-xl bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-800/40 shrink-0">
                                        <p class="text-sm text-red-700 dark:text-red-300">{{ $verifyError }}</p>
                                    </div>
                                @endif

                                @if (!$verifyResult && !$verifyError)
                                    <div class="flex justify-center py-6 shrink-0">
                                        <x-atoms.button wire:click="runVerification" variant="primary" icon="shield-check">
                                            <span wire:loading.remove wire:target="runVerification">Jalankan Verifikasi</span>
                                            <span wire:loading wire:target="runVerification">Memverifikasi…</span>
                                        </x-atoms.button>
                                    </div>
                                @endif

                                @if ($verifyResult)
                                    @php
                                        $vData = $verifyResult['data'] ?? [];
                                        $conclusion = strtoupper($vData['conclusion'] ?? '');
                                        $sigCount = $vData['signatureCount'] ?? 0;
                                        $signatures = $vData['signatureInformations'] ?? [];
                                        $cStyle = match ($conclusion) {
                                            'VALID' => [
                                                'bg' => 'bg-emerald-50 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-800/40',
                                                'icon' => 'check-circle',
                                                'ic' => 'text-emerald-600 dark:text-emerald-400',
                                                'tx' => 'text-emerald-700 dark:text-emerald-400',
                                                'badge' => 'emerald',
                                            ],
                                            'WARNING' => [
                                                'bg' => 'bg-amber-50 dark:bg-amber-950/20 border-amber-200 dark:border-amber-800/40',
                                                'icon' => 'exclamation-triangle',
                                                'ic' => 'text-amber-600 dark:text-amber-400',
                                                'tx' => 'text-amber-700 dark:text-amber-400',
                                                'badge' => 'amber',
                                            ],
                                            default => [
                                                'bg' => 'bg-red-50 dark:bg-red-950/20 border-red-200 dark:border-red-800/40',
                                                'icon' => 'x-circle',
                                                'ic' => 'text-red-600 dark:text-red-400',
                                                'tx' => 'text-red-700 dark:text-red-400',
                                                'badge' => 'red',
                                            ],
                                        };
                                    @endphp

                                    <div x-data="{ showFull: false }" class="space-y-3">
                                        <div class="flex items-start gap-2.5 p-3 rounded-xl border {{ $cStyle['bg'] }}">
                                            <flux:icon name="{{ $cStyle['icon'] }}" class="w-4 h-4 shrink-0 mt-0.5 {{ $cStyle['ic'] }}" />
                                            <div class="flex-1 min-w-0">
                                                <div class="flex flex-wrap items-center gap-1.5 mb-1">
                                                    <span class="font-semibold text-sm {{ $cStyle['tx'] }}">{{ $conclusion ?: 'Tidak Diketahui' }}</span>
                                                    <flux:badge color="{{ $cStyle['badge'] }}" size="sm">{{ $sigCount }} ttd</flux:badge>
                                                </div>
                                                @if (!empty($vData['description']))
                                                    <p class="text-xs {{ $cStyle['tx'] }} opacity-80">{{ $vData['description'] }}</p>
                                                @endif
                                            </div>
                                        </div>

                                        @foreach ($signatures as $i => $sig)
                                            @php $ts = $sig['timestampInfomation'] ?? null; @endphp
                                            <div class="rounded-xl border border-zinc-200 dark:border-primary-dark-700 overflow-hidden text-xs">
                                                <div class="flex flex-wrap items-center gap-1.5 px-3 py-2 bg-zinc-50 dark:bg-primary-dark-900/40 border-b border-zinc-100 dark:border-primary-dark-700/60">
                                                    <span class="font-semibold text-zinc-400 dark:text-primary-dark-500">#{{ $i + 1 }}</span>
                                                    <span class="font-medium text-zinc-800 dark:text-primary-dark-200 truncate flex-1">{{ $sig['signerName'] ?? '—' }}</span>
                                                    @if (isset($sig['integrityValid']))
                                                        <flux:badge color="{{ $sig['integrityValid'] ? 'emerald' : 'red' }}" size="sm">
                                                            {{ $sig['integrityValid'] ? 'Terjamin' : 'Tidak Terjamin' }}
                                                        </flux:badge>
                                                    @endif
                                                    @if (isset($sig['certificateTrusted']))
                                                        <flux:badge color="{{ $sig['certificateTrusted'] ? 'emerald' : 'amber' }}" size="sm">
                                                            {{ $sig['certificateTrusted'] ? 'Terpercaya' : 'Tidak Terpercaya' }}
                                                        </flux:badge>
                                                    @endif
                                                </div>
                                                <div class="p-3 space-y-1 bg-white dark:bg-primary-dark-800">
                                                    @if (!empty($sig['signatureDate']))
                                                        <div class="flex gap-1.5">
                                                            <span class="text-zinc-400 shrink-0 w-14">Tanggal</span>
                                                            <span class="text-zinc-700 dark:text-primary-dark-300 font-mono">{{ \Carbon\Carbon::parse($sig['signatureDate'])->setTimezone(config('app.timezone'))->format('d/m/Y H:i:s') }}</span>
                                                        </div>
                                                    @endif
                                                    @if (!empty($sig['location']))
                                                        <div class="flex gap-1.5">
                                                            <span class="text-zinc-400 shrink-0 w-14">Lokasi</span>
                                                            <span class="text-zinc-700 dark:text-primary-dark-300">{{ $sig['location'] }}</span>
                                                        </div>
                                                    @endif
                                                    @if (!empty($sig['reason']))
                                                        <div class="flex gap-1.5">
                                                            <span class="text-zinc-400 shrink-0 w-14">Alasan</span>
                                                            <span class="text-zinc-700 dark:text-primary-dark-300">{{ $sig['reason'] }}</span>
                                                        </div>
                                                    @endif
                                                    @if (isset($sig['ltv']))
                                                        <div class="flex gap-1.5">
                                                            <span class="text-zinc-400 shrink-0 w-14">LTV</span>
                                                            <span class="text-zinc-700 dark:text-primary-dark-300">{{ $sig['ltv'] ? 'Ya' : 'Tidak' }}</span>
                                                        </div>
                                                    @endif
                                                    @if ($ts)
                                                        <div class="flex gap-1.5 pt-1 border-t border-zinc-100 dark:border-primary-dark-700/40">
                                                            <flux:icon name="clock" class="w-3.5 h-3.5 text-zinc-400 shrink-0 mt-0.5" />
                                                            <div>
                                                                <span class="text-zinc-400 dark:text-primary-dark-500">{{ $ts['signerName'] ?? '—' }}</span>
                                                                @if (!empty($ts['timestampDate']))
                                                                    <span class="text-zinc-400 font-mono ml-1">{{ \Carbon\Carbon::parse($ts['timestampDate'])->setTimezone(config('app.timezone'))->format('d/m/Y H:i:s') }}</span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach

                                        <div>
                                            <x-atoms.button x-on:click="showFull = !showFull"
                                                class="flex items-center gap-1.5 text-xs font-medium text-primary-600 dark:text-primary-400 hover:underline">
                                                <flux:icon name="code-bracket" class="w-3.5 h-3.5" />
                                                <span x-text="showFull ? 'Sembunyikan JSON' : 'Lihat JSON lengkap'"></span>
                                            </x-atoms.button>
                                            <div x-show="showFull" x-collapse class="mt-2">
                                                <x-atoms.code-block language="json" maxHeight="max-h-48">{{ json_encode($vData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</x-atoms.code-block>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                <div class="flex justify-end pt-4 mt-2 border-t border-zinc-200 dark:border-primary-dark-700 shrink-0">
                    <x-atoms.button size="sm" variant="ghost" x-on:click="$flux.modal('tte-document').close()">
                        Tutup</x-atoms.button>
                </div>
            </div>
        @endif

    </x-organisms.modal>
</div>

@pushOnce('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
    <script>
        function destroyTteHitChart() {
            const existing = Chart.getChart('tte-hit-chart');
            if (existing) existing.destroy();
        }

        function initTteHitChart() {
            const dataEl = document.getElementById('tte-hit-chart-data');
            const canvas = document.getElementById('tte-hit-chart');
            if (!dataEl || !canvas) return;

            const d = JSON.parse(dataEl.textContent);
            const isDark = document.documentElement.classList.contains('dark');
            const grid = isDark ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.06)';
            const tick = isDark ? '#a1a1aa' : '#71717a';

            destroyTteHitChart();

            new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: d.labels,
                    datasets: [{
                            label: 'Sign PDF',
                            data: d.sign,
                            backgroundColor: 'rgba(16,185,129,0.75)',
                            borderRadius: 4,
                            borderSkipped: false
                        },
                        {
                            label: 'Seal PDF',
                            data: d.seal,
                            backgroundColor: 'rgba(139,92,246,0.75)',
                            borderRadius: 4,
                            borderSkipped: false
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { labels: { color: tick, boxWidth: 12 } } },
                    scales: {
                        x: { stacked: true, ticks: { color: tick, maxRotation: 45 }, grid: { color: grid } },
                        y: { stacked: true, beginAtZero: true, ticks: { color: tick, precision: 0 }, grid: { color: grid } },
                    },
                },
            });
        }

        function destroyTteEmployeeChart() {
            const existing = Chart.getChart('tte-employee-chart');
            if (existing) existing.destroy();
        }

        function initTteEmployeeChart() {
            const dataEl = document.getElementById('tte-employee-chart-data');
            const canvas = document.getElementById('tte-employee-chart');
            if (!dataEl || !canvas) return;

            const d = JSON.parse(dataEl.textContent);
            const isDark = document.documentElement.classList.contains('dark');
            const grid = isDark ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.06)';
            const tick = isDark ? '#a1a1aa' : '#71717a';
            destroyTteEmployeeChart();

            new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: d.labels,
                    datasets: [{
                            label: 'Sign PDF',
                            data: d.sign,
                            backgroundColor: 'rgba(16,185,129,0.75)',
                            borderRadius: 4,
                            borderSkipped: false
                        },
                        {
                            label: 'Seal PDF',
                            data: d.seal,
                            backgroundColor: 'rgba(139,92,246,0.75)',
                            borderRadius: 4,
                            borderSkipped: false
                        },
                    ],
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { labels: { color: tick, boxWidth: 12 } } },
                    scales: {
                        x: { stacked: true, beginAtZero: true, ticks: { color: tick, precision: 0 }, grid: { color: grid } },
                        y: { stacked: true, ticks: { color: tick }, grid: { color: grid } },
                    },
                },
            });
        }

        function initAllTteCharts() {
            if (document.getElementById('tte-hit-chart-data')) {
                destroyTteHitChart();
                initTteHitChart();
            }
            if (document.getElementById('tte-employee-chart-data')) {
                destroyTteEmployeeChart();
                initTteEmployeeChart();
            }
        }

        function rafInitAllTteCharts() {
            requestAnimationFrame(initAllTteCharts);
        }

        document.addEventListener('DOMContentLoaded', rafInitAllTteCharts);
        document.addEventListener('livewire:navigated', rafInitAllTteCharts);
        document.addEventListener('tte-refresh-charts', rafInitAllTteCharts);
    </script>
@endPushOnce
