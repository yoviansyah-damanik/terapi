<?php

use App\Models\Bpjs\BpjsAntreanBooking;
use App\Models\Bpjs\BpjsAntreanRegistration;
use App\Models\Bpjs\BpjsErm;
use App\Models\Bpjs\BpjsHealthcareService;
use App\Models\Bpjs\BpjsIcd10;
use App\Models\Bpjs\BpjsIcd9;
use App\Models\Bpjs\BpjsMedication;
use App\Models\Bpjs\BpjsPatient;
use App\Models\Bpjs\BpjsPractitioner;
use App\Models\Bpjs\BpjsProcedure;
use App\Models\Terminology\Icd10;
use App\Models\Terminology\Icd9;
use App\Models\Simrs\Bangsal;
use App\Models\Simrs\DataBarang;
use App\Models\Simrs\JnsPerawatan;
use App\Models\Simrs\JnsPerawatanInap;
use App\Models\Simrs\JnsPerawatanLab;
use App\Models\Simrs\JnsPerawatanRadiologi;
use App\Models\Simrs\BridgingSep;
use App\Models\Simrs\Pasien;
use App\Models\Simrs\Pegawai;
use App\Models\Simrs\Poliklinik;
use App\Models\Simrs\TemplateLaboratorium;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new #[Layout('layouts::app')] #[Title('Ringkasan BPJS Kesehatan')] class extends Component {
    public function with(): array
    {
        // --- eRM Terkirim ---
        $ermCounts = BpjsErm::selectRaw('encounter_type, count(*) as total')->groupBy('encounter_type')->pluck('total', 'encounter_type');
        $ermTotal = BpjsErm::count();
        $ermBulanIni = BpjsErm::whereYear('sent_at', now()->year)->whereMonth('sent_at', now()->month)->count();

        // --- Patient ---
        $patientRegistered = BpjsPatient::count();
        $patientTotal = 0;
        try {
            $patientTotal = Pasien::whereNotNull('no_ktp')->where('no_ktp', '!=', '')->count();
        } catch (\Exception) {
        }

        // --- Practitioner (SIMRS — pakai try/catch karena beda koneksi DB) ---
        $medisTotal = $kepTotal = $penTotal = $nonMedisTotal = 0;
        $medisRegistered = $kepRegistered = $penRegistered = $nonMedisRegistered = 0;
        try {
            $medisNiks = Pegawai::where('stts_aktif', 'AKTIF')->where('bidang', 'Medis')->pluck('nik');
            $kepNiks = Pegawai::where('stts_aktif', 'AKTIF')
                ->whereIn('bidang', ['Keperawatan', 'Kebidanan'])
                ->pluck('nik');
            $penNiks = Pegawai::where('stts_aktif', 'AKTIF')->where('bidang', 'Penunjang Medis')->pluck('nik');
            $nonMedisNiks = Pegawai::where('stts_aktif', 'AKTIF')
                ->whereNotIn('bidang', ['Medis', 'Keperawatan', 'Kebidanan', 'Penunjang Medis'])
                ->pluck('nik');

            $medisTotal = $medisNiks->count();
            $kepTotal = $kepNiks->count();
            $penTotal = $penNiks->count();
            $nonMedisTotal = $nonMedisNiks->count();

            $medisRegistered = BpjsPractitioner::whereIn('identifier', $medisNiks)->count();
            $kepRegistered = BpjsPractitioner::whereIn('identifier', $kepNiks)->count();
            $penRegistered = BpjsPractitioner::whereIn('identifier', $penNiks)->count();
            $nonMedisRegistered = BpjsPractitioner::whereIn('identifier', $nonMedisNiks)->count();
        } catch (\Exception) {
        }

        // --- Healthcare Service (SIMRS) ---
        $poliRegistered = BpjsHealthcareService::where('type', 'poliklinik')->count();
        $bangsalRegistered = BpjsHealthcareService::where('type', 'bangsal')->count();
        $poliTotal = $bangsalTotal = 0;
        try {
            $poliTotal = Poliklinik::active()->count();
            $bangsalTotal = Bangsal::where('status', '1')->count();
        } catch (\Exception) {
        }

        // --- Procedure (SIMRS) ---
        $procCounts = BpjsProcedure::selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type');
        $ralanTotal = $ranapTotal = $labTotal = $itemLabTotal = $radTotal = 0;
        try {
            $ralanTotal = JnsPerawatan::count();
            $ranapTotal = JnsPerawatanInap::count();
            $labTotal = JnsPerawatanLab::count();
            $itemLabTotal = TemplateLaboratorium::count();
            $radTotal = JnsPerawatanRadiologi::count();
        } catch (\Exception) {
        }

        // --- Clinical (ICD — local DB) ---
        $icd10Total = Icd10::distinct('code')->count('code');
        $icd9Total = Icd9::distinct('code')->count('code');
        $icd10Registered = BpjsIcd10::count();
        $icd9Registered = BpjsIcd9::count();

        // --- Antrean Online ---
        $antreanTotal    = BpjsAntreanBooking::count();
        $antreanHariIni  = BpjsAntreanBooking::where('tanggal', today()->format('Y-m-d'))->count();
        $antreanBulanIni = BpjsAntreanBooking::whereYear('tanggal', now()->year)->whereMonth('tanggal', now()->month)->count();
        $antreanSelesai  = BpjsAntreanBooking::whereRaw('LOWER(status) = ?', ['selesai'])->count();
        $antreanBatal    = BpjsAntreanBooking::whereRaw('LOWER(status) = ?', ['batal'])->count();
        $antreanTerdaftar = BpjsAntreanRegistration::count();

        // --- vClaim SEP (SIMRS) ---
        $sepTotal = $sepBulanIni = $sepRalan = $sepRanap = $sepIgd = 0;
        try {
            $sepTotal    = BridgingSep::count();
            $sepBulanIni = BridgingSep::whereYear('tglsep', now()->year)->whereMonth('tglsep', now()->month)->count();
            $sepRalan    = BridgingSep::ralanOnly()->count();
            $sepRanap    = BridgingSep::where('jnspelayanan', '1')->count();
            $sepIgd      = BridgingSep::igd()->count();
        } catch (\Exception) {}

        // --- Medication (SIMRS) ---
        $medicationRegistered = BpjsMedication::count();
        $medicationTotal = 0;
        try {
            $medicationTotal = DataBarang::count();
        } catch (\Exception) {
        }

        return [
            'ermTotal' => $ermTotal,
            'ermBulanIni' => $ermBulanIni,
            'ermCounts' => $ermCounts,
            'patient' => [['label' => 'Pasien', 'total' => $patientTotal, 'mapped' => $patientRegistered, 'route' => 'bpjs.fhir-resource.patient']],
            'practitioner' => [['label' => 'Tenaga Medis', 'total' => $medisTotal, 'mapped' => $medisRegistered, 'route' => 'bpjs.fhir-resource.practitioner'], ['label' => 'Keperawatan', 'total' => $kepTotal, 'mapped' => $kepRegistered, 'route' => 'bpjs.fhir-resource.practitioner'], ['label' => 'Penunjang Medis', 'total' => $penTotal, 'mapped' => $penRegistered, 'route' => 'bpjs.fhir-resource.practitioner'], ['label' => 'Non Medis', 'total' => $nonMedisTotal, 'mapped' => $nonMedisRegistered, 'route' => 'bpjs.fhir-resource.practitioner']],
            'healthcare_service' => [['label' => 'Poliklinik', 'total' => $poliTotal, 'mapped' => $poliRegistered, 'route' => 'bpjs.fhir-resource.healthcare-service'], ['label' => 'Bangsal/Ruang', 'total' => $bangsalTotal, 'mapped' => $bangsalRegistered, 'route' => 'bpjs.fhir-resource.healthcare-service']],
            'procedure' => [['label' => 'Rawat Jalan', 'total' => $ralanTotal, 'mapped' => $procCounts['ralan'] ?? 0, 'route' => 'bpjs.fhir-resource.procedure'], ['label' => 'Rawat Inap', 'total' => $ranapTotal, 'mapped' => $procCounts['ranap'] ?? 0, 'route' => 'bpjs.fhir-resource.procedure'], ['label' => 'Lab Jenis', 'total' => $labTotal, 'mapped' => $procCounts['lab'] ?? 0, 'route' => 'bpjs.fhir-resource.procedure'], ['label' => 'Lab Item', 'total' => $itemLabTotal, 'mapped' => $procCounts['item_lab'] ?? 0, 'route' => 'bpjs.fhir-resource.procedure'], ['label' => 'Radiologi', 'total' => $radTotal, 'mapped' => $procCounts['rad'] ?? 0, 'route' => 'bpjs.fhir-resource.procedure']],
            'clinical' => [['label' => 'ICD-10', 'total' => $icd10Total, 'mapped' => $icd10Registered, 'route' => 'bpjs.fhir-resource.icd10'], ['label' => 'ICD-9CM', 'total' => $icd9Total, 'mapped' => $icd9Registered, 'route' => 'bpjs.fhir-resource.icd9']],
            'medication' => [['label' => 'Obat / Alkes', 'total' => $medicationTotal, 'mapped' => $medicationRegistered, 'route' => 'bpjs.fhir-resource.medication']],
            'sep' => compact('sepTotal', 'sepBulanIni', 'sepRalan', 'sepRanap', 'sepIgd'),
            'antrean' => compact('antreanTotal', 'antreanHariIni', 'antreanBulanIni', 'antreanSelesai', 'antreanBatal', 'antreanTerdaftar'),
        ];
    }
};
?>

<div class="pb-12 space-y-8 min-h-[calc(100vh-100px)]">
    <x-ui.page-header title="Ringkasan BPJS Kesehatan"
        subtitle="Analitik data rekam medis elektronik dan pemantauan status integrasi UUID FHIR BPJS." />

    {{-- eRM Terkirim --}}
    <section>
        <div class="flex items-center gap-2 mb-4">
            <h2 class="text-lg font-bold text-zinc-800 dark:text-primary-dark-100 flex items-center gap-2">
                <flux:icon name="document-check" class="w-5 h-5 text-emerald-500" />
                Metrik Pengiriman eRM
            </h2>
            <div class="h-px flex-1 bg-gradient-to-r from-zinc-200 to-transparent dark:from-primary-dark-800 ml-4"></div>
        </div>

        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            {{-- Total --}}
            <x-organisms.stat-card wire:navigate href="{{ route('bpjs.erm') }}" title="Total Terkirim"
                value="{{ number_format($ermTotal) }}" color="zinc"
                subtitle="{{ number_format($ermBulanIni) }} bulan ini" />

            {{-- Per encounter_type --}}
            @foreach ([
        'AMB' => ['label' => 'Rawat Jalan', 'color' => 'sky'],
        'EMER' => ['label' => 'IGD', 'color' => 'red'],
        'IMP' => ['label' => 'Rawat Inap', 'color' => 'violet'],
    ] as $type => $meta)
                @php
                    $sub = $ermTotal > 0 ? round((($ermCounts[$type] ?? 0) / $ermTotal) * 100) . '% dari total' : '';
                @endphp
                <x-organisms.stat-card wire:navigate href="{{ route('bpjs.erm') }}" title="{{ $meta['label'] }}"
                    value="{{ number_format($ermCounts[$type] ?? 0) }}" color="{{ $meta['color'] }}"
                    subtitle="{{ $sub }}" />
            @endforeach
        </div>
    </section>

    {{-- vClaim SEP --}}
    <section>
        <div class="flex items-center gap-2 mb-4">
            <h2 class="text-lg font-bold text-zinc-800 dark:text-primary-dark-100 flex items-center gap-2">
                <flux:icon name="document-text" class="w-5 h-5 text-blue-500" />
                vClaim SEP
            </h2>
            <div class="h-px flex-1 bg-gradient-to-r from-zinc-200 to-transparent dark:from-primary-dark-800 ml-4"></div>
            <x-atoms.button wire:navigate href="{{ route('bpjs.vclaim') }}" variant="ghost" size="sm"
                icon="arrow-right">Lihat Detail</x-atoms.button>
        </div>

        <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
            <x-organisms.stat-card wire:navigate href="{{ route('bpjs.vclaim') }}" title="Total SEP"
                :value="number_format($sep['sepTotal'])" color="zinc"
                :subtitle="number_format($sep['sepBulanIni']) . ' bulan ini'" />
            <x-organisms.stat-card wire:navigate href="{{ route('bpjs.vclaim', ['filterJenis' => 'ralan']) }}"
                title="Rawat Jalan" :value="number_format($sep['sepRalan'])" color="blue"
                :subtitle="$sep['sepTotal'] > 0 ? round($sep['sepRalan'] / $sep['sepTotal'] * 100) . '% dari total' : ''" />
            <x-organisms.stat-card wire:navigate href="{{ route('bpjs.vclaim', ['filterJenis' => 'ranap']) }}"
                title="Rawat Inap" :value="number_format($sep['sepRanap'])" color="emerald"
                :subtitle="$sep['sepTotal'] > 0 ? round($sep['sepRanap'] / $sep['sepTotal'] * 100) . '% dari total' : ''" />
            <x-organisms.stat-card wire:navigate href="{{ route('bpjs.vclaim', ['filterJenis' => 'igd']) }}"
                title="IGD" :value="number_format($sep['sepIgd'])" color="red"
                :subtitle="$sep['sepTotal'] > 0 ? round($sep['sepIgd'] / $sep['sepTotal'] * 100) . '% dari total' : ''" />
            @php
                $sepHariIni = 0;
                try {
                    $sepHariIni = \App\Models\Simrs\BridgingSep::whereDate('tglsep', today())->count();
                } catch (\Throwable) {}
            @endphp
            <x-organisms.stat-card wire:navigate
                href="{{ route('bpjs.vclaim', ['filterMode' => 'tanggal', 'filterTanggal' => now()->format('Y-m-d')]) }}"
                title="SEP Hari Ini" :value="number_format($sepHariIni)" color="sky" />
        </div>
    </section>

    {{-- Antrean Online --}}
    <section>
        <div class="flex items-center gap-2 mb-4">
            <h2 class="text-lg font-bold text-zinc-800 dark:text-primary-dark-100 flex items-center gap-2">
                <flux:icon name="queue-list" class="w-5 h-5 text-violet-500" />
                Antrean Online
            </h2>
            <div class="h-px flex-1 bg-gradient-to-r from-zinc-200 to-transparent dark:from-primary-dark-800 ml-4"></div>
            <x-atoms.button wire:navigate href="{{ route('bpjs.antrean-online') }}" variant="ghost" size="sm"
                icon="arrow-right">Lihat Detail</x-atoms.button>
        </div>

        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
            <x-organisms.stat-card wire:navigate href="{{ route('bpjs.antrean-online') }}" title="Total Booking"
                :value="number_format($antrean['antreanTotal'])" color="zinc" />
            <x-organisms.stat-card wire:navigate href="{{ route('bpjs.antrean-online') }}" title="Hari Ini"
                :value="number_format($antrean['antreanHariIni'])" color="sky" />
            <x-organisms.stat-card wire:navigate href="{{ route('bpjs.antrean-online') }}" title="Bulan Ini"
                :value="number_format($antrean['antreanBulanIni'])" color="violet" />
            <x-organisms.stat-card wire:navigate href="{{ route('bpjs.antrean-online') }}" title="Selesai"
                :value="number_format($antrean['antreanSelesai'])" color="emerald"
                :subtitle="$antrean['antreanTotal'] > 0 ? round($antrean['antreanSelesai'] / $antrean['antreanTotal'] * 100) . '% dari total' : ''" />
            <x-organisms.stat-card wire:navigate href="{{ route('bpjs.antrean-online') }}" title="Batal"
                :value="number_format($antrean['antreanBatal'])" color="red"
                :subtitle="$antrean['antreanTotal'] > 0 ? round($antrean['antreanBatal'] / $antrean['antreanTotal'] * 100) . '% dari total' : ''" />
            <x-organisms.stat-card wire:navigate href="{{ route('bpjs.antrean-online') }}" title="Terdaftar SIMRS"
                :value="number_format($antrean['antreanTerdaftar'])" color="amber"
                :subtitle="$antrean['antreanTotal'] > 0 ? round($antrean['antreanTerdaftar'] / $antrean['antreanTotal'] * 100) . '% dari total' : ''" />
        </div>
    </section>

    {{-- FHIR Resource --}}
    @php
        $groups = [
            ['key' => 'patient', 'title' => 'Patient', 'color' => 'blue', 'icon' => 'users', 'data' => $patient],
            [
                'key' => 'practitioner',
                'title' => 'Practitioner',
                'color' => 'sky',
                'icon' => 'user-group',
                'data' => $practitioner,
            ],
            [
                'key' => 'healthcare_service',
                'title' => 'Healthcare Service',
                'color' => 'teal',
                'icon' => 'building-office-2',
                'data' => $healthcare_service,
            ],
            [
                'key' => 'procedure',
                'title' => 'Procedure',
                'color' => 'amber',
                'icon' => 'clipboard-document-list',
                'data' => $procedure,
            ],
            [
                'key' => 'clinical',
                'title' => 'Clinical (ICD)',
                'color' => 'violet',
                'icon' => 'document-text',
                'data' => $clinical,
            ],
            [
                'key' => 'medication',
                'title' => 'Medication',
                'color' => 'emerald',
                'icon' => 'shopping-bag',
                'data' => $medication,
            ],
        ];

        $colorMap = [
            'blue' => [
                'light' => 'bg-blue-50 dark:bg-blue-900/20',
                'text' => 'text-blue-700 dark:text-blue-300',
                'ring' => 'ring-blue-200 dark:ring-blue-800',
                'bar' => 'bg-blue-400 dark:bg-blue-500',
                'hex' => '#3b82f6',
            ],
            'sky' => [
                'light' => 'bg-sky-50 dark:bg-sky-900/20',
                'text' => 'text-sky-700 dark:text-sky-300',
                'ring' => 'ring-sky-200 dark:ring-sky-800',
                'bar' => 'bg-sky-400 dark:bg-sky-500',
                'hex' => '#0ea5e9',
            ],
            'teal' => [
                'light' => 'bg-teal-50 dark:bg-teal-900/20',
                'text' => 'text-teal-700 dark:text-teal-300',
                'ring' => 'ring-teal-200 dark:ring-teal-800',
                'bar' => 'bg-teal-400 dark:bg-teal-500',
                'hex' => '#14b8a6',
            ],
            'amber' => [
                'light' => 'bg-amber-50 dark:bg-amber-900/20',
                'text' => 'text-amber-700 dark:text-amber-300',
                'ring' => 'ring-amber-200 dark:ring-amber-800',
                'bar' => 'bg-amber-400 dark:bg-amber-500',
                'hex' => '#f59e0b',
            ],
            'violet' => [
                'light' => 'bg-violet-50 dark:bg-violet-900/20',
                'text' => 'text-violet-700 dark:text-violet-300',
                'ring' => 'ring-violet-200 dark:ring-violet-800',
                'bar' => 'bg-violet-400 dark:bg-violet-500',
                'hex' => '#8b5cf6',
            ],
            'emerald' => [
                'light' => 'bg-emerald-50 dark:bg-emerald-900/20',
                'text' => 'text-emerald-700 dark:text-emerald-300',
                'ring' => 'ring-emerald-200 dark:ring-emerald-800',
                'bar' => 'bg-emerald-400 dark:bg-emerald-500',
                'hex' => '#10b981',
            ],
        ];
    @endphp

    <div class="flex items-center gap-3 mb-5">
        <div
            class="flex items-center justify-center w-9 h-9 rounded-xl bg-zinc-100 dark:bg-primary-dark-800 ring-1 ring-zinc-200 dark:ring-primary-dark-700 shrink-0">
            <flux:icon name="cube" class="w-4.5 h-4.5 text-zinc-500 dark:text-primary-dark-400" />
        </div>
        <div>
            <h3 class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">FHIR Resource</h3>
            <p class="text-xs text-zinc-400 dark:text-primary-dark-500">Status pendaftaran UUID FHIR per resource type
            </p>
        </div>
    </div>

    <div class="columns-1 gap-5 sm:columns-2 lg:columns-3 xl:columns-4 [column-fill:_balance]">
        @foreach ($groups as $group)
            @php
                $items = $group['data'];
                $groupTotal = collect($items)->sum('total');
                $groupMapped = collect($items)->sum('mapped');
                $groupPct = $groupTotal > 0 ? min(100, round(($groupMapped / $groupTotal) * 100)) : 0;
            @endphp

            <div class="mb-5 break-inside-avoid-column">
                <x-organisms.stat-card-fhir :title="$group['title']" :value="$groupMapped" :total="$groupTotal" :percentage="$groupPct"
                    :supported="true">
                    @foreach ($items as $item)
                        <div class="flex items-center justify-between group/sub">
                            <div class="flex items-center gap-2">
                                <div
                                    class="w-1.5 h-1.5 rounded-full {{ $item['mapped'] > 0 ? 'bg-sky-400 dark:bg-sky-500' : 'bg-zinc-200 dark:bg-primary-dark-700' }} transition-colors">
                                </div>
                                <span
                                    class="text-xs font-medium transition-colors text-zinc-600 dark:text-primary-dark-400 group-hover/sub:text-zinc-900 dark:group-hover/sub:text-primary-dark-200">
                                    <a href="{{ route($item['route']) }}" wire:navigate
                                        class="hover:underline hover:text-sky-600 dark:hover:text-sky-400">
                                        {{ $item['label'] }}
                                    </a>
                                </span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span
                                    class="text-[10px] text-zinc-400 dark:text-primary-dark-500 font-medium">{{ number_format($item['total']) }}
                                    /</span>
                                <span
                                    class="px-2 py-0.5 text-xs font-bold rounded-md bg-zinc-50 dark:bg-primary-dark-800/80 {{ $item['mapped'] > 0 ? 'text-sky-700 dark:text-sky-400 border border-sky-100 dark:border-sky-900/50' : 'text-zinc-400 dark:text-primary-dark-500 border border-transparent' }}">
                                    {{ $item['mapped'] > 0 ? number_format($item['mapped']) : '-' }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </x-organisms.stat-card-fhir>
            </div>
        @endforeach
    </div>
</div>
