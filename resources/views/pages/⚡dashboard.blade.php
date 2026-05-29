<?php

use App\Models\Bpjs\BpjsErm;
use App\Models\Dicom\DicomStudy;
use App\Models\Mapping\AllergyMap;
use App\Models\Mapping\EmployeeMap;
use App\Models\Mapping\Icd10Map;
use App\Models\Mapping\Icd9Map;
use App\Models\Mapping\LabMap;
use App\Models\Mapping\MedicationMap;
use App\Models\Mapping\ProcedureMap;
use App\Models\Mapping\RadMap;
use App\Models\SatuSehat\SatuSehatEncounter;
use App\Models\SatuSehat\SatuSehatLog;
use App\Models\SatuSehat\SatuSehatPatient;
use App\Models\SatuSehat\SatuSehatPractitioner;
use App\Models\Terminology\Icd10;
use App\Models\Terminology\Icd9;
use App\Models\TteDocument;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new #[Layout('layouts::app')] #[Title('Dashboard')] class extends Component {
    public function with(): array
    {
        // --- Stat cards ---
        $encounterTotal    = SatuSehatEncounter::count();
        $encounterHariIni  = SatuSehatEncounter::whereDate('created_at', today())->count();
        $bpjsErmTotal      = BpjsErm::count();
        $bpjsErmBulanIni   = BpjsErm::whereYear('sent_at', now()->year)->whereMonth('sent_at', now()->month)->count();
        $practitionerTotal = SatuSehatPractitioner::count();
        $patientTotal      = SatuSehatPatient::count();
        $tteTotal          = TteDocument::count();
        $tteHariIni        = TteDocument::whereDate('created_at', today())->count();

        // --- Tren Encounter SS — 14 hari ---
        $encounterRaw = SatuSehatEncounter::selectRaw('DATE(created_at) as date, count(*) as total')
            ->where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->groupBy('date')->pluck('total', 'date');

        $trendLabels = $trendValues = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = now()->subDays($i);
            $trendLabels[] = $d->format('d M');
            $trendValues[] = (int) ($encounterRaw[$d->format('Y-m-d')] ?? 0);
        }

        // --- Tren TTE — 14 hari ---
        $tteRaw = TteDocument::selectRaw('DATE(created_at) as date, count(*) as total')
            ->where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->groupBy('date')->pluck('total', 'date');

        $tteTrendLabels = $tteTrendValues = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = now()->subDays($i);
            $tteTrendLabels[] = $d->format('d M');
            $tteTrendValues[] = (int) ($tteRaw[$d->format('Y-m-d')] ?? 0);
        }

        // --- eRM BPJS per bulan — 6 bulan ---
        $ermRaw = BpjsErm::selectRaw("DATE_FORMAT(sent_at, '%Y-%m') as month, encounter_type, count(*) as total")
            ->where('sent_at', '>=', now()->subMonths(5)->startOfMonth())
            ->whereNotNull('sent_at')->groupBy('month', 'encounter_type')
            ->get()->groupBy('month');

        $ermLabels = $ermAmb = $ermEmer = $ermImp = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = now()->subMonths($i);
            $key = $m->format('Y-m');
            $ermLabels[] = $m->format('M Y');
            $byType = $ermRaw->get($key, collect())->keyBy('encounter_type');
            $ermAmb[]  = (int) ($byType['AMB']?->total  ?? 0);
            $ermEmer[] = (int) ($byType['EMER']?->total ?? 0);
            $ermImp[]  = (int) ($byType['IMP']?->total  ?? 0);
        }

        // --- Distribusi eRM (donut) ---
        $distribusi = BpjsErm::selectRaw('encounter_type, count(*) as total')
            ->whereNotNull('encounter_type')->groupBy('encounter_type')
            ->pluck('total', 'encounter_type');

        // --- Local Terminology coverage ---
        $icd10Total   = Icd10::count();
        $icd10Mapped  = Icd10Map::count();
        $icd9Total    = Icd9::count();
        $icd9Mapped   = Icd9Map::count();
        $procMapped   = ProcedureMap::count();
        $labMapped    = LabMap::count();
        $radMapped    = RadMap::count();
        $medMapped    = MedicationMap::count();
        $allergyMapped = AllergyMap::count();
        $empMapped    = EmployeeMap::count();

        // --- DICOM ---
        $dicomTotal   = DicomStudy::count();
        $dicomHariIni = DicomStudy::whereDate('created_at', today())->count();
        $dicomSent    = DicomStudy::where('status', 'sent')->count();
        $dicomPending = DicomStudy::where('status', 'pending')->count();
        $dicomError   = DicomStudy::where('status', 'error')->count();

        $dicomRaw = DicomStudy::selectRaw('DATE(created_at) as date, count(*) as total')
            ->where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->groupBy('date')->pluck('total', 'date');

        $dicomTrendLabels = $dicomTrendValues = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = now()->subDays($i);
            $dicomTrendLabels[] = $d->format('d M');
            $dicomTrendValues[] = (int) ($dicomRaw[$d->format('Y-m-d')] ?? 0);
        }

        $dicomByModality = DicomStudy::selectRaw('modality, count(*) as total')
            ->whereNotNull('modality')
            ->groupBy('modality')
            ->orderByDesc('total')
            ->limit(6)
            ->pluck('total', 'modality');

        // --- Job queue ---
        $pendingJobs = $failedJobs = 0;
        try {
            $pendingJobs = DB::table('jobs')->count();
            $failedJobs  = DB::table('failed_jobs')->count();
        } catch (\Throwable) {}

        // --- Aktivitas terbaru SS ---
        $recentActivity = SatuSehatLog::orderByDesc('created_at')->limit(8)->get();

        return [
            'encounterTotal'    => $encounterTotal,
            'encounterHariIni'  => $encounterHariIni,
            'bpjsErmTotal'      => $bpjsErmTotal,
            'bpjsErmBulanIni'   => $bpjsErmBulanIni,
            'practitionerTotal' => $practitionerTotal,
            'patientTotal'      => $patientTotal,
            'tteTotal'          => $tteTotal,
            'tteHariIni'        => $tteHariIni,
            'trendLabels'       => $trendLabels,
            'trendValues'       => $trendValues,
            'tteTrendLabels'    => $tteTrendLabels,
            'tteTrendValues'    => $tteTrendValues,
            'ermLabels'         => $ermLabels,
            'ermAmb'            => $ermAmb,
            'ermEmer'           => $ermEmer,
            'ermImp'            => $ermImp,
            'distribusi'        => $distribusi,
            'localTerminology'  => [
                ['label' => 'ICD-10',      'mapped' => $icd10Mapped,  'total' => $icd10Total,        'color' => 'violet', 'route' => 'local.source.icd10',            'permission' => 'local.source'],
                ['label' => 'ICD-9CM',     'mapped' => $icd9Mapped,   'total' => $icd9Total,         'color' => 'violet', 'route' => 'local.source.icd9',             'permission' => 'local.source'],
                ['label' => 'Practitioner','mapped' => $empMapped,    'total' => $practitionerTotal,  'color' => 'sky',    'route' => 'local.practitioner',            'permission' => 'local.practitioner'],
                ['label' => 'Prosedur',    'mapped' => $procMapped,   'total' => null,               'color' => 'amber',  'route' => 'local.clinical.procedure',      'permission' => 'local.clinical'],
                ['label' => 'Lab',         'mapped' => $labMapped,    'total' => null,               'color' => 'emerald','route' => 'local.observation.laboratory',  'permission' => 'local.observation'],
                ['label' => 'Radiologi',   'mapped' => $radMapped,    'total' => null,               'color' => 'sky',    'route' => 'local.observation.radiology',   'permission' => 'local.observation'],
                ['label' => 'Medication',  'mapped' => $medMapped,    'total' => null,               'color' => 'emerald','route' => 'local.medication.medicine',     'permission' => 'local.medication'],
                ['label' => 'Alergi',      'mapped' => $allergyMapped,'total' => null,               'color' => 'red',    'route' => 'local.allergy.allergy',         'permission' => 'local.allergy'],
            ],
            'dicomTotal'          => $dicomTotal,
            'dicomHariIni'        => $dicomHariIni,
            'dicomSent'           => $dicomSent,
            'dicomPending'        => $dicomPending,
            'dicomError'          => $dicomError,
            'dicomTrendLabels'    => $dicomTrendLabels,
            'dicomTrendValues'    => $dicomTrendValues,
            'dicomByModality'     => $dicomByModality,
            'pendingJobs'         => $pendingJobs,
            'failedJobs'          => $failedJobs,
            'recentActivity'      => $recentActivity,
        ];
    }
};
?>

<div class="pb-12 space-y-8">

    {{-- Greeting --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-primary-dark-100">
                Selamat datang, {{ auth()->user()->name }} 👋
            </h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-primary-dark-400">
                {{ config('hospital.name', 'Sistem Integrasi Layanan Kesehatan') }}
                &nbsp;·&nbsp;
                {{ now()->translatedFormat('l, d F Y') }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            @if (auth()->user()?->hasPermission('erm'))
                <x-atoms.button wire:navigate href="{{ route('erm.rawat-jalan') }}" variant="primary" icon="document-text" size="sm">
                    Buka eRM
                </x-atoms.button>
            @endif
            @if (auth()->user()?->hasPermission('satusehat'))
                <x-atoms.button wire:navigate href="{{ route('satusehat.summary') }}" variant="outline" size="sm">
                    Satu Sehat
                </x-atoms.button>
            @endif
            @if ($failedJobs > 0 && auth()->user()?->hasPermission('configuration.jobs'))
                <a href="{{ route('configuration.jobs') }}" wire:navigate>
                    <flux:badge color="red" size="sm" icon="exclamation-triangle">
                        {{ $failedJobs }} Job Gagal
                    </flux:badge>
                </a>
            @endif
        </div>
    </div>

    {{-- Stat Cards: 4 utama --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-organisms.stat-card title="Encounter SS Hari Ini" :value="number_format($encounterHariIni)"
            color="sky" icon="document-plus" :subtitle="number_format($encounterTotal) . ' total'" />
        <x-organisms.stat-card title="eRM BPJS Bulan Ini" :value="number_format($bpjsErmBulanIni)"
            color="blue" icon="document-check" :subtitle="number_format($bpjsErmTotal) . ' total terkirim'" />
        <x-organisms.stat-card title="Practitioner SS" :value="number_format($practitionerTotal)"
            color="violet" icon="user-group" subtitle="IHS terdaftar" />
        <x-organisms.stat-card title="Patient SS" :value="number_format($patientTotal)"
            color="emerald" icon="users" subtitle="IHS terdaftar" />
    </div>

    {{-- Stat Cards: TTE + DICOM --}}
    @if (auth()->user()?->hasPermission('tte') || auth()->user()?->hasPermission('dicom'))
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            @if (auth()->user()?->hasPermission('tte'))
                <x-organisms.stat-card title="TTE Hari Ini" :value="number_format($tteHariIni)"
                    color="amber" icon="finger-print" :subtitle="number_format($tteTotal) . ' total'" />
                <x-organisms.stat-card title="TTE Total" :value="number_format($tteTotal)"
                    color="zinc" icon="document-check" subtitle="Seluruh dokumen tersimpan" />
            @endif
            @if (auth()->user()?->hasPermission('dicom'))
                <x-organisms.stat-card title="Studi DICOM Hari Ini" :value="number_format($dicomHariIni)"
                    color="amber" icon="photo" :subtitle="number_format($dicomTotal) . ' total'" />
                <x-organisms.stat-card title="Studi Terkirim" :value="number_format($dicomSent)"
                    color="emerald" icon="arrow-up-tray"
                    :subtitle="($dicomPending > 0 ? number_format($dicomPending) . ' pending' : '') . ($dicomError > 0 ? ' · ' . number_format($dicomError) . ' error' : '')" />
            @endif
        </div>
    @endif

    {{-- Charts Row 1: Tren Encounter + Distribusi eRM --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
        <div class="lg:col-span-3 rounded-xl border border-zinc-200 dark:border-primary-dark-700 bg-white dark:bg-primary-dark-800 p-5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">Tren Encounter Satu Sehat</h3>
                    <p class="text-xs text-zinc-500 dark:text-primary-dark-400 mt-0.5">14 hari terakhir</p>
                </div>
                <flux:icon name="chart-bar" class="w-5 h-5 text-sky-400" />
            </div>
            <div class="h-48"><canvas id="chart-encounter-trend"></canvas></div>
        </div>

        <div class="lg:col-span-2 rounded-xl border border-zinc-200 dark:border-primary-dark-700 bg-white dark:bg-primary-dark-800 p-5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">Distribusi eRM BPJS</h3>
                    <p class="text-xs text-zinc-500 dark:text-primary-dark-400 mt-0.5">Berdasarkan tipe kunjungan</p>
                </div>
                <flux:icon name="chart-pie" class="w-5 h-5 text-blue-400" />
            </div>
            <div class="h-36 flex items-center justify-center">
                <canvas id="chart-erm-dist"></canvas>
            </div>
            <div class="mt-3 flex flex-wrap justify-center gap-x-4 gap-y-1">
                @foreach ([
                    'AMB'  => ['label' => 'Rawat Jalan', 'color' => '#3b82f6'],
                    'EMER' => ['label' => 'IGD',          'color' => '#ef4444'],
                    'IMP'  => ['label' => 'Rawat Inap',  'color' => '#8b5cf6'],
                ] as $type => $meta)
                    <div class="flex items-center gap-1.5 text-xs text-zinc-600 dark:text-primary-dark-400">
                        <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background:{{ $meta['color'] }}"></span>
                        {{ $meta['label'] }}
                        <span class="font-semibold text-zinc-800 dark:text-primary-dark-200">{{ number_format($distribusi[$type] ?? 0) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Charts Row 2: eRM per Bulan + TTE Tren --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-zinc-200 dark:border-primary-dark-700 bg-white dark:bg-primary-dark-800 p-5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">eRM BPJS per Bulan</h3>
                    <p class="text-xs text-zinc-500 dark:text-primary-dark-400 mt-0.5">6 bulan terakhir</p>
                </div>
                @if (auth()->user()?->hasPermission('bpjs.summary'))
                    <x-atoms.button wire:navigate href="{{ route('bpjs.summary') }}" variant="ghost" size="xs" icon="arrow-right">Detail</x-atoms.button>
                @endif
            </div>
            <div class="h-52"><canvas id="chart-erm-monthly"></canvas></div>
        </div>

        @if (auth()->user()?->hasPermission('tte'))
            <div class="rounded-xl border border-zinc-200 dark:border-primary-dark-700 bg-white dark:bg-primary-dark-800 p-5">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">Tren Tanda Tangan Elektronik</h3>
                        <p class="text-xs text-zinc-500 dark:text-primary-dark-400 mt-0.5">14 hari terakhir</p>
                    </div>
                    <x-atoms.button wire:navigate href="{{ route('tte.history') }}" variant="ghost" size="xs" icon="arrow-right">Riwayat</x-atoms.button>
                </div>
                <div class="h-52"><canvas id="chart-tte-trend"></canvas></div>
            </div>
        @else
            <div class="rounded-xl border border-zinc-200 dark:border-primary-dark-700 bg-white dark:bg-primary-dark-800 p-5 flex items-center justify-center">
                <p class="text-xs text-zinc-400 dark:text-primary-dark-600">Tidak ada akses modul TTE</p>
            </div>
        @endif
    </div>

    {{-- Charts Row 3: DICOM --}}
    @if (auth()->user()?->hasPermission('dicom'))
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
            {{-- Tren Studi DICOM --}}
            <div class="lg:col-span-3 rounded-xl border border-zinc-200 dark:border-primary-dark-700 bg-white dark:bg-primary-dark-800 p-5">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">Tren Studi DICOM</h3>
                        <p class="text-xs text-zinc-500 dark:text-primary-dark-400 mt-0.5">14 hari terakhir</p>
                    </div>
                    <x-atoms.button wire:navigate href="{{ route('dicom.summary') }}" variant="ghost" size="xs" icon="arrow-right">Detail</x-atoms.button>
                </div>
                <div class="h-48"><canvas id="chart-dicom-trend"></canvas></div>
            </div>

            {{-- DICOM Breakdown per Modality --}}
            <div class="lg:col-span-2 rounded-xl border border-zinc-200 dark:border-primary-dark-700 bg-white dark:bg-primary-dark-800 p-5">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">Studi per Modality</h3>
                        <p class="text-xs text-zinc-500 dark:text-primary-dark-400 mt-0.5">Distribusi berdasarkan jenis alat</p>
                    </div>
                    <flux:icon name="photo" class="w-5 h-5 text-amber-400" />
                </div>
                <div class="space-y-2.5 mt-2">
                    @php
                        $dicomModalityColors = ['CT' => '#f59e0b', 'MR' => '#3b82f6', 'US' => '#10b981', 'CR' => '#8b5cf6', 'DR' => '#0ea5e9', 'XA' => '#ef4444'];
                        $dicomMax = $dicomByModality->max() ?: 1;
                    @endphp
                    @forelse ($dicomByModality as $modality => $count)
                        @php $color = $dicomModalityColors[$modality] ?? '#71717a'; $pct = round($count / $dicomMax * 100); @endphp
                        <div class="flex items-center gap-3">
                            <span class="w-8 shrink-0 text-[10px] font-bold text-center py-0.5 rounded" style="background:{{ $color }}20;color:{{ $color }}">{{ $modality }}</span>
                            <div class="flex-1 h-1.5 rounded-full bg-zinc-100 dark:bg-primary-dark-700 overflow-hidden">
                                <div class="h-full rounded-full transition-all" style="width:{{ $pct }}%;background:{{ $color }}"></div>
                            </div>
                            <span class="text-xs font-semibold text-zinc-600 dark:text-primary-dark-300 w-10 text-right">{{ number_format($count) }}</span>
                        </div>
                    @empty
                        <p class="text-xs text-center text-zinc-400 dark:text-primary-dark-600 py-4">Belum ada data studi.</p>
                    @endforelse
                </div>
                <div class="mt-4 pt-3 border-t border-zinc-100 dark:border-primary-dark-700/60 flex justify-between text-xs text-zinc-500 dark:text-primary-dark-400">
                    <span>Total: <strong class="text-zinc-700 dark:text-primary-dark-200">{{ number_format($dicomTotal) }}</strong></span>
                    <span class="text-emerald-600 dark:text-emerald-400">{{ number_format($dicomSent) }} terkirim</span>
                    @if ($dicomError > 0)
                        <span class="text-red-500">{{ number_format($dicomError) }} error</span>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Local Terminology Coverage --}}
    @if (auth()->user()?->hasPermission('local'))
        <div class="rounded-xl border border-zinc-200 dark:border-primary-dark-700 bg-white dark:bg-primary-dark-800 p-5">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h3 class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">Local Terminology Coverage</h3>
                    <p class="text-xs text-zinc-500 dark:text-primary-dark-400 mt-0.5">Status mapping resource FHIR lokal</p>
                </div>
                <x-atoms.button wire:navigate href="{{ route('local.summary') }}" variant="ghost" size="xs" icon="arrow-right">Ringkasan</x-atoms.button>
            </div>

            @php
                $colorBar = [
                    'violet'  => 'bg-violet-400 dark:bg-violet-500',
                    'sky'     => 'bg-sky-400 dark:bg-sky-500',
                    'amber'   => 'bg-amber-400 dark:bg-amber-500',
                    'emerald' => 'bg-emerald-400 dark:bg-emerald-500',
                    'red'     => 'bg-red-400 dark:bg-red-500',
                ];
                $colorText = [
                    'violet'  => 'text-violet-700 dark:text-violet-400',
                    'sky'     => 'text-sky-700 dark:text-sky-400',
                    'amber'   => 'text-amber-700 dark:text-amber-400',
                    'emerald' => 'text-emerald-700 dark:text-emerald-400',
                    'red'     => 'text-red-600 dark:text-red-400',
                ];
            @endphp

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($localTerminology as $item)
                    @php
                        $pct = $item['total'] > 0 ? min(100, round($item['mapped'] / $item['total'] * 100)) : null;
                        $bar = $colorBar[$item['color']] ?? 'bg-zinc-400';
                        $txt = $colorText[$item['color']] ?? 'text-zinc-600';
                    @endphp
                    @php $canLink = auth()->user()?->hasPermission($item['permission']); @endphp
                    @if ($canLink)
                        <a href="{{ route($item['route']) }}" wire:navigate
                            class="group p-3.5 rounded-xl border border-zinc-100 dark:border-primary-dark-700/60 hover:border-zinc-200 dark:hover:border-primary-dark-600 transition-colors">
                    @else
                        <div class="p-3.5 rounded-xl border border-zinc-100 dark:border-primary-dark-700/60 opacity-60">
                    @endif
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-semibold text-zinc-600 dark:text-primary-dark-300 {{ $canLink ? 'group-hover:text-zinc-800 dark:group-hover:text-primary-dark-100' : '' }}">
                                {{ $item['label'] }}
                            </span>
                            <span class="text-xs font-bold {{ $txt }}">
                                {{ number_format($item['mapped']) }}
                                @if ($item['total'])
                                    <span class="font-normal text-zinc-400 dark:text-primary-dark-600">/ {{ number_format($item['total']) }}</span>
                                @endif
                            </span>
                        </div>
                        @if ($pct !== null)
                            <div class="h-1.5 rounded-full bg-zinc-100 dark:bg-primary-dark-700 overflow-hidden">
                                <div class="h-full rounded-full {{ $bar }} transition-all" style="width: {{ $pct }}%"></div>
                            </div>
                            <p class="mt-1.5 text-[10px] text-zinc-400 dark:text-primary-dark-600">{{ $pct }}% terpetakan</p>
                        @else
                            <div class="h-1.5 rounded-full bg-zinc-100 dark:bg-primary-dark-700 overflow-hidden">
                                <div class="h-full rounded-full {{ $bar }}" style="width: {{ $item['mapped'] > 0 ? '100%' : '0%' }}"></div>
                            </div>
                            <p class="mt-1.5 text-[10px] text-zinc-400 dark:text-primary-dark-600">{{ number_format($item['mapped']) }} terpetakan</p>
                        @endif
                    @if ($canLink)
                        </a>
                    @else
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    {{-- Bottom Row: Aktivitas Terbaru (admin only) + Modul + Queue --}}
    <div class="grid grid-cols-1 gap-6 {{ auth()->user()?->isAdmin() ? 'lg:grid-cols-5' : 'lg:grid-cols-5' }}">

        {{-- Aktivitas Terbaru SS — hanya admin --}}
        @if (auth()->user()?->isAdmin())
        <div class="lg:col-span-3 rounded-xl border border-zinc-200 dark:border-primary-dark-700 bg-white dark:bg-primary-dark-800 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-100 dark:border-primary-dark-700/60">
                <h3 class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">Aktivitas Terbaru Satu Sehat</h3>
                @if (auth()->user()?->hasPermission('logs.satusehat'))
                    <x-atoms.button wire:navigate href="{{ route('logs.satusehat') }}" variant="ghost" size="xs" icon="arrow-right">Semua Log</x-atoms.button>
                @endif
            </div>
            <div class="divide-y divide-zinc-50 dark:divide-primary-dark-700/40">
                @forelse ($recentActivity as $log)
                    @php
                        $dot = match(true) {
                            $log->response_status >= 200 && $log->response_status < 300 => 'bg-emerald-400',
                            $log->response_status >= 400 && $log->response_status < 500 => 'bg-amber-400',
                            $log->response_status >= 500 => 'bg-red-400',
                            default => 'bg-zinc-300',
                        };
                        $badge = match(true) {
                            $log->response_status >= 200 && $log->response_status < 300 => 'green',
                            $log->response_status >= 500 => 'red',
                            default => 'yellow',
                        };
                    @endphp
                    <div class="flex items-center gap-3 px-5 py-3">
                        <span class="w-2 h-2 rounded-full {{ $dot }} shrink-0"></span>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium text-zinc-700 dark:text-primary-dark-200 truncate">
                                {{ $log->resource_type ?? $log->method ?? '-' }}
                            </p>
                            <p class="text-[10px] text-zinc-400 dark:text-primary-dark-500">
                                {{ $log->created_at?->diffForHumans() }}
                            </p>
                        </div>
                        @if ($log->response_status)
                            <flux:badge :color="$badge" size="sm">{{ $log->response_status }}</flux:badge>
                        @endif
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-xs text-zinc-400 dark:text-primary-dark-500">
                        Belum ada aktivitas.
                    </div>
                @endforelse
            </div>
        </div>
        @endif

        {{-- Kolom kanan: Queue (admin only) + Modul --}}
        <div class="{{ auth()->user()?->isAdmin() ? 'lg:col-span-2' : 'lg:col-span-5' }} space-y-4">
            {{-- Job Queue — hanya admin --}}
            @if (auth()->user()?->isAdmin())
            <div class="rounded-xl border border-zinc-200 dark:border-primary-dark-700 bg-white dark:bg-primary-dark-800 p-4">
                <h3 class="text-xs font-semibold uppercase tracking-widest text-zinc-400 dark:text-primary-dark-500 mb-3">Status Queue</h3>
                <div class="flex items-center justify-between">
                    <div class="text-center">
                        <p class="text-2xl font-bold text-zinc-800 dark:text-primary-dark-100">{{ number_format($pendingJobs) }}</p>
                        <p class="text-xs text-zinc-500 dark:text-primary-dark-400 mt-0.5">Pending</p>
                    </div>
                    <div class="w-px h-8 bg-zinc-200 dark:bg-primary-dark-700"></div>
                    <div class="text-center">
                        <p class="text-2xl font-bold {{ $failedJobs > 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-800 dark:text-primary-dark-100' }}">
                            {{ number_format($failedJobs) }}
                        </p>
                        <p class="text-xs text-zinc-500 dark:text-primary-dark-400 mt-0.5">Gagal</p>
                    </div>
                    @if (auth()->user()?->hasPermission('configuration.jobs'))
                        <x-atoms.button wire:navigate href="{{ route('configuration.jobs') }}" variant="ghost" size="xs" icon="arrow-right" />
                    @endif
                </div>
            </div>
            @endif

            {{-- Modul --}}
            @php
                $modules = [
                    ['title' => 'eRM',              'route' => 'erm.rawat-jalan',   'icon' => 'document-text',    'color' => 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10', 'permission' => 'erm'],
                    ['title' => 'Satu Sehat',        'route' => 'satusehat.summary', 'icon' => 'heart',            'color' => 'text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-500/10',               'permission' => 'satusehat'],
                    ['title' => 'BPJS',              'route' => 'bpjs.summary',      'icon' => 'shield-check',     'color' => 'text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-500/10',           'permission' => 'bpjs'],
                    ['title' => 'Local Terminology', 'route' => 'local.summary',     'icon' => 'rectangle-stack',  'color' => 'text-violet-600 dark:text-violet-400 bg-violet-50 dark:bg-violet-500/10',   'permission' => 'local'],
                    ['title' => 'DICOM / PACS',      'route' => 'dicom.summary',     'icon' => 'photo',            'color' => 'text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-500/10',       'permission' => 'dicom'],
                    ['title' => 'WhatsApp',          'route' => 'whatsapp.messages', 'icon' => 'device-phone-mobile','color' => 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10','permission' => 'whatsapp'],
                    ['title' => 'SIRS Online',       'route' => 'sirs.index',        'icon' => 'chart-bar',        'color' => 'text-orange-600 dark:text-orange-400 bg-orange-50 dark:bg-orange-500/10',   'permission' => 'sirs'],
                    ['title' => 'TTE',               'route' => 'tte.simulation',    'icon' => 'finger-print',     'color' => 'text-zinc-600 dark:text-zinc-400 bg-zinc-100 dark:bg-zinc-700',             'permission' => 'tte'],
                ];
            @endphp
            <div class="rounded-xl border border-zinc-200 dark:border-primary-dark-700 bg-white dark:bg-primary-dark-800 p-4">
                <h3 class="text-xs font-semibold uppercase tracking-widest text-zinc-400 dark:text-primary-dark-500 mb-3">Modul</h3>
                <div class="grid grid-cols-2 gap-2">
                    @foreach ($modules as $mod)
                        @if (auth()->user()?->hasPermission($mod['permission']))
                            <a href="{{ route($mod['route']) }}" wire:navigate
                                class="flex items-center gap-2.5 p-2.5 rounded-lg hover:bg-zinc-50 dark:hover:bg-primary-dark-700/60 transition-colors group">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg {{ $mod['color'] }}">
                                    <flux:icon name="{{ $mod['icon'] }}" class="w-3.5 h-3.5" />
                                </span>
                                <span class="text-xs font-medium text-zinc-700 dark:text-primary-dark-300 group-hover:text-zinc-900 dark:group-hover:text-white leading-tight">
                                    {{ $mod['title'] }}
                                </span>
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Akses Cepat --}}
    <div>
        <p class="text-xs font-semibold uppercase tracking-widest text-zinc-400 dark:text-primary-dark-500 mb-3">Akses Cepat</p>
        <div class="flex flex-wrap gap-2">
            @foreach ([
                ['label' => 'eRM IGD',          'route' => 'erm.igd',                  'permission' => 'erm.igd'],
                ['label' => 'eRM Rawat Jalan',  'route' => 'erm.rawat-jalan',          'permission' => 'erm.rawat_jalan'],
                ['label' => 'eRM Rawat Inap',   'route' => 'erm.rawat-inap',           'permission' => 'erm.rawat_inap'],
                ['label' => 'vClaim SEP',        'route' => 'bpjs.vclaim',              'permission' => 'bpjs.vclaim'],
                ['label' => 'Antrean Online',    'route' => 'bpjs.antrean-online',      'permission' => 'bpjs.antrean_online'],
                ['label' => 'Pencarian Pintar',  'route' => 'terminology.smart-search', 'permission' => 'terminology.smart_search'],
                ['label' => 'Practitioner',      'route' => 'local.practitioner',       'permission' => 'local.practitioner'],
                ['label' => 'TTE Simulasi',      'route' => 'tte.simulation',           'permission' => 'tte.simulation'],
                ['label' => 'Status Koneksi',    'route' => 'utility.connection-status','permission' => 'utility.connection_status'],
                ['label' => 'Log Satu Sehat',    'route' => 'logs.satusehat',           'permission' => 'logs.satusehat'],
                ['label' => 'Log BPJS',          'route' => 'logs.bpjs',                'permission' => 'logs.bpjs'],
            ] as $link)
                @if (auth()->user()?->hasPermission($link['permission']))
                    <a href="{{ route($link['route']) }}" wire:navigate
                        class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg border border-zinc-200 dark:border-primary-dark-700 bg-white dark:bg-primary-dark-800 text-zinc-600 dark:text-primary-dark-300 hover:border-zinc-300 dark:hover:border-primary-dark-600 hover:text-zinc-800 dark:hover:text-primary-dark-100 transition-all">
                        {{ $link['label'] }}
                    </a>
                @endif
            @endforeach
        </div>
    </div>
</div>

{{-- Data grafik di HTML agar Blade memproses @json sebelum dikirim ke browser --}}
<script type="application/json" id="dashboard-chart-data">
{
    "trendLabels":    @json($trendLabels),
    "trendValues":    @json($trendValues),
    "tteTrendLabels": @json($tteTrendLabels),
    "tteTrendValues": @json($tteTrendValues),
    "ermLabels":      @json($ermLabels),
    "ermAmb":         @json($ermAmb),
    "ermEmer":        @json($ermEmer),
    "ermImp":         @json($ermImp),
    "distAmb":          {{ (int) ($distribusi['AMB']  ?? 0) }},
    "distEmer":         {{ (int) ($distribusi['EMER'] ?? 0) }},
    "distImp":          {{ (int) ($distribusi['IMP']  ?? 0) }},
    "hasTte":           {{ auth()->user()?->hasPermission('tte') ? 'true' : 'false' }},
    "hasDicom":         {{ auth()->user()?->hasPermission('dicom') ? 'true' : 'false' }},
    "dicomTrendLabels": @json($dicomTrendLabels),
    "dicomTrendValues": @json($dicomTrendValues)
}
</script>

@once
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
@endonce

<script>
(function () {
    const raw = document.getElementById('dashboard-chart-data');
    if (!raw) return;
    const d    = JSON.parse(raw.textContent);
    const dark = document.documentElement.classList.contains('dark');
    const grid = dark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)';
    const tick = dark ? '#9ca3af' : '#6b7280';

    function waitChart(cb) {
        if (typeof Chart !== 'undefined') { cb(); return; }
        const t = setInterval(() => { if (typeof Chart !== 'undefined') { clearInterval(t); cb(); } }, 50);
    }

    waitChart(function () {
        Chart.defaults.font.family = 'inherit';
        Chart.defaults.font.size   = 11;

        const axisOpts = (stacked) => ({
            x: { stacked, grid: { display: false }, ticks: { color: tick } },
            y: { stacked, grid: { color: grid }, ticks: { color: tick, precision: 0 }, beginAtZero: true },
        });

        // Tren Encounter SS
        const elEnc = document.getElementById('chart-encounter-trend');
        if (elEnc) new Chart(elEnc, {
            type: 'line',
            data: {
                labels: d.trendLabels,
                datasets: [{
                    label: 'Encounter',
                    data: d.trendValues,
                    borderColor: '#0ea5e9',
                    backgroundColor: 'rgba(14,165,233,0.08)',
                    borderWidth: 2,
                    pointRadius: 3,
                    pointBackgroundColor: '#0ea5e9',
                    tension: 0.4,
                    fill: true,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { color: grid }, ticks: { color: tick, maxRotation: 0 } },
                    y: { grid: { color: grid }, ticks: { color: tick, precision: 0 }, beginAtZero: true },
                }
            }
        });

        // Distribusi eRM
        const elDist = document.getElementById('chart-erm-dist');
        if (elDist) new Chart(elDist, {
            type: 'doughnut',
            data: {
                labels: ['Rawat Jalan', 'IGD', 'Rawat Inap'],
                datasets: [{
                    data: [d.distAmb, d.distEmer, d.distImp],
                    backgroundColor: ['#3b82f6', '#ef4444', '#8b5cf6'],
                    borderWidth: 0, hoverOffset: 4,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                cutout: '68%',
                plugins: { legend: { display: false } },
            }
        });

        // eRM per Bulan
        const elErm = document.getElementById('chart-erm-monthly');
        if (elErm) new Chart(elErm, {
            type: 'bar',
            data: {
                labels: d.ermLabels,
                datasets: [
                    { label: 'Rawat Jalan', data: d.ermAmb,  backgroundColor: '#3b82f6', borderRadius: 4 },
                    { label: 'IGD',         data: d.ermEmer, backgroundColor: '#ef4444', borderRadius: 4 },
                    { label: 'Rawat Inap',  data: d.ermImp,  backgroundColor: '#8b5cf6', borderRadius: 4 },
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true, position: 'top', align: 'end',
                        labels: { color: tick, boxWidth: 10, boxHeight: 10, borderRadius: 3, useBorderRadius: true, padding: 16, font: { size: 11 } }
                    }
                },
                scales: axisOpts(false),
            }
        });

        // Tren DICOM
        const elDicom = document.getElementById('chart-dicom-trend');
        if (elDicom && d.hasDicom) new Chart(elDicom, {
            type: 'line',
            data: {
                labels: d.dicomTrendLabels,
                datasets: [{
                    label: 'Studi',
                    data: d.dicomTrendValues,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245,158,11,0.08)',
                    borderWidth: 2,
                    pointRadius: 3,
                    pointBackgroundColor: '#f59e0b',
                    tension: 0.4,
                    fill: true,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { color: grid }, ticks: { color: tick, maxRotation: 0 } },
                    y: { grid: { color: grid }, ticks: { color: tick, precision: 0 }, beginAtZero: true },
                }
            }
        });

        // Tren TTE
        const elTte = document.getElementById('chart-tte-trend');
        if (elTte && d.hasTte) new Chart(elTte, {
            type: 'bar',
            data: {
                labels: d.tteTrendLabels,
                datasets: [{
                    label: 'Dokumen TTE',
                    data: d.tteTrendValues,
                    backgroundColor: 'rgba(245,158,11,0.7)',
                    borderColor: '#f59e0b',
                    borderWidth: 1,
                    borderRadius: 4,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: tick, maxRotation: 0 } },
                    y: { grid: { color: grid }, ticks: { color: tick, precision: 0 }, beginAtZero: true },
                }
            }
        });
    });
})();
</script>
