<?php

use App\Models\Terminology\Icd10;
use App\Models\Terminology\Icd9;
use App\Models\Terminology\IcdMm;
use App\Models\Terminology\IcdPm;
use App\Models\Terminology\IcdOTopography;
use App\Models\Terminology\IcdOMorphology;
use App\Models\Mapping\AllergyMap;
use App\Models\Mapping\AllergyReactionMap;
use App\Models\Mapping\DoctorMap;
use App\Models\Mapping\HealthcareServiceMap;
use App\Models\Mapping\HsServiceItem;
use App\Models\Simrs\Bangsal;
use App\Models\Simrs\Departemen;
use App\Models\Simrs\Dokter;
use App\Models\Simrs\Poliklinik;
use App\Models\Mapping\EmployeeMap;
use App\Models\Mapping\Icd10Map;
use App\Models\Mapping\Icd9Map;
use App\Models\Mapping\IcdMmMap;
use App\Models\Mapping\IcdPmMap;
use App\Models\Mapping\IcdOTopographyMap;
use App\Models\Mapping\IcdOMorphologyMap;
use App\Models\Mapping\LabMap;
use App\Models\Mapping\LabItemMap;
use App\Models\Mapping\RadMap;
use App\Models\Mapping\MedicationMap;
use App\Models\Mapping\DeviceMap;
use App\Models\Mapping\ProcedureMap;
use App\Models\Mapping\SurgeryNoteMap;
use App\Models\Simrs\Alergi;
use App\Models\Simrs\AlergiReaksi;
use App\Models\Simrs\Pegawai;
use App\Models\Simrs\JnsPerawatan;
use App\Models\Simrs\JnsPerawatanInap;
use App\Models\Simrs\JnsPerawatanLab;
use App\Models\Simrs\JnsPerawatanRadiologi;
use App\Models\Simrs\TemplateLaboratorium;
use App\Models\Simrs\DataBarang;
use App\Models\Simrs\InventarisBarang;
use App\Models\Simrs\Pasien;
use App\Models\Simrs\PaketOperasi;
use App\Models\Bpjs\BpjsPatient;
use App\Models\Bpjs\BpjsIcd10;
use App\Models\SatuSehat\SatuSehatPatient;
use App\Models\Bpjs\BpjsIcd9;
use App\Models\Bpjs\BpjsOrganization;
use App\Models\Bpjs\BpjsProcedure;
use App\Models\Bpjs\BpjsPractitioner;
use App\Models\Bpjs\BpjsObservationLab;
use App\Models\Bpjs\BpjsObservationRadiology;
use App\Models\Bpjs\BpjsMedication;
use App\Models\Bpjs\BpjsVaccine;
use App\Models\Bpjs\BpjsDevice;
use App\Models\Bpjs\BpjsAllergy;
use App\Models\Bpjs\BpjsAllergyReaction;
use App\Models\Bpjs\BpjsHealthcareService;
use App\Models\SatuSehat\SatuSehatOrganization;
use App\Jobs\SyncBpjsPatientsJob;
use App\Models\SatuSehat\SatuSehatLocation;
use App\Models\SatuSehat\SatuSehatPractitioner;
use App\Models\SatuSehat\SatuSehatHealthcareService;
use App\Jobs\SyncBpjsIcd10Job;
use App\Jobs\SyncBpjsIcd9Job;
use App\Jobs\SyncBpjsProceduresJob;
use App\Jobs\SyncBpjsPractitionersJob;
use App\Jobs\SyncBpjsObservationLabsJob;
use App\Jobs\SyncBpjsObservationRadiologiesJob;
use App\Jobs\SyncBpjsMedicationsJob;
use App\Jobs\SyncBpjsVaccinesJob;
use App\Jobs\SyncBpjsDevicesJob;
use App\Jobs\SyncBpjsAllergiesJob;
use App\Jobs\SyncBpjsAllergyReactionsJob;
use App\Jobs\SyncBpjsOrganizationsJob;
use App\Jobs\SyncBpjsHealthcareServicesJob;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;

/** Halaman ringkasan status mapping local terminology. */
new #[Layout('layouts::app')] #[Title('Ringkasan Local Terminology')] class extends Component {
    public bool $showSyncModal = false;
    public string $syncGroup = '';
    public string $syncGroupLabel = '';

    public function openSyncModal(string $group, string $label): void
    {
        $this->syncGroup      = $group;
        $this->syncGroupLabel = $label;
        $this->showSyncModal  = true;
    }

    public function confirmSyncBpjs(): void
    {
        if ($this->syncGroup === 'clinical') {
            SyncBpjsIcd10Job::dispatch();
            SyncBpjsIcd9Job::dispatch();
            SyncBpjsProceduresJob::dispatch();
        } elseif ($this->syncGroup === 'practitioner') {
            SyncBpjsPractitionersJob::dispatch();
        } elseif ($this->syncGroup === 'observation') {
            SyncBpjsObservationLabsJob::dispatch();
            SyncBpjsObservationRadiologiesJob::dispatch();
        } elseif ($this->syncGroup === 'medication') {
            SyncBpjsMedicationsJob::dispatch();
            SyncBpjsVaccinesJob::dispatch();
        } elseif ($this->syncGroup === 'device') {
            SyncBpjsDevicesJob::dispatch();
        } elseif ($this->syncGroup === 'allergy') {
            SyncBpjsAllergiesJob::dispatch();
            SyncBpjsAllergyReactionsJob::dispatch();
        } elseif ($this->syncGroup === 'healthcare_service') {
            SyncBpjsHealthcareServicesJob::dispatch('all');
        } elseif ($this->syncGroup === 'patient') {
            SyncBpjsPatientsJob::dispatch();
        } elseif ($this->syncGroup === 'organization') {
            SyncBpjsOrganizationsJob::dispatch();
        }

        $this->showSyncModal = false;
        $this->toastSuccess("Sync BPJS UUID untuk {$this->syncGroupLabel} berhasil dijadwalkan.");
        $this->reset(['syncGroup', 'syncGroupLabel']);
    }

    public function with(): array
    {
        // --- Patient (SIMRS — try/catch karena beda koneksi) ---
        try {
            $patientTotal = Pasien::count();
        } catch (\Exception) {
            $patientTotal = 0;
        }
        $patientBpjs = BpjsPatient::count();
        $patientSs   = SatuSehatPatient::count();

        // --- Organization (SIMRS — try/catch karena beda koneksi) ---
        try {
            $deptTotal = Departemen::count();
        } catch (\Exception) {
            $deptTotal = 0;
        }
        $deptSs   = SatuSehatOrganization::count();
        $deptBpjs = BpjsOrganization::count();

        // --- Clinical ---
        $icd10Total    = Icd10::count();
        $icd10Mapped   = Icd10Map::count();
        $icd9Total     = Icd9::count();
        $icd9Mapped    = Icd9Map::count();
        $icdOTopTotal  = IcdOTopography::count();
        $icdOTopMapped = IcdOTopographyMap::count();
        $icdOMorTotal  = IcdOMorphology::count();
        $icdOMorMapped = IcdOMorphologyMap::count();
        $icdPmTotal    = IcdPm::count();
        $icdPmMapped   = IcdPmMap::count();
        $icdMmTotal    = IcdMm::count();
        $icdMmMapped   = IcdMmMap::count();

        // --- Practitioner (SIMRS — try/catch karena beda koneksi) ---
        try {
            $dokterTotal   = Dokter::where('status', '1')->count();
            $medisTotal    = Pegawai::where('stts_aktif', 'AKTIF')->where('bidang', 'Medis')->count();
            $kepTotal      = Pegawai::where('stts_aktif', 'AKTIF')->whereIn('bidang', ['Keperawatan', 'Kebidanan'])->count();
            $penTotal      = Pegawai::where('stts_aktif', 'AKTIF')->where('bidang', 'Penunjang Medis')->count();
            $nonMedisTotal = Pegawai::where('stts_aktif', 'AKTIF')->whereNotIn('bidang', ['Medis', 'Keperawatan', 'Kebidanan', 'Penunjang Medis'])->count();

            $medisNiks    = Pegawai::where('stts_aktif', 'AKTIF')->where('bidang', 'Medis')->pluck('nik')->toArray();
            $kepNiks      = Pegawai::where('stts_aktif', 'AKTIF')->whereIn('bidang', ['Keperawatan', 'Kebidanan'])->pluck('nik')->toArray();
            $penNiks      = Pegawai::where('stts_aktif', 'AKTIF')->where('bidang', 'Penunjang Medis')->pluck('nik')->toArray();
            $nonMedisNiks = Pegawai::where('stts_aktif', 'AKTIF')->whereNotIn('bidang', ['Medis', 'Keperawatan', 'Kebidanan', 'Penunjang Medis'])->pluck('nik')->toArray();
        } catch (\Exception) {
            $dokterTotal = $medisTotal = $kepTotal = $penTotal = $nonMedisTotal = 0;
            $medisNiks   = $kepNiks = $penNiks = $nonMedisNiks = [];
        }

        $dokterMapped  = DoctorMap::count();
        $medisMapped   = EmployeeMap::whereIn('employee_id', $medisNiks)->count();
        $kepMapped     = EmployeeMap::whereIn('employee_id', $kepNiks)->count();
        $penMapped     = EmployeeMap::whereIn('employee_id', $penNiks)->count();
        $nonMedisMapped = EmployeeMap::whereIn('employee_id', $nonMedisNiks)->count();

        $medisBpjs    = BpjsPractitioner::whereIn('identifier', $medisNiks)->count();
        $kepBpjs      = BpjsPractitioner::whereIn('identifier', $kepNiks)->count();
        $penBpjs      = BpjsPractitioner::whereIn('identifier', $penNiks)->count();
        $nonMedisBpjs = BpjsPractitioner::whereIn('identifier', $nonMedisNiks)->count();

        $medisSs    = SatuSehatPractitioner::whereIn('nik', $medisNiks)->count();
        $kepSs      = SatuSehatPractitioner::whereIn('nik', $kepNiks)->count();
        $penSs      = SatuSehatPractitioner::whereIn('nik', $penNiks)->count();
        $nonMedisSs = SatuSehatPractitioner::whereIn('nik', $nonMedisNiks)->count();

        // --- Tindakan (Procedure) ---
        try {
            $procJalanTotal = JnsPerawatan::count();
            $procInapTotal  = JnsPerawatanInap::count();
        } catch (\Exception) {
            $procJalanTotal = $procInapTotal = 0;
        }
        $procTotal   = $procJalanTotal + $procInapTotal;
        $procMapped  = ProcedureMap::where('source_table', 'jalan')->count() + ProcedureMap::where('source_table', 'inap')->count();
        $procBpjs    = BpjsProcedure::whereIn('type', ['jalan', 'ranap'])->count();

        // --- Observation ---
        try {
            $labTotal     = JnsPerawatanLab::count();
            $labItemTotal = TemplateLaboratorium::count();
            $radTotal     = JnsPerawatanRadiologi::count();
        } catch (\Exception) {
            $labTotal = $labItemTotal = $radTotal = 0;
        }
        $labMapped     = LabMap::count();
        $labItemMapped = LabItemMap::count();
        $radMapped     = RadMap::count();
        $labBpjs       = BpjsObservationLab::count();
        $labItemBpjs   = BpjsProcedure::where('type', 'item_lab')->count();
        $radBpjs       = BpjsObservationRadiology::count();

        // --- Medication ---
        try {
            $obatTotal   = DataBarang::whereDoesntHave('kategoriBarang', fn($q) => $q->where('nama', 'like', '%vaksin%'))->count();
            $vaksinTotal = DataBarang::whereHas('kategoriBarang', fn($q) => $q->where('nama', 'like', '%vaksin%'))->count();
            $obatKode    = DataBarang::whereDoesntHave('kategoriBarang', fn($q) => $q->where('nama', 'like', '%vaksin%'))->pluck('kode_brng')->toArray();
            $vaksinKode  = DataBarang::whereHas('kategoriBarang', fn($q) => $q->where('nama', 'like', '%vaksin%'))->pluck('kode_brng')->toArray();
        } catch (\Exception) {
            $obatTotal = $vaksinTotal = 0;
            $obatKode  = $vaksinKode = [];
        }
        $obatMapped   = MedicationMap::whereIn('local_code', $obatKode)->count();
        $vaksinMapped = MedicationMap::whereIn('local_code', $vaksinKode)->count();
        $obatBpjs     = BpjsMedication::whereIn('local_code', $obatKode)->count();
        $vaksinBpjs   = BpjsVaccine::count();

        // --- Device ---
        try {
            $alkesTotal = InventarisBarang::where('id_jenis', 'ALKES')->count();
        } catch (\Exception) {
            $alkesTotal = 0;
        }
        $alkesMapped = DeviceMap::count();
        $alkesBpjs   = BpjsDevice::count();

        // --- Allergy Intolerance ---
        try {
            $allergyTotal  = Alergi::count();
            $reactionTotal = AlergiReaksi::count();
        } catch (\Exception) {
            $allergyTotal = $reactionTotal = 0;
        }
        $allergyMapped  = AllergyMap::count();
        $reactionMapped = AllergyReactionMap::count();
        $allergyBpjs    = BpjsAllergy::count();
        $reactionBpjs   = BpjsAllergyReaction::count();

        // --- Healthcare Service ---
        try {
            $poliTotal    = Poliklinik::count();
            $bangsalTotal = Bangsal::count();
        } catch (\Exception) {
            $poliTotal = $bangsalTotal = 0;
        }
        $mappedPoliCodes = HsServiceItem::where('type', 'polyclinic')->distinct('local_code')->pluck('local_code')
            ->merge(HealthcareServiceMap::where('type', 'polyclinic')->whereNotNull('physical_type_code')->pluck('local_code'))
            ->unique();
        $poliMapped    = $mappedPoliCodes->count();
        $poliBpjs      = BpjsHealthcareService::where('type', 'poliklinik')->count();
        $poliSs        = SatuSehatHealthcareService::count();
        $bangsalMapped = SatuSehatLocation::where('type', 'ranap')->count();
        $bangsalBpjs   = BpjsHealthcareService::where('type', 'bangsal')->count();

        return [
            'patient' => [
                // "mapped" = terdaftar di Satu Sehat (IHS Number)
                ['label' => 'Pasien', 'total' => $patientTotal, 'mapped' => $patientSs, 'bpjs' => $patientBpjs, 'satusehat' => $patientSs, 'route' => 'local.patient'],
            ],
            'organization' => [
                // "mapped" = terdaftar di Satu Sehat (tujuan utama halaman ini)
                ['label' => 'Departemen', 'total' => $deptTotal, 'mapped' => $deptSs, 'bpjs' => $deptBpjs, 'satusehat' => $deptSs, 'route' => 'local.organization'],
            ],
            'source' => [
                ['label' => 'ICD-10', 'total' => $icd10Total, 'mapped' => $icd10Mapped, 'bpjs' => BpjsIcd10::count(), 'route' => 'local.source.icd10'],
                ['label' => 'ICD-9-CM', 'total' => $icd9Total, 'mapped' => $icd9Mapped, 'bpjs' => BpjsIcd9::count(), 'route' => 'local.source.icd9'],
                ['label' => 'ICD-O Topografi', 'total' => $icdOTopTotal, 'mapped' => $icdOTopMapped, 'route' => 'local.source.icd-o-topography'],
                ['label' => 'ICD-O Morfologi', 'total' => $icdOMorTotal, 'mapped' => $icdOMorMapped, 'route' => 'local.source.icd-o-morphology'],
                ['label' => 'ICD-PM', 'total' => $icdPmTotal, 'mapped' => $icdPmMapped, 'route' => 'local.source.icd-pm'],
                ['label' => 'ICD-MM', 'total' => $icdMmTotal, 'mapped' => $icdMmMapped, 'route' => 'local.source.icd-mm'],
            ],
            'clinical' => [
                ['label' => 'Tindakan', 'total' => $procTotal, 'mapped' => $procMapped, 'bpjs' => $procBpjs, 'route' => 'local.clinical.procedure'],
                ['label' => 'Operasi (SNOMED)', 'total' => PaketOperasi::count(), 'mapped' => ProcedureMap::where('source_table', 'operasi')->count(), 'bpjs' => BpjsProcedure::where('type', 'operasi')->count(), 'route' => 'local.clinical.surgery'],
                ['label' => 'Operasi (LOINC)', 'total' => PaketOperasi::count(), 'mapped' => SurgeryNoteMap::count(), 'route' => 'local.clinical.surgery'],
            ],
            'practitioner' => [
                ['label' => 'Dokter', 'total' => $dokterTotal, 'mapped' => $dokterMapped, 'route' => 'local.practitioner.doctor'],
                ['label' => 'Tenaga Medis', 'total' => $medisTotal, 'mapped' => $medisMapped, 'bpjs' => $medisBpjs, 'satusehat' => $medisSs, 'route' => 'local.practitioner.medical'],
                ['label' => 'Keperawatan', 'total' => $kepTotal, 'mapped' => $kepMapped, 'bpjs' => $kepBpjs, 'satusehat' => $kepSs, 'route' => 'local.practitioner.nursing'],
                ['label' => 'Penunjang Medis', 'total' => $penTotal, 'mapped' => $penMapped, 'bpjs' => $penBpjs, 'satusehat' => $penSs, 'route' => 'local.practitioner.support'],
                ['label' => 'Non Medis', 'total' => $nonMedisTotal, 'mapped' => $nonMedisMapped, 'bpjs' => $nonMedisBpjs, 'satusehat' => $nonMedisSs, 'route' => 'local.practitioner.non-medical'],
            ],
            'observation' => [
                ['label' => 'Lab Jenis', 'total' => $labTotal, 'mapped' => $labMapped, 'bpjs' => $labBpjs, 'route' => 'local.observation.laboratory'],
                ['label' => 'Lab Item', 'total' => $labItemTotal, 'mapped' => $labItemMapped, 'bpjs' => $labItemBpjs, 'route' => 'local.observation.laboratory'],
                ['label' => 'Radiologi', 'total' => $radTotal, 'mapped' => $radMapped, 'bpjs' => $radBpjs, 'route' => 'local.observation.radiology'],
            ],
            'medication' => [
                ['label' => 'Obat', 'total' => $obatTotal, 'mapped' => $obatMapped, 'bpjs' => $obatBpjs, 'route' => 'local.medication.medicine'],
                ['label' => 'Vaksin', 'total' => $vaksinTotal, 'mapped' => $vaksinMapped, 'bpjs' => $vaksinBpjs, 'route' => 'local.medication.vaccine'],
            ],
            'device' => [
                ['label' => 'Alat Kesehatan', 'total' => $alkesTotal, 'mapped' => $alkesMapped, 'bpjs' => $alkesBpjs, 'route' => 'local.device.equipment'],
            ],
            'allergy' => [
                ['label' => 'Alergi', 'total' => $allergyTotal, 'mapped' => $allergyMapped, 'bpjs' => $allergyBpjs, 'route' => 'local.allergy.allergy'],
                ['label' => 'Reaksi Alergi', 'total' => $reactionTotal, 'mapped' => $reactionMapped, 'bpjs' => $reactionBpjs, 'route' => 'local.allergy.reaction'],
            ],
            'healthcare_service' => [
                ['label' => 'Poliklinik', 'total' => $poliTotal, 'mapped' => $poliMapped, 'bpjs' => $poliBpjs, 'satusehat' => $poliSs, 'route' => 'local.healthcare-service.polyclinic'],
                ['label' => 'Bangsal', 'total' => $bangsalTotal, 'mapped' => $bangsalMapped, 'bpjs' => $bangsalBpjs, 'route' => 'local.healthcare-service.ward'],
            ],
        ];
    }
};
?>

<div>
    <x-ui.page-header title="Ringkasan Local Terminology"
        subtitle="Status kemajuan mapping kode terminologi lokal ke standar internasional" />

    @php
        $groups = [
            ['key' => 'patient',           'title' => 'Patient',              'color' => 'cyan',    'icon' => 'users',             'data' => $patient,           'syncable' => true],
            ['key' => 'organization',      'title' => 'Organization',         'color' => 'indigo',  'icon' => 'building-library',  'data' => $organization,      'syncable' => true],
            ['key' => 'source',            'title' => 'Source (ICD)',         'color' => 'zinc',    'icon' => 'document-text',     'data' => $source,            'syncable' => true],
            ['key' => 'clinical',          'title' => 'Clinical',             'color' => 'violet',  'icon' => 'clipboard-document-check', 'data' => $clinical,          'syncable' => true],
            ['key' => 'practitioner',      'title' => 'Practitioner',         'color' => 'sky',     'icon' => 'user-group',        'data' => $practitioner,      'syncable' => true],
            ['key' => 'observation',       'title' => 'Observation',          'color' => 'amber',   'icon' => 'beaker',            'data' => $observation,       'syncable' => true],
            ['key' => 'medication',        'title' => 'Medication',           'color' => 'emerald', 'icon' => 'archive-box',       'data' => $medication,        'syncable' => true],
            ['key' => 'device',            'title' => 'Device',               'color' => 'rose',    'icon' => 'cpu-chip',          'data' => $device,            'syncable' => true],
            ['key' => 'allergy',           'title' => 'Allergy Intolerance',  'color' => 'orange',  'icon' => 'shield-exclamation','data' => $allergy,           'syncable' => true],
            ['key' => 'healthcare_service','title' => 'Healthcare Service',   'color' => 'teal',    'icon' => 'building-office-2', 'data' => $healthcare_service,'syncable' => true],
        ];

        $colorMap = [
            'cyan'    => ['bg' => 'bg-cyan-500',    'light' => 'bg-cyan-50 dark:bg-cyan-900/20',      'text' => 'text-cyan-700 dark:text-cyan-300',      'ring' => 'ring-cyan-200 dark:ring-cyan-800',      'bar' => 'bg-cyan-400 dark:bg-cyan-500',      'hex' => '#06b6d4'],
            'indigo'  => ['bg' => 'bg-indigo-500',  'light' => 'bg-indigo-50 dark:bg-indigo-900/20',  'text' => 'text-indigo-700 dark:text-indigo-300',  'ring' => 'ring-indigo-200 dark:ring-indigo-800',  'bar' => 'bg-indigo-400 dark:bg-indigo-500',  'hex' => '#6366f1'],
            'violet'  => ['bg' => 'bg-violet-500',  'light' => 'bg-violet-50 dark:bg-violet-900/20',  'text' => 'text-violet-700 dark:text-violet-300',  'ring' => 'ring-violet-200 dark:ring-violet-800',  'bar' => 'bg-violet-400 dark:bg-violet-500',  'hex' => '#8b5cf6'],
            'sky'     => ['bg' => 'bg-sky-500',     'light' => 'bg-sky-50 dark:bg-sky-900/20',        'text' => 'text-sky-700 dark:text-sky-300',        'ring' => 'ring-sky-200 dark:ring-sky-800',        'bar' => 'bg-sky-400 dark:bg-sky-500',        'hex' => '#0ea5e9'],
            'amber'   => ['bg' => 'bg-amber-500',   'light' => 'bg-amber-50 dark:bg-amber-900/20',    'text' => 'text-amber-700 dark:text-amber-300',    'ring' => 'ring-amber-200 dark:ring-amber-800',    'bar' => 'bg-amber-400 dark:bg-amber-500',    'hex' => '#f59e0b'],
            'emerald' => ['bg' => 'bg-emerald-500', 'light' => 'bg-emerald-50 dark:bg-emerald-900/20','text' => 'text-emerald-700 dark:text-emerald-300','ring' => 'ring-emerald-200 dark:ring-emerald-800','bar' => 'bg-emerald-400 dark:bg-emerald-500','hex' => '#10b981'],
            'rose'    => ['bg' => 'bg-rose-500',    'light' => 'bg-rose-50 dark:bg-rose-900/20',      'text' => 'text-rose-700 dark:text-rose-300',      'ring' => 'ring-rose-200 dark:ring-rose-800',      'bar' => 'bg-rose-400 dark:bg-rose-500',      'hex' => '#f43f5e'],
            'orange'  => ['bg' => 'bg-orange-500',  'light' => 'bg-orange-50 dark:bg-orange-900/20',  'text' => 'text-orange-700 dark:text-orange-300',  'ring' => 'ring-orange-200 dark:ring-orange-800',  'bar' => 'bg-orange-400 dark:bg-orange-500',  'hex' => '#f97316'],
            'teal'    => ['bg' => 'bg-teal-500',    'light' => 'bg-teal-50 dark:bg-teal-900/20',      'text' => 'text-teal-700 dark:text-teal-300',      'ring' => 'ring-teal-200 dark:ring-teal-800',      'bar' => 'bg-teal-400 dark:bg-teal-500',      'hex' => '#14b8a6'],
            'zinc'    => ['bg' => 'bg-zinc-500',    'light' => 'bg-zinc-50 dark:bg-zinc-900/20',      'text' => 'text-zinc-700 dark:text-zinc-300',      'ring' => 'ring-zinc-200 dark:ring-zinc-800',      'bar' => 'bg-zinc-400 dark:bg-zinc-500',      'hex' => '#71717a'],
        ];
    @endphp

    @foreach ($groups as $group)
        @php
            $c           = $colorMap[$group['color']];
            $items       = $group['data'];
            $groupTotal  = collect($items)->sum('total');
            $groupMapped = collect($items)->sum('mapped');
            $groupPct    = $groupTotal > 0 ? round(($groupMapped / $groupTotal) * 100) : 0;
            $groupBpjs   = collect($items)->sum(fn($i) => $i['bpjs'] ?? 0);
            $groupSs     = collect($items)->filter(fn($i) => isset($i['satusehat']))->sum('satusehat');
            $hasBpjs     = collect($items)->contains(fn($i) => isset($i['bpjs']));
            $hasSs       = collect($items)->contains(fn($i) => isset($i['satusehat']));
        @endphp

        <div class="mb-8">
            {{-- Group header --}}
            <div class="flex items-center gap-3 mb-4 flex-wrap">
                <div
                    class="flex items-center justify-center w-9 h-9 rounded-xl {{ $c['light'] }} ring-1 {{ $c['ring'] }} shrink-0">
                    <flux:icon name="{{ $group['icon'] }}" class="w-4.5 h-4.5 {{ $c['text'] }}" />
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">{{ $group['title'] }}</h3>
                    <div class="flex items-center gap-3 flex-wrap mt-0.5">
                        <p class="text-xs text-zinc-400 dark:text-primary-dark-500">
                            {{ number_format($groupMapped) }} / {{ number_format($groupTotal) }} ter-mapping
                            ({{ $groupPct }}%)
                        </p>
                        @if ($hasBpjs)
                            <span
                                class="inline-flex items-center gap-1 text-xs text-cyan-600 dark:text-cyan-400 font-medium">
                                <flux:icon name="identification" class="w-3 h-3" />
                                {{ number_format($groupBpjs) }} BPJS UUID
                            </span>
                        @endif
                        @if ($hasSs)
                            <span
                                class="inline-flex items-center gap-1 text-xs text-emerald-600 dark:text-emerald-400 font-medium">
                                <flux:icon name="check-badge" class="w-3 h-3" />
                                {{ number_format($groupSs) }} Satu Sehat
                            </span>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    {{-- Progress bar ringkasan group --}}
                    <div class="hidden sm:flex items-center gap-2 w-40">
                        <div class="flex-1 h-1.5 rounded-full bg-zinc-100 dark:bg-primary-dark-700 overflow-hidden">
                            <div class="h-full rounded-full {{ $c['bar'] }} transition-all duration-500"
                                style="width: {{ $groupPct }}%"></div>
                        </div>
                        <span
                            class="text-xs font-medium tabular-nums {{ $c['text'] }} w-8 text-right">{{ $groupPct }}%</span>
                    </div>
                    @if ($group['syncable'])
                        <x-atoms.button
                            wire:click="openSyncModal('{{ $group['key'] }}', '{{ $group['title'] }}')"
                            variant="ghost" size="sm" icon="arrow-path">
                            Sync BPJS
                        </x-atoms.button>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                {{-- Kartu per item --}}
                <div class="lg:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach ($items as $item)
                        @php
                            $pct         = $item['total'] > 0 ? round(($item['mapped'] / $item['total']) * 100) : 0;
                            $hasBpjsItem = isset($item['bpjs']);
                            $hasSsItem   = isset($item['satusehat']);
                        @endphp
                        <a wire:navigate href="{{ route($item['route']) }}"
                            class="group flex flex-col gap-3 p-4 bg-white dark:bg-primary-dark-800 border border-zinc-200/80 dark:border-primary-dark-700/60 rounded-2xl shadow-sm hover:shadow-md transition-shadow duration-150">
                            <div class="flex items-start justify-between gap-2">
                                <span
                                    class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-200 leading-tight">{{ $item['label'] }}</span>
                                <span
                                    class="text-xs font-bold tabular-nums {{ $c['text'] }} shrink-0">{{ $pct }}%</span>
                            </div>
                            <div class="h-1.5 rounded-full bg-zinc-100 dark:bg-primary-dark-700 overflow-hidden">
                                <div class="h-full rounded-full {{ $c['bar'] }} transition-all duration-500"
                                    style="width: {{ $pct }}%"></div>
                            </div>
                            <div
                                class="flex items-center justify-between text-xs text-zinc-400 dark:text-primary-dark-500">
                                <span>
                                    <span
                                        class="font-semibold {{ $c['text'] }}">{{ number_format($item['mapped']) }}</span>
                                    ter-mapping
                                </span>
                                <span>{{ number_format($item['total']) }} total</span>
                            </div>
                            @if ($hasBpjsItem || $hasSsItem)
                                <div
                                    class="flex items-center gap-3 pt-1 border-t border-zinc-100 dark:border-primary-dark-700/50 flex-wrap">
                                    @if ($hasBpjsItem)
                                        <span
                                            class="inline-flex items-center gap-1 text-xs text-cyan-600 dark:text-cyan-400 font-medium">
                                            <flux:icon name="identification" class="w-3 h-3" />
                                            {{ number_format($item['bpjs']) }} BPJS UUID
                                        </span>
                                    @endif
                                    @if ($hasSsItem)
                                        <span
                                            class="inline-flex items-center gap-1 text-xs text-emerald-600 dark:text-emerald-400 font-medium">
                                            <flux:icon name="check-badge" class="w-3 h-3" />
                                            {{ number_format($item['satusehat']) }} Satu Sehat
                                        </span>
                                    @endif
                                </div>
                            @endif
                        </a>
                    @endforeach
                </div>

                {{-- Chart.js --}}
                <div
                    class="bg-white dark:bg-primary-dark-800 border border-zinc-200/80 dark:border-primary-dark-700/60 rounded-2xl shadow-sm p-4 flex flex-col">
                    <p
                        class="text-xs font-semibold text-zinc-400 dark:text-primary-dark-500 uppercase tracking-wider mb-3">
                        Perbandingan</p>
                    <div class="flex-1 min-h-0" style="min-height: {{ count($items) * 48 + 32 }}px">
                        <canvas id="chart-{{ $group['key'] }}"></canvas>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    {{-- Modal: Konfirmasi Sync BPJS UUID --}}
    <x-organisms.modal wire:model="showSyncModal" maxWidth="sm" title="">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Sync BPJS UUID</flux:heading>
                <flux:text class="mt-0.5">
                    Generate UUID BPJS untuk semua data
                    <span class="font-semibold">{{ $syncGroupLabel }}</span>?
                    Proses berjalan di background — halaman tidak perlu menunggu.
                </flux:text>
            </div>
        </div>

        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <x-atoms.button wire:click="$set('showSyncModal', false)" variant="ghost">Batal</x-atoms.button>
                <x-atoms.button wire:click="confirmSyncBpjs" variant="primary" icon="arrow-path">
                    <span wire:loading.remove wire:target="confirmSyncBpjs">Proses</span>
                    <span wire:loading wire:target="confirmSyncBpjs">Menjadwalkan...</span>
                </x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    @pushOnce('scripts')
        <script>
            function initSummaryCharts() {
                document.querySelectorAll('[id^="chart-"]').forEach(canvas => {
                    const existing = Chart.getChart(canvas);
                    if (existing) existing.destroy();
                });

                const isDark = () => document.documentElement.classList.contains('dark');
                const gridColor = () => isDark() ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.06)';
                const labelColor = () => isDark() ? '#a1a1aa' : '#71717a';

                /**
                 * @param {string[]} labels
                 * @param {number[]} totals
                 * @param {number[]} mappeds
                 * @param {number[]} bpjsData
                 * @param {string}   hex     – warna aksen grup
                 */
                const baseOpts = (labels, totals, mappeds, bpjsData, hex) => ({
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [
                            {
                                label: 'Total Data',
                                data: totals,
                                backgroundColor: isDark() ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)',
                                borderRadius: 4,
                                borderSkipped: false,
                                order: 3,
                            },
                            {
                                label: 'Ter-mapping',
                                data: mappeds,
                                backgroundColor: hex + 'cc',
                                borderRadius: 4,
                                borderSkipped: false,
                                order: 2,
                            },
                            {
                                label: 'BPJS UUID',
                                data: bpjsData,
                                backgroundColor: isDark() ? '#22d3ee80' : '#0891b280',
                                borderRadius: 4,
                                borderSkipped: false,
                                order: 1,
                            },
                        ],
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                labels: {
                                    color: labelColor(),
                                    font: { size: 10 },
                                    boxWidth: 10,
                                    padding: 8,
                                },
                            },
                            tooltip: {
                                callbacks: {
                                    label: ctx => {
                                        const total = ctx.chart.data.datasets[0].data[ctx.dataIndex] || 0;
                                        const pct   = total > 0 ? Math.round(ctx.parsed.x / total * 100) : 0;
                                        return ` ${ctx.dataset.label}: ${ctx.parsed.x.toLocaleString('id-ID')} (${pct}%)`;
                                    },
                                },
                            },
                        },
                        scales: {
                            x: {
                                grid: { color: gridColor() },
                                ticks: { color: labelColor(), font: { size: 11 } },
                            },
                            y: {
                                grid: { display: false },
                                ticks: { color: labelColor(), font: { size: 11 } },
                            },
                        },
                    },
                });

                @foreach ($groups as $group)
                    @php
                        $labels   = collect($group['data'])->pluck('label')->toJson();
                        $totals   = collect($group['data'])->pluck('total')->toJson();
                        $mappeds  = collect($group['data'])->pluck('mapped')->toJson();
                        $bpjsData = collect($group['data'])->map(fn($i) => $i['bpjs'] ?? 0)->toJson();
                        $hex      = $colorMap[$group['color']]['hex'];
                    @endphp
                    new Chart(
                        document.getElementById('chart-{{ $group['key'] }}'),
                        baseOpts(
                            {!! $labels !!},
                            {!! $totals !!},
                            {!! $mappeds !!},
                            {!! $bpjsData !!},
                            '{{ $hex }}'
                        )
                    );
                @endforeach
            }

            document.addEventListener('DOMContentLoaded', initSummaryCharts);
            document.addEventListener('livewire:navigated', initSummaryCharts);
        </script>
    @endPushOnce
</div>
