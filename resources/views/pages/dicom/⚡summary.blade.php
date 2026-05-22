<?php

use App\Models\Dicom\DicomModality;
use App\Models\Dicom\DicomRouter;
use App\Models\Dicom\DicomStudy;
use App\Services\Dicom\OrthancService;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new #[Layout('layouts::app')] #[Title('DICOM — Ringkasan')] class extends Component {

    public function with(): array
    {
        // Statistik lokal
        $modalityTotal  = DicomModality::count();
        $modalityActive = DicomModality::active()->count();
        $routerTotal    = DicomRouter::count();
        $routerActive   = DicomRouter::active()->count();

        $studyTotal    = DicomStudy::count();
        $studyPending  = DicomStudy::where('status', 'pending')->count();
        $studyReceived = DicomStudy::where('status', 'received')->count();
        $studySent     = DicomStudy::where('status', 'sent')->count();
        $studyError    = DicomStudy::where('status', 'error')->count();

        // Studi per modality (lokal)
        $studyByModality = DicomStudy::selectRaw('modality, count(*) as total')
            ->whereNotNull('modality')
            ->groupBy('modality')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // Studi 7 hari terakhir
        $studyTrend = DicomStudy::selectRaw('DATE(created_at) as date, count(*) as total')
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $trendDates  = [];
        $trendTotals = [];
        for ($i = 6; $i >= 0; $i--) {
            $date          = now()->subDays($i)->format('Y-m-d');
            $trendDates[]  = now()->subDays($i)->format('d/m');
            $trendTotals[] = $studyTrend[$date]->total ?? 0;
        }

        // Koneksi Orthanc
        $orthanc      = app(OrthancService::class);
        $orthancInfo  = null;
        $orthancError = null;

        if ($orthanc->isConfigured()) {
            $sysResult   = $orthanc->getSystemInfo();
            $statsResult = $orthanc->getStatistics();

            if ($sysResult['success'] && is_array($sysResult['data'])) {
                $orthancInfo = $sysResult['data'];
                if ($statsResult['success'] && is_array($statsResult['data'])) {
                    $orthancInfo = array_merge($orthancInfo, $statsResult['data']);
                }
            } else {
                $orthancError = $sysResult['message'];
            }
        } else {
            $orthancError = 'Orthanc belum dikonfigurasi.';
        }

        // Modality terdaftar di Orthanc
        $orthancAes = [];
        if ($orthanc->isConfigured()) {
            $modsResult = $orthanc->getModalities();
            if ($modsResult['success'] && is_array($modsResult['data'])) {
                $orthancAes = $modsResult['data'];
            }
        }

        $registeredInOrthanc = DicomModality::whereIn('ae_title', $orthancAes)->count();

        // Studi terbaru
        $recentStudies = DicomStudy::orderByDesc('created_at')->limit(5)->get();

        return compact(
            'modalityTotal',
            'modalityActive',
            'routerTotal',
            'routerActive',
            'studyTotal',
            'studyPending',
            'studyReceived',
            'studySent',
            'studyError',
            'studyByModality',
            'trendDates',
            'trendTotals',
            'orthancInfo',
            'orthancError',
            'registeredInOrthanc',
            'recentStudies'
        );
    }
}; ?>

<div>
    <x-ui.page-header title="Ringkasan DICOM / PACS"
        subtitle="Status sistem DICOM, Orthanc, modality, dan studi." />

    {{-- Status Orthanc --}}
    <div class="mb-6">
        @if ($orthancInfo)
        <div class="rounded-lg border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20 p-4 flex items-start gap-3">
            <div class="mt-0.5 flex-shrink-0 w-5 h-5 rounded-full bg-green-500 flex items-center justify-center">
                <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-green-800 dark:text-green-300">Orthanc Terhubung</p>
                <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-green-700 dark:text-green-400">
                    <span>Versi: <strong>{{ $orthancInfo['Version'] ?? '-' }}</strong></span>
                    <span>Storage: <strong>{{ $orthancInfo['TotalDiskSizeMB'] ?? '?' }} MB</strong></span>
                    <span>Studi di Orthanc: <strong>{{ $orthancInfo['CountStudies'] ?? '?' }}</strong></span>
                    <span>Series: <strong>{{ $orthancInfo['CountSeries'] ?? '?' }}</strong></span>
                    <span>Instance: <strong>{{ $orthancInfo['CountInstances'] ?? '?' }}</strong></span>
                </div>
            </div>
        </div>
        @else
        <div class="rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 p-4 flex items-start gap-3">
            <div class="mt-0.5 flex-shrink-0 w-5 h-5 rounded-full bg-red-500 flex items-center justify-center">
                <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-red-800 dark:text-red-300">Orthanc Tidak Terhubung</p>
                <p class="text-xs text-red-600 dark:text-red-400 mt-0.5">{{ $orthancError }}</p>
                <a href="{{ route('configuration.connectivity', ['tab' => 'dicom']) }}" wire:navigate
                    class="text-xs text-red-700 dark:text-red-400 underline mt-1 inline-block">
                    Buka Konfigurasi
                </a>
            </div>
        </div>
        @endif
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <x-organisms.stat-card title="Modality Aktif"
            :value="$modalityActive . ' / ' . $modalityTotal"
            icon="computer-desktop" color="primary" />

        <x-organisms.stat-card title="Terdaftar di Orthanc"
            :value="$registeredInOrthanc . ' / ' . $modalityTotal"
            icon="check-circle" color="success" />

        <x-organisms.stat-card title="Router Aktif"
            :value="$routerActive . ' / ' . $routerTotal"
            icon="arrows-right-left" color="info" />

        <x-organisms.stat-card title="Total Studi"
            :value="$studyTotal"
            icon="photo" color="secondary" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Status studi --}}
        <x-organisms.card title="Status Studi (Lokal)">
            <div class="space-y-3">
                @foreach ([
                'received' => ['label' => 'Diterima dari Orthanc', 'value' => $studyReceived, 'color' => 'bg-blue-500'],
                'sent' => ['label' => 'Terkirim ke Router', 'value' => $studySent, 'color' => 'bg-green-500'],
                'pending' => ['label' => 'Menunggu', 'value' => $studyPending, 'color' => 'bg-yellow-500'],
                'error' => ['label' => 'Error', 'value' => $studyError, 'color' => 'bg-red-500'],
                ] as $status => $item)
                @if ($studyTotal > 0)
                @php $pct = round($item['value'] / $studyTotal * 100); @endphp
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-zinc-600 dark:text-primary-dark-300">{{ $item['label'] }}</span>
                        <span class="font-semibold text-zinc-900 dark:text-primary-dark-100">
                            {{ number_format($item['value']) }}
                            <span class="font-normal text-zinc-400">({{ $pct }}%)</span>
                        </span>
                    </div>
                    <div class="h-2 rounded-full bg-zinc-100 dark:bg-primary-dark-700 overflow-hidden">
                        <div class="{{ $item['color'] }} h-full rounded-full" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
                @else
                <div class="flex justify-between text-sm">
                    <span class="text-zinc-600 dark:text-primary-dark-300">{{ $item['label'] }}</span>
                    <span class="font-semibold text-zinc-900 dark:text-primary-dark-100">{{ $item['value'] }}</span>
                </div>
                @endif
                @endforeach

                @if ($studyTotal === 0)
                <p class="text-sm text-zinc-400 dark:text-primary-dark-500 text-center py-4">Belum ada studi tercatat.</p>
                @endif
            </div>
        </x-organisms.card>

        {{-- Studi per modality --}}
        <x-organisms.card title="Studi per Modality">
            @if ($studyByModality->isEmpty())
            <p class="text-sm text-zinc-400 dark:text-primary-dark-500 text-center py-4">Belum ada data.</p>
            @else
            <div class="space-y-2">
                @foreach ($studyByModality as $row)
                @php $pct = $studyTotal > 0 ? round($row->total / $studyTotal * 100) : 0; @endphp
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="font-mono font-semibold text-zinc-800 dark:text-primary-dark-100">{{ $row->modality }}</span>
                        <span class="text-zinc-600 dark:text-primary-dark-300">
                            {{ number_format($row->total) }}
                            <span class="text-zinc-400">({{ $pct }}%)</span>
                        </span>
                    </div>
                    <div class="h-1.5 rounded-full bg-zinc-100 dark:bg-primary-dark-700 overflow-hidden">
                        <div class="bg-blue-500 h-full rounded-full" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </x-organisms.card>

        {{-- Tren 7 hari --}}
        <x-organisms.card title="Studi Masuk (7 Hari Terakhir)" class="lg:col-span-2">
            <div wire:ignore>
                <canvas id="dicom-trend-chart" height="80"></canvas>
            </div>
        </x-organisms.card>

        {{-- Studi Terbaru --}}
        <x-organisms.card title="Studi Terbaru" class="lg:col-span-2">
            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading>Waktu</x-atoms.table-heading>
                    <x-atoms.table-heading>Pasien / Order</x-atoms.table-heading>
                    <x-atoms.table-heading>Modality</x-atoms.table-heading>
                    <x-atoms.table-heading>Status</x-atoms.table-heading>
                </x-slot:headings>
                @forelse ($recentStudies as $s)
                    <x-molecules.table-row>
                        <x-atoms.table-cell>
                            <span class="text-xs text-zinc-500">{{ $s->created_at->format('d/m H:i') }}</span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            <p class="text-sm font-bold text-zinc-900">{{ $s->patient_id }}</p>
                            <p class="text-[10px] text-zinc-500 font-mono">{{ $s->noorder }}</p>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            <flux:badge size="xs" variant="outline">{{ $s->modality }}</flux:badge>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            @php
                                $color = match($s->status) {
                                    'sent'     => 'green',
                                    'received' => 'blue',
                                    'pending'  => 'yellow',
                                    'error'    => 'red',
                                    default    => 'zinc'
                                };
                            @endphp
                            <flux:badge size="xs" :color="$color">{{ strtoupper($s->status) }}</flux:badge>
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @empty
                    <x-molecules.table-row>
                        <x-atoms.table-cell colspan="4" align="center" class="py-8 text-zinc-400">
                            Belum ada aktivitas studi.
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @endforelse
            </x-organisms.table>
        </x-organisms.card>

    </div>
</div>

@pushOnce('scripts')
<script>
    function initDicomTrendChart() {
        const ctx = document.getElementById('dicom-trend-chart');
        if (!ctx) return;

        if (window._dicomTrendChart) {
            window._dicomTrendChart.destroy();
        }

        const isDark = document.documentElement.classList.contains('dark');
        const gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)';
        const labelColor = isDark ? '#94a3b8' : '#64748b';

        window._dicomTrendChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: @json($trendDates),
                datasets: [{
                    label: 'Studi',
                    data: @json($trendTotals),
                    backgroundColor: 'rgba(59,130,246,0.7)',
                    borderRadius: 4,
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: gridColor
                        },
                        ticks: {
                            color: labelColor
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: gridColor
                        },
                        ticks: {
                            color: labelColor,
                            stepSize: 1,
                            precision: 0
                        }
                    }
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', initDicomTrendChart);
    document.addEventListener('livewire:navigated', initDicomTrendChart);
</script>
@endPushOnce