<?php

use App\Models\SatuSehat\SatuSehatEncounter;
use App\Models\SatuSehat\SatuSehatCondition;
use App\Models\SatuSehat\SatuSehatObservation;
use App\Models\SatuSehat\SatuSehatProcedure;
use App\Models\SatuSehat\SatuSehatComposition;
use App\Models\SatuSehat\SatuSehatMedication;
use App\Models\SatuSehat\SatuSehatMedicationRequest;
use App\Models\SatuSehat\SatuSehatMedicationDispense;
use App\Models\SatuSehat\SatuSehatAllergyIntolerance;
use App\Models\SatuSehat\SatuSehatServiceRequest;
use App\Models\SatuSehat\SatuSehatClinicalImpression;
use App\Models\SatuSehat\SatuSehatImmunization;
use App\Models\SatuSehat\SatuSehatCarePlan;
use App\Models\SatuSehat\SatuSehatSpecimen;
use App\Models\SatuSehat\SatuSehatDiagnosticReport;
use App\Models\SatuSehat\SatuSehatEpisodeOfCare;
use App\Models\SatuSehat\SatuSehatPatient;
use App\Models\SatuSehat\SatuSehatPractitioner;
use App\Models\SatuSehat\SatuSehatOrganization;
use App\Models\SatuSehat\SatuSehatLocation;
use App\Models\SatuSehat\SatuSehatHealthcareService;
use App\Models\SatuSehat\SatuSehatImagingStudy;
use App\Models\SatuSehat\SatuSehatMedicationAdministration;
use App\Models\SatuSehat\SatuSehatMedicationStatement;
use App\Models\SatuSehat\SatuSehatQuestionnaireResponse;
use App\Models\SatuSehat\SatuSehatDocumentReference;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Carbon\Carbon;

new #[Layout('layouts::app')] #[Title('Ringkasan Satu Sehat')] class extends Component {
    #[Url]
    public string $period = '30';

    private function getDateRange(): array
    {
        $end = Carbon::now();
        $start = match ($this->period) {
            '7' => Carbon::now()->subDays(7),
            '30' => Carbon::now()->subDays(30),
            '90' => Carbon::now()->subDays(90),
            '365' => Carbon::now()->subDays(365),
            'all' => null,
            default => Carbon::now()->subDays(30),
        };

        return [$start, $end];
    }

    private function countResource(string $modelClass, ?\Closure $callback = null): int
    {
        [$start, $end] = $this->getDateRange();
        $query = $modelClass::query();

        if ($start) {
            $query->where('created_at', '>=', $start);
        }

        if ($callback) {
            $callback($query);
        }

        return $query->count();
    }

    public function with(): array
    {
        [$start, $end] = $this->getDateRange();

        $periodLabel = match ($this->period) {
            '7' => '7 hari ke belakang',
            '30' => '30 hari ke belakang',
            '90' => '90 hari ke belakang',
            '365' => '1 tahun ke belakang',
            'all' => 'Semua data',
            default => '30 hari ke belakang',
        };

        $dateRange = $start ? $start->format('d M Y') . ' - ' . $end->format('d M Y') : 'Semua periode waktu';

        // Master data (total, tidak difilter periode)
        $masterData = [['label' => 'Patient', 'count' => SatuSehatPatient::count(), 'icon' => 'user'], ['label' => 'Practitioner', 'count' => SatuSehatPractitioner::count(), 'icon' => 'identification'], ['label' => 'Organization', 'count' => SatuSehatOrganization::count(), 'icon' => 'building-office'], ['label' => 'Location', 'count' => SatuSehatLocation::count(), 'icon' => 'map-pin'], ['label' => 'HealthcareService', 'count' => SatuSehatHealthcareService::count(), 'icon' => 'building-storefront']];

        // FHIR resource (difilter berdasarkan periode)
        $fhirResources = [
            [
                'label' => 'Encounter',
                'count' => $this->countResource(SatuSehatEncounter::class),
                'supported' => true,
                'sub' => [['label' => 'IGD', 'count' => $this->countResource(SatuSehatEncounter::class, fn($q) => $q->where('class', 'EMER'))], ['label' => 'Ralan', 'count' => $this->countResource(SatuSehatEncounter::class, fn($q) => $q->where('class', 'AMB'))], ['label' => 'Ranap', 'count' => $this->countResource(SatuSehatEncounter::class, fn($q) => $q->where('class', 'IMP'))]],
            ],

            ['label' => 'Condition', 'count' => $this->countResource(SatuSehatCondition::class), 'supported' => true],

            [
                'label' => 'Observation',
                'count' => $this->countResource(SatuSehatObservation::class),
                'supported' => true,
                'sub' => [
                    // Vital Signs (TTV)
                    ['label' => 'Suhu Badan', 'count' => $this->countResource(SatuSehatObservation::class, fn($q) => $q->where('code', '8310-5'))],
                    ['label' => 'Tekanan Darah', 'count' => $this->countResource(SatuSehatObservation::class, fn($q) => $q->whereIn('code', ['55284-4', '8480-6']))],
                    ['label' => 'Saturasi O₂ (SpO₂)', 'count' => $this->countResource(SatuSehatObservation::class, fn($q) => $q->whereIn('code', ['2708-6', '59408-5']))],
                    ['label' => 'Nadi', 'count' => $this->countResource(SatuSehatObservation::class, fn($q) => $q->where('code', '8867-4'))],
                    ['label' => 'Frekuensi Napas', 'count' => $this->countResource(SatuSehatObservation::class, fn($q) => $q->where('code', '9279-1'))],
                    // Antropometri
                    ['label' => 'Tinggi Badan', 'count' => $this->countResource(SatuSehatObservation::class, fn($q) => $q->where('code', '8302-2'))],
                    ['label' => 'Berat Badan', 'count' => $this->countResource(SatuSehatObservation::class, fn($q) => $q->where('code', '29463-7'))],
                    ['label' => 'Lingkar Kepala', 'count' => $this->countResource(SatuSehatObservation::class, fn($q) => $q->where('code', '8287-5'))],
                    ['label' => 'Lingkar Pinggang', 'count' => $this->countResource(SatuSehatObservation::class, fn($q) => $q->where('code', '56086-2'))],
                    ['label' => 'IMT / BMI', 'count' => $this->countResource(SatuSehatObservation::class, fn($q) => $q->where('code', '39156-5'))],
                    // Gula Darah
                    ['label' => 'GSV (Glukosa Sewaktu)', 'count' => $this->countResource(SatuSehatObservation::class, fn($q) => $q->where('code', '2339-0'))],
                    ['label' => 'GDP (Glukosa Puasa)', 'count' => $this->countResource(SatuSehatObservation::class, fn($q) => $q->where('code', '1558-6'))],
                    ['label' => 'HbA1c', 'count' => $this->countResource(SatuSehatObservation::class, fn($q) => $q->where('code', '4548-4'))],
                    // Lainnya
                    ['label' => 'Laboratorium', 'count' => $this->countResource(SatuSehatObservation::class, fn($q) => $q->where('category', 'laboratory'))],
                    ['label' => 'Radiologi', 'count' => $this->countResource(SatuSehatObservation::class, fn($q) => $q->where('category', 'imaging'))],
                ],
            ],

            [
                'label' => 'Procedure',
                'count' => $this->countResource(SatuSehatProcedure::class),
                'supported' => true,
                'sub' => [
                    ['label' => 'Prosedur ICD-9', 'count' => $this->countResource(SatuSehatProcedure::class, fn($q) => $q->where('category', 'icd-9'))],
                    ['label' => 'Tindakan Rawat Jalan', 'count' => $this->countResource(SatuSehatProcedure::class, fn($q) => $q->where('category', 'tindakan-rj'))],
                    ['label' => 'Tindakan Rawat Inap', 'count' => $this->countResource(SatuSehatProcedure::class, fn($q) => $q->where('category', 'tindakan-rl'))],
                ],
            ],
            [
                'label'     => 'Composition',
                'count'     => $this->countResource(SatuSehatComposition::class),
                'supported' => true,
                'sub'       => [
                    ['label' => 'Resume Rawat Jalan',  'count' => $this->countResource(SatuSehatComposition::class, fn($q) => $q->where('composition_type', SatuSehatComposition::TYPE_RESUME_RALAN))],
                    ['label' => 'Resume Rawat Inap',   'count' => $this->countResource(SatuSehatComposition::class, fn($q) => $q->where('composition_type', SatuSehatComposition::TYPE_RESUME_RANAP))],
                    ['label' => 'Catatan Gizi ADIME',  'count' => $this->countResource(SatuSehatComposition::class, fn($q) => $q->where('composition_type', SatuSehatComposition::TYPE_CATATAN_GIZI))],
                    ['label' => 'Keperawatan Ralan',   'count' => $this->countResource(SatuSehatComposition::class, fn($q) => $q->where('composition_type', SatuSehatComposition::TYPE_RESUME_KEPERAWATAN_RALAN))],
                    ['label' => 'Keperawatan Ranap',   'count' => $this->countResource(SatuSehatComposition::class, fn($q) => $q->where('composition_type', SatuSehatComposition::TYPE_RESUME_KEPERAWATAN_RANAP))],
                    ['label' => 'Resume Farmasi',      'count' => $this->countResource(SatuSehatComposition::class, fn($q) => $q->where('composition_type', SatuSehatComposition::TYPE_RESUME_FARMASI))],
                ],
            ],

            ['label' => 'Medication', 'count' => $this->countResource(SatuSehatMedication::class), 'supported' => true],
            ['label' => 'MedicationRequest', 'count' => $this->countResource(SatuSehatMedicationRequest::class), 'supported' => true],
            ['label' => 'MedicationDispense', 'count' => $this->countResource(SatuSehatMedicationDispense::class), 'supported' => true],
            ['label' => 'MedicationStatement', 'count' => $this->countResource(SatuSehatMedicationStatement::class), 'supported' => true],
            [
                'label' => 'MedicationAdministration',
                'count' => $this->countResource(SatuSehatMedicationAdministration::class),
                'supported' => true,
                'sub' => [['label' => 'Rawat Jalan (outpatient)', 'count' => $this->countResource(SatuSehatMedicationAdministration::class, fn($q) => $q->where('category', 'outpatient'))], ['label' => 'Rawat Inap (inpatient)', 'count' => $this->countResource(SatuSehatMedicationAdministration::class, fn($q) => $q->where('category', 'inpatient'))], ['label' => 'Komunitas (community)', 'count' => $this->countResource(SatuSehatMedicationAdministration::class, fn($q) => $q->where('category', 'community'))]],
            ],

            [
                'label' => 'AllergyIntolerance',
                'count' => $this->countResource(SatuSehatAllergyIntolerance::class),
                'supported' => true,
                'sub' => [['label' => 'Obat-obatan (medication)', 'count' => $this->countResource(SatuSehatAllergyIntolerance::class, fn($q) => $q->where('category', 'medication'))], ['label' => 'Makanan (food)', 'count' => $this->countResource(SatuSehatAllergyIntolerance::class, fn($q) => $q->where('category', 'food'))], ['label' => 'Lingkungan (environment)', 'count' => $this->countResource(SatuSehatAllergyIntolerance::class, fn($q) => $q->where('category', 'environment'))], ['label' => 'Biologis (biologic)', 'count' => $this->countResource(SatuSehatAllergyIntolerance::class, fn($q) => $q->where('category', 'biologic'))]],
            ],

            [
                'label' => 'ServiceRequest',
                'count' => $this->countResource(SatuSehatServiceRequest::class),
                'supported' => true,
                'sub' => [['label' => 'Laboratorium', 'count' => $this->countResource(SatuSehatServiceRequest::class, fn($q) => $q->where(fn($sq) => $sq->where('category', 'laboratory')->orWhere('category', 'like', '%lab%')->orWhere('category', '108252007')))], ['label' => 'Radiologi', 'count' => $this->countResource(SatuSehatServiceRequest::class, fn($q) => $q->where(fn($sq) => $sq->where('category', 'imaging')->orWhere('category', 'like', '%rad%')->orWhere('category', '363679005')))]],
            ],

            ['label' => 'ClinicalImpression', 'count' => $this->countResource(SatuSehatClinicalImpression::class), 'supported' => true],
            ['label' => 'Immunization', 'count' => $this->countResource(SatuSehatImmunization::class), 'supported' => true],
            ['label' => 'CarePlan', 'count' => $this->countResource(SatuSehatCarePlan::class), 'supported' => true],

            [
                'label' => 'Specimen',
                'count' => $this->countResource(SatuSehatSpecimen::class),
                'supported' => true,
                'sub' => [['label' => 'Laboratorium', 'count' => $this->countResource(SatuSehatSpecimen::class, fn($q) => $q->whereHas('serviceRequest', fn($sq) => $sq->where(fn($r) => $r->where('category', 'laboratory')->orWhere('category', 'like', '%lab%')->orWhere('category', '108252007'))))], ['label' => 'Radiologi', 'count' => $this->countResource(SatuSehatSpecimen::class, fn($q) => $q->whereHas('serviceRequest', fn($sq) => $sq->where(fn($r) => $r->where('category', 'imaging')->orWhere('category', 'like', '%rad%')->orWhere('category', '363679005'))))]],
            ],

            [
                'label' => 'DiagnosticReport',
                'count' => $this->countResource(SatuSehatDiagnosticReport::class),
                'supported' => true,
                'sub' => [['label' => 'Laboratorium', 'count' => $this->countResource(SatuSehatDiagnosticReport::class, fn($q) => $q->where(fn($sq) => $sq->where('category', 'LAB')->orWhere('category', 'like', '%lab%')))], ['label' => 'Radiologi', 'count' => $this->countResource(SatuSehatDiagnosticReport::class, fn($q) => $q->where(fn($sq) => $sq->where('category', 'RAD')->orWhere('category', 'like', '%rad%')))]],
            ],

            [
                'label' => 'EpisodeOfCare',
                'count' => $this->countResource(SatuSehatEpisodeOfCare::class),
                'supported' => true,
                'sub' => [
                    ['label' => 'Diabetes Program (diab)', 'count' => $this->countResource(SatuSehatEpisodeOfCare::class, fn($q) => $q->where('type_code', 'diab'))],
                    ['label' => 'Drug & Alcohol Rehab (da)', 'count' => $this->countResource(SatuSehatEpisodeOfCare::class, fn($q) => $q->where('type_code', 'da'))],
                    ['label' => 'Community Aged Care (cacp)', 'count' => $this->countResource(SatuSehatEpisodeOfCare::class, fn($q) => $q->where('type_code', 'cacp'))],
                    ['label' => 'Home & Comm. Care (hacc)', 'count' => $this->countResource(SatuSehatEpisodeOfCare::class, fn($q) => $q->where('type_code', 'hacc'))],
                    ['label' => 'Post Acute Care (pac)', 'count' => $this->countResource(SatuSehatEpisodeOfCare::class, fn($q) => $q->where('type_code', 'pac'))],
                    ['label' => 'Cancer Management (CNC)', 'count' => $this->countResource(SatuSehatEpisodeOfCare::class, fn($q) => $q->where('type_code', 'CNC'))],
                    ['label' => 'Coronary Arterial (CAD)', 'count' => $this->countResource(SatuSehatEpisodeOfCare::class, fn($q) => $q->where('type_code', 'CAD'))],
                    ['label' => 'Cerebrovascular (CVD)', 'count' => $this->countResource(SatuSehatEpisodeOfCare::class, fn($q) => $q->where('type_code', 'CVD'))],
                    ['label' => 'Chronic Kidney (CKD)', 'count' => $this->countResource(SatuSehatEpisodeOfCare::class, fn($q) => $q->where('type_code', 'CKD'))],
                    ['label' => 'TB Sensitif Obat (TB-SO)', 'count' => $this->countResource(SatuSehatEpisodeOfCare::class, fn($q) => $q->where('type_code', 'TB-SO'))],
                    ['label' => 'TB Resisten Obat (TB-RO)', 'count' => $this->countResource(SatuSehatEpisodeOfCare::class, fn($q) => $q->where('type_code', 'TB-RO'))],
                    ['label' => 'Antenatal Care (ANC)', 'count' => $this->countResource(SatuSehatEpisodeOfCare::class, fn($q) => $q->where('type_code', 'ANC'))],
                    ['label' => 'Neonate', 'count' => $this->countResource(SatuSehatEpisodeOfCare::class, fn($q) => $q->where('type_code', 'Neonate'))],
                    ['label' => 'Postnatal Care (PNC)', 'count' => $this->countResource(SatuSehatEpisodeOfCare::class, fn($q) => $q->where('type_code', 'PNC'))],
                ],
            ],

            [
                'label' => 'ImagingStudy',
                'count' => $this->countResource(SatuSehatImagingStudy::class),
                'supported' => true,
                'sub' => [
                    ['label' => 'Computed Radiography (CR)', 'count' => $this->countResource(SatuSehatImagingStudy::class, fn($q) => $q->where('modality_code', 'CR'))],
                    ['label' => 'Digital Radiography (DX)', 'count' => $this->countResource(SatuSehatImagingStudy::class, fn($q) => $q->where('modality_code', 'DX'))],
                    ['label' => 'X-ray Umum (XR)', 'count' => $this->countResource(SatuSehatImagingStudy::class, fn($q) => $q->where('modality_code', 'XR'))],
                    ['label' => 'Computed Tomography (CT)', 'count' => $this->countResource(SatuSehatImagingStudy::class, fn($q) => $q->where('modality_code', 'CT'))],
                    ['label' => 'Magnetic Resonance (MR)', 'count' => $this->countResource(SatuSehatImagingStudy::class, fn($q) => $q->where('modality_code', 'MR'))],
                    ['label' => 'Ultrasonography (US)', 'count' => $this->countResource(SatuSehatImagingStudy::class, fn($q) => $q->where('modality_code', 'US'))],
                    ['label' => 'Mammography (MG)', 'count' => $this->countResource(SatuSehatImagingStudy::class, fn($q) => $q->where('modality_code', 'MG'))],
                    ['label' => 'X-ray Angiography (XA)', 'count' => $this->countResource(SatuSehatImagingStudy::class, fn($q) => $q->where('modality_code', 'XA'))],
                    ['label' => 'Radiofluoroscopy (RF)', 'count' => $this->countResource(SatuSehatImagingStudy::class, fn($q) => $q->where('modality_code', 'RF'))],
                    ['label' => 'Digital Subtraction Angio (DS)', 'count' => $this->countResource(SatuSehatImagingStudy::class, fn($q) => $q->where('modality_code', 'DS'))],
                    ['label' => 'DEXA Scan (DXA)', 'count' => $this->countResource(SatuSehatImagingStudy::class, fn($q) => $q->where('modality_code', 'DXA'))],
                    ['label' => 'Nuclear Medicine (NM)', 'count' => $this->countResource(SatuSehatImagingStudy::class, fn($q) => $q->where('modality_code', 'NM'))],
                    ['label' => 'PET Scan (PT)', 'count' => $this->countResource(SatuSehatImagingStudy::class, fn($q) => $q->where('modality_code', 'PT'))],
                    ['label' => 'SPECT (ST)', 'count' => $this->countResource(SatuSehatImagingStudy::class, fn($q) => $q->where('modality_code', 'ST'))],
                    ['label' => 'Intravascular Ultrasound (IVUS)', 'count' => $this->countResource(SatuSehatImagingStudy::class, fn($q) => $q->where('modality_code', 'IVUS'))],
                    ['label' => 'Optical Coherence Tomography (OCT)', 'count' => $this->countResource(SatuSehatImagingStudy::class, fn($q) => $q->where('modality_code', 'OCT'))],
                    ['label' => 'Ophthalmic Photography (OP)', 'count' => $this->countResource(SatuSehatImagingStudy::class, fn($q) => $q->where('modality_code', 'OP'))],
                    ['label' => 'Endoscopy (ES)', 'count' => $this->countResource(SatuSehatImagingStudy::class, fn($q) => $q->where('modality_code', 'ES'))],
                    ['label' => 'Electrocardiography (ECG)', 'count' => $this->countResource(SatuSehatImagingStudy::class, fn($q) => $q->where('modality_code', 'ECG'))],
                ],
            ],

            [
                'label' => 'QuestionnaireResponse',
                'count' => $this->countResource(SatuSehatQuestionnaireResponse::class),
                'supported' => true,
                'sub' => [
                    ['label' => 'Q0001 – Asesmen Awal IGD', 'count' => $this->countResource(SatuSehatQuestionnaireResponse::class, fn($q) => $q->where('questionnaire', 'like', '%Q0001%'))],
                    ['label' => 'Q0002 – Asesmen Awal Ranap', 'count' => $this->countResource(SatuSehatQuestionnaireResponse::class, fn($q) => $q->where('questionnaire', 'like', '%Q0002%'))],
                    ['label' => 'Q0007 – Telaah Resep', 'count' => $this->countResource(SatuSehatQuestionnaireResponse::class, fn($q) => $q->where('questionnaire', 'like', '%Q0007%'))],
                    ['label' => 'Q0008 – Rekonsiliasi Obat', 'count' => $this->countResource(SatuSehatQuestionnaireResponse::class, fn($q) => $q->where('questionnaire', 'like', '%Q0008%'))],
                    ['label' => 'Lainnya', 'count' => $this->countResource(SatuSehatQuestionnaireResponse::class, fn($q) => $q->where(fn($sq) => $sq->where('questionnaire', 'not like', '%Q0001%')->where('questionnaire', 'not like', '%Q0002%')->where('questionnaire', 'not like', '%Q0007%')->where('questionnaire', 'not like', '%Q0008%')))],
                ],
            ],

            [
                'label' => 'DocumentReference',
                'count' => $this->countResource(SatuSehatDocumentReference::class),
                'supported' => true,
                'sub' => [
                    ['label' => 'Resep Farmasi (prescription)', 'count' => $this->countResource(SatuSehatDocumentReference::class, fn($q) => $q->where('doc_type', SatuSehatDocumentReference::TYPE_PRESCRIPTION))],
                ],
            ],
        ];

        // Batch sync terbaru (Patient & Practitioner)
        $syncBatches = DB::table('job_batches')->where(fn($q) => $q->where('name', 'like', 'Patient Sync%')->orWhere('name', 'like', 'Practitioner Sync%'))->orderByDesc('created_at')->limit(10)->get()->map(
            fn($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'total' => $b->total_jobs,
                'pending' => $b->pending_jobs,
                'failed' => $b->failed_jobs,
                'processed' => $b->total_jobs - $b->pending_jobs,
                'progress' => $b->total_jobs > 0 ? round((($b->total_jobs - $b->pending_jobs) / $b->total_jobs) * 100) : 100,
                'status' => $b->cancelled_at ? 'cancelled' : ($b->finished_at ? ($b->failed_jobs > 0 ? 'partial' : 'done') : 'running'),
                'created_at' => Carbon::createFromTimestamp($b->created_at)->format('d M Y H:i'),
                'finished_at' => $b->finished_at ? Carbon::createFromTimestamp($b->finished_at)->format('d M Y H:i') : null,
            ],
        );

        return [
            'masterData' => $masterData,
            'fhirResources' => $fhirResources,
            'periodLabel' => $periodLabel,
            'dateRange' => $dateRange,
            'syncBatches' => $syncBatches,
        ];
    }
};
?>

<div class="pb-12 space-y-8 min-h-[calc(100vh-100px)]">
    {{-- Header Section --}}
    <x-ui.page-header title="Ringkasan Satu Sehat"
        subtitle="Analitik data FHIR dan pemantauan status sinkronisasi integrasi platform.">
        <x-slot:actions>
            <div class="flex flex-col items-end gap-2 shrink-0">
                <div class="w-full sm:min-w-[220px]">
                    <flux:select wire:model.live="period" size="sm" icon="calendar">
                        <flux:select.option value="7">7 Hari Terakhir</flux:select.option>
                        <flux:select.option value="30">30 Hari Terakhir</flux:select.option>
                        <flux:select.option value="90">90 Hari Terakhir</flux:select.option>
                        <flux:select.option value="365">1 Tahun Terakhir</flux:select.option>
                        <flux:select.option value="all">Semua Waktu</flux:select.option>
                    </flux:select>
                </div>
                <span
                    class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30 px-2.5 py-1 rounded-full border border-emerald-100 dark:border-emerald-800/50">
                    {{ $dateRange }}
                </span>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Master Data Grid --}}
    <section>
        <div class="flex items-center gap-2 mb-4">
            <h2 class="text-lg font-bold text-zinc-800 dark:text-primary-dark-100 flex items-center gap-2">
                <flux:icon name="circle-stack" class="w-5 h-5 text-emerald-500" />
                Data Induk (Master)
            </h2>
            <div class="h-px flex-1 bg-gradient-to-r from-zinc-200 to-transparent dark:from-primary-dark-800 ml-4">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
            @foreach ($masterData as $item)
                <x-organisms.stat-card :title="$item['label']" :value="number_format($item['count'])" :icon="$item['icon']" color="emerald" />
            @endforeach
        </div>
    </section>

    {{-- FHIR Resources Grid --}}
    <section>
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <h2 class="text-lg font-bold text-zinc-800 dark:text-primary-dark-100 flex items-center gap-2">
                    <flux:icon name="arrows-right-left" class="w-5 h-5 text-blue-500" />
                    Transaksi FHIR
                </h2>
                <span
                    class="ml-2 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-blue-700 bg-blue-100 rounded-lg dark:bg-blue-900/50 dark:text-blue-300 ring-1 ring-blue-200 dark:ring-blue-800">
                    Periode {{ $periodLabel }}
                </span>
            </div>

            {{-- Legend Badge --}}
            <div class="flex items-center gap-2">
                <div
                    class="flex items-center gap-1.5 px-2 py-1 text-xs font-semibold text-sky-700 bg-sky-50 dark:bg-sky-900/30 dark:text-sky-300 rounded-lg border border-sky-200 dark:border-sky-800 shadow-sm">
                    <flux:icon name="check-badge" variant="solid" class="w-4 h-4 text-sky-500 dark:text-sky-400" />
                    <span>Terdata di Platform Satu Sehat</span>
                </div>
            </div>
        </div>

        <div class="columns-1 gap-5 sm:columns-2 lg:columns-3 xl:columns-4 [column-fill:_balance]">
            @foreach ($fhirResources as $resource)
                <div class="mb-5 break-inside-avoid-column">
                    <x-organisms.stat-card-fhir :title="$resource['label']" :value="$resource['count']" :supported="$resource['supported'] ?? false">
                        @if (!empty($resource['sub']))
                            @foreach ($resource['sub'] as $sub)
                                <div class="flex items-center justify-between group/sub">
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="w-1.5 h-1.5 rounded-full {{ $sub['count'] > 0 ? 'bg-sky-400 dark:bg-sky-500' : 'bg-zinc-200 dark:bg-primary-dark-700' }} transition-colors">
                                        </div>
                                        <span
                                            class="text-xs font-medium transition-colors text-zinc-600 dark:text-primary-dark-400 group-hover/sub:text-zinc-900 dark:group-hover/sub:text-primary-dark-200">
                                            {{ $sub['label'] }}
                                        </span>
                                    </div>
                                    <span
                                        class="px-2 py-0.5 text-xs font-bold rounded-md bg-zinc-50 dark:bg-primary-dark-800/80 {{ $sub['count'] > 0 ? 'text-sky-700 dark:text-sky-400 border border-sky-100 dark:border-sky-900/50' : 'text-zinc-400 dark:text-primary-dark-500 border border-transparent' }}">
                                        {{ $sub['count'] > 0 ? number_format($sub['count']) : '-' }}
                                    </span>
                                </div>
                            @endforeach
                        @endif
                    </x-organisms.stat-card-fhir>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Batch Sync Progress --}}
    @if ($syncBatches->isNotEmpty())
        <x-organisms.data-panel title="Riwayat Batch Sync" subtitle="Log pekerjaan sinkronisasi latar belakang"
            icon="arrow-path-rounded-square">
            <x-slot:action>
                <span
                    class="px-3 py-1 text-xs font-bold text-zinc-500 bg-zinc-100 rounded-full dark:bg-primary-dark-800 dark:text-primary-dark-300">
                    {{ $syncBatches->count() }} Terakhir
                </span>
            </x-slot:action>

            <div class="p-5 space-y-4">
                @foreach ($syncBatches as $batch)
                    @php
                        $isPatient = str_starts_with($batch['name'], 'Patient');
                        $statusColor = match ($batch['status']) {
                            'done' => 'text-emerald-600 dark:text-emerald-400',
                            'running' => 'text-blue-600 dark:text-blue-400 animate-pulse',
                            'partial' => 'text-amber-600 dark:text-amber-400',
                            'cancelled' => 'text-zinc-400 dark:text-primary-dark-500',
                            default => 'text-zinc-400',
                        };
                        $statusLabel = match ($batch['status']) {
                            'done' => 'Selesai',
                            'running' => 'Berjalan',
                            'partial' => 'Selesai (Ada Error)',
                            'cancelled' => 'Dibatalkan',
                            default => '-',
                        };
                        $barColor = match ($batch['status']) {
                            'done' => 'bg-emerald-500',
                            'running' => 'bg-blue-500',
                            'partial' => 'bg-amber-500',
                            'cancelled' => 'bg-zinc-400',
                            default => 'bg-zinc-300',
                        };
                    @endphp
                    <div
                        class="px-5 py-4 transition-colors border shadow-sm rounded-xl border-zinc-200/70 dark:border-primary-dark-700/60 bg-zinc-50/50 dark:bg-primary-dark-950/40 hover:bg-zinc-50 dark:hover:bg-primary-dark-900">
                        <div class="flex flex-wrap items-center justify-between gap-4 mb-3">
                            <div class="flex items-center gap-3">
                                <flux:badge size="sm" color="{{ $isPatient ? 'violet' : 'emerald' }}"
                                    class="uppercase tracking-widest text-[10px]">
                                    {{ $isPatient ? 'Patient' : 'PRACTITIONER' }}
                                </flux:badge>
                                <span
                                    class="font-bold text-sm text-zinc-800 dark:text-primary-dark-200">{{ $batch['name'] }}</span>
                            </div>
                            <div class="flex items-center gap-4 text-xs">
                                <span
                                    class="flex items-center gap-1.5 {{ $statusColor }} bg-opacity-10 px-2 py-0.5 rounded-md font-bold">
                                    @if ($batch['status'] === 'running')
                                        <flux:icon name="arrow-path" class="w-3.5 h-3.5 animate-spin" />
                                    @endif
                                    {{ $statusLabel }}
                                </span>
                                @if ($batch['status'] === 'running')
                                    <span
                                        class="font-bold text-blue-600 dark:text-blue-400">{{ $batch['progress'] }}%</span>
                                @endif
                                <span class="text-zinc-400 dark:text-primary-dark-500 flex items-center gap-1">
                                    <flux:icon name="clock" class="w-3.5 h-3.5" />
                                    {{ $batch['created_at'] }}
                                </span>
                            </div>
                        </div>

                        {{-- Progress bar --}}
                        <div
                            class="w-full h-2 mb-4 overflow-hidden shadow-inner rounded-full bg-zinc-200 dark:bg-primary-dark-800/80">
                            <div class="{{ $barColor }} h-2 transition-all duration-700 ease-in-out relative flex items-center justify-end pr-1"
                                style="width: {{ $batch['progress'] }}%">
                                @if ($batch['status'] === 'running')
                                    <div class="w-full h-full absolute top-0 left-0 bg-white/20 animate-pulse"></div>
                                @endif
                            </div>
                        </div>

                        {{-- Stats --}}
                        <div class="flex flex-wrap gap-x-6 gap-y-2 text-xs text-zinc-500 dark:text-primary-dark-400">
                            <span class="flex items-center gap-1">Total: <strong
                                    class="text-zinc-800 dark:text-primary-dark-200 font-mono">{{ number_format($batch['total']) }}</strong></span>
                            <span class="flex items-center gap-1">Selesai: <strong
                                    class="text-emerald-600 dark:text-emerald-400 font-mono">{{ number_format($batch['processed']) }}</strong></span>
                            <span class="flex items-center gap-1">Pending: <strong
                                    class="text-zinc-800 dark:text-primary-dark-200 font-mono">{{ number_format($batch['pending']) }}</strong></span>
                            @if ($batch['failed'] > 0)
                                <span class="flex items-center gap-1">Gagal: <strong
                                        class="text-red-500 dark:text-red-400 font-mono">{{ number_format($batch['failed']) }}</strong></span>
                            @endif
                            @if ($batch['finished_at'])
                                <span class="pl-4 ml-2 border-l border-zinc-200 dark:border-primary-dark-700">Dimuat:
                                    <strong
                                        class="text-zinc-600 dark:text-primary-dark-300">{{ $batch['finished_at'] }}</strong></span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </x-organisms.data-panel>
    @endif
</div>
