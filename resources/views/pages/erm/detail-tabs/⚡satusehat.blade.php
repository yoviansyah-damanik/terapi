<?php

use App\Models\Simrs\RegPeriksa;
use Livewire\Component;
use App\Models\SatuSehat\SatuSehatAllergyIntolerance;
use App\Models\SatuSehat\SatuSehatClinicalImpression;
use App\Models\SatuSehat\SatuSehatComposition;
use App\Models\SatuSehat\SatuSehatCondition;
use App\Models\SatuSehat\SatuSehatDiagnosticReport;
use App\Models\SatuSehat\SatuSehatEncounter;
use App\Models\SatuSehat\SatuSehatImmunization;
use App\Models\SatuSehat\SatuSehatImagingStudy;
use App\Models\SatuSehat\SatuSehatMedicationDispense;
use App\Models\SatuSehat\SatuSehatMedicationRequest;
use App\Models\SatuSehat\SatuSehatObservation;
use App\Models\Simrs\DiagnosaPasien;
use App\Models\Simrs\ProsedurPasien;
use App\Models\Simrs\PemeriksaanRalan;
use App\Models\Simrs\PemeriksaanRanap;
use App\Models\Simrs\Operasi;
use App\Models\Simrs\LaporanOperasi;
use App\Models\Simrs\DetailPemberianObat;
use App\Models\Simrs\ResepPulang;
use App\Models\Simrs\PermintaanLab;
use App\Models\Simrs\PermintaanRadiologi;
use App\Models\Simrs\AlergiPasien;
use App\Models\Simrs\CatatanAdimeGizi;
use App\Models\SatuSehat\SatuSehatProcedure;
use App\Models\SatuSehat\SatuSehatServiceRequest;
use App\Models\SatuSehat\SatuSehatMedicationStatement;
use App\Models\SatuSehat\SatuSehatMedicationAdministration;
use App\Models\SatuSehat\SatuSehatCarePlan;
use App\Models\SatuSehat\SatuSehatDocumentReference;
use App\Models\SatuSehat\SatuSehatQuestionnaireResponse;
use App\Models\SatuSehat\SatuSehatSpecimen;
use App\Models\SatuSehat\SatuSehatLog;
use App\Models\SatuSehat\SatuSehatEpisodeOfCare;
use App\Models\SatuSehat\SatuSehatBundle;
use App\Models\Dicom\DicomStudy;
use App\Models\Dicom\DicomRouterResponse;
use App\Services\SatuSehat\ErmFhirService;
use App\Services\SatuSehat\SatuSehatErmValidator;
use App\Services\SatuSehat\Resources\EpisodeOfCareService;

new class extends Component {
    public RegPeriksa $reg;
    public ?SatuSehatEncounter $ssEncounter = null;

    public ?SatuSehatBundle $ssBundleLog = null;

    public bool $showSsDetailModal = false;
    public bool $showSsRingkasanModal = false;
    public bool $showSendAllModal = false;
    public ?array $ssDetailData = null;

    public array $ssSelectedConditions = [];
    public array $ssSelectedObservations = [];
    public array $ssSelectedLabObservations = [];
    public array $ssSelectedRadObservations = [];
    public array $ssSelectedUsgObservations = [];
    public array $ssSelectedProcedures = [];
    public array $ssSelectedMedications = [];
    public array $ssSelectedDispenses = [];
    public array $ssSelectedServiceRequests = [];
    public array $ssSelectedImagingStudies = [];
    public array $ssSelectedDiagnosticReports = [];
    public array $ssSelectedSpecimens = [];
    public array $ssSelectedCompositions = [];
    public array $ssSelectedClinicalImpressions = [];
    public array $ssSelectedAllergyIntolerances = [];
    public array $ssSelectedImmunizations = [];
    public array $ssSelectedVaksinObat = [];
    public array $ssSelectedMedicationStatements = [];
    public array $ssSelectedMedicationAdministrations = [];
    public array $ssSelectedAdimeGizis = [];
    public array $ssSelectedMedicationCompositions = [];
    public array $ssSelectedCarePlans = [];
    public array $ssSelectedQuestionnaireResponses = [];
    public array $ssSelectedDocumentReferences = [];
    public array $ssSelectedSurgeries = [];

    public array $sendAllResults = [];

    public function getEncounter(): ?SatuSehatEncounter
    {
        return $this->ssEncounter != null ? $this->ssEncounter : SatuSehatEncounter::where('local_id', $this->reg->no_rawat)->first();
    }

    public function sendSsEncounter($status): void
    {
        try {
            $result = new ErmFhirService()->sendEncounter($this->reg, $status, $this->ssBundleLog);
            if ($result['success']) {
                $this->ssEncounter = null; // Force re-fetch
                $this->toastSuccess($result['message']);
            } else {
                $this->toastError($result['message']);
            }
        } catch (\Exception $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    public function sendSsSurgeries(): void
    {
        try {
            $encounter = $this->getEncounter();
            if (!$encounter) {
                $this->toastError('Encounter belum terkirim.');
                return;
            }

            if (empty($this->ssSelectedSurgeries)) {
                $this->toastError('Pilih tindakan operasi yang akan dikirim.');
                return;
            }

            $result = new ErmFhirService()->sendSurgeries($this->reg, $encounter, $this->ssSelectedSurgeries, $this->ssBundleLog);
            if ($result['success']) {
                $this->toastSuccess($result['message']);
                $this->reset('ssSelectedSurgeries');
            } else {
                $this->toastError($result['message']);
            }
        } catch (\Exception $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    public function sendSsConditions(): void
    {
        try {
            $encounter = $this->getEncounter();

            if (!$encounter) {
                $this->toastError('Encounter belum dikirim ke Satu Sehat. Kirim Encounter terlebih dahulu.');
                return;
            }
            if (empty($this->ssSelectedConditions)) {
                $this->toastError('Silakan pilih minimal 1 Diagnosa/Condition untuk dikirim.');
                return;
            }
            $result = new ErmFhirService()->sendConditions($this->reg, $encounter, $this->ssSelectedConditions, $this->ssBundleLog);
            $result['success'] ? $this->toastSuccess($result['message']) : $this->toastError($result['message']);
        } catch (\Exception $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    public function sendSsProcedures(): void
    {
        try {
            $encounter = $this->getEncounter();

            if (!$encounter) {
                $this->toastError('Encounter belum dikirim ke Satu Sehat. Kirim Encounter terlebih dahulu.');
                return;
            }
            if (empty($this->ssSelectedProcedures)) {
                $this->toastError('Silakan pilih minimal 1 Tindakan/Procedure untuk dikirim.');
                return;
            }
            $result = new ErmFhirService()->sendProcedures($this->reg, $encounter, $this->ssSelectedProcedures, $this->ssBundleLog);
            $result['success'] ? $this->toastSuccess($result['message']) : $this->toastError($result['message']);
        } catch (\Exception $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    public function sendSsObservations(): void
    {
        try {
            $encounter = $this->getEncounter();
            if (!$encounter) {
                $this->toastError('Encounter belum dikirim ke Satu Sehat. Kirim Encounter terlebih dahulu.');
                return;
            }
            if (empty($this->ssSelectedObservations)) {
                $this->toastError('Silakan pilih minimal 1 Pemeriksaan/Observation untuk dikirim.');
                return;
            }

            $result = new ErmFhirService()->sendObservations($this->reg, $encounter, $this->ssSelectedObservations, $this->ssBundleLog);
            $result['success'] ? $this->toastSuccess($result['message']) : $this->toastError($result['message']);
        } catch (\Exception $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    public function sendSsLabObservations(): void
    {
        try {
            $encounter = $this->getEncounter();
            if (!$encounter) {
                $this->toastError('Encounter belum dikirim ke Satu Sehat. Kirim Encounter terlebih dahulu.');
                return;
            }
            if (empty($this->ssSelectedLabObservations)) {
                $this->toastError('Silakan pilih minimal 1 Observasi Laboratorium untuk dikirim.');
                return;
            }
            $result = new ErmFhirService()->sendLabObservations($this->reg, $encounter, $this->ssSelectedLabObservations, $this->ssBundleLog);
            $result['success'] ? $this->toastSuccess($result['message']) : $this->toastError($result['message']);
        } catch (\Exception $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    public function sendSsRadObservations(): void
    {
        try {
            $encounter = $this->getEncounter();
            if (!$encounter) {
                $this->toastError('Encounter belum dikirim ke Satu Sehat. Kirim Encounter terlebih dahulu.');
                return;
            }
            if (empty($this->ssSelectedRadObservations)) {
                $this->toastError('Silakan pilih minimal 1 Observasi Radiologi untuk dikirim.');
                return;
            }
            $result = new ErmFhirService()->sendRadObservations($this->reg, $encounter, $this->ssSelectedRadObservations, $this->ssBundleLog);

            $result['success'] ? $this->toastSuccess($result['message']) : $this->toastError($result['message']);
        } catch (\Exception $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    public function sendSsUsgObservations(): void
    {
        try {
            $encounter = $this->getEncounter();
            if (!$encounter) {
                $this->toastError('Encounter belum dikirim ke Satu Sehat. Kirim Encounter terlebih dahulu.');
                return;
            }
            if (empty($this->ssSelectedUsgObservations)) {
                $this->toastError('Silakan pilih minimal 1 Observasi USG untuk dikirim.');
                return;
            }
            $result = new ErmFhirService()->sendUsgObservations($this->reg, $encounter, $this->ssSelectedUsgObservations, $this->ssBundleLog);
            $result['success'] ? $this->toastSuccess($result['message']) : $this->toastError($result['message']);
        } catch (\Exception $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    public function sendSsMedicationRequests(): void
    {
        try {
            $encounter = $this->getEncounter();
            if (!$encounter) {
                $this->toastError('Encounter belum dikirim ke Satu Sehat. Kirim Encounter terlebih dahulu.');
                return;
            }
            if (empty($this->ssSelectedMedications)) {
                $this->toastError('Silakan pilih minimal 1 Obat/Medication Request untuk dikirim.');
                return;
            }
            $result = new ErmFhirService()->sendMedicationRequests($this->reg, $encounter, $this->ssSelectedMedications, $this->ssBundleLog);
            $result['success'] ? $this->toastSuccess($result['message']) : $this->toastError($result['message']);
        } catch (\Exception $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    public function sendSsMedicationDispenses(): void
    {
        try {
            $encounter = $this->getEncounter();
            if (!$encounter) {
                $this->toastError('Encounter belum dikirim ke Satu Sehat. Kirim Encounter terlebih dahulu.');
                return;
            }
            if (empty($this->ssSelectedDispenses)) {
                $this->toastError('Silakan pilih minimal 1 Pemberian Obat/Medication Dispense untuk dikirim.');
                return;
            }
            $result = new ErmFhirService()->sendMedicationDispenses($this->reg, $encounter, $this->ssSelectedDispenses, $this->ssBundleLog);
            $result['success'] ? $this->toastSuccess($result['message']) : $this->toastError($result['message']);
        } catch (\Exception $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    public function sendSsLabServiceRequests(): void
    {
        try {
            $encounter = $this->getEncounter();
            if (!$encounter) {
                $this->toastError('Encounter belum dikirim ke Satu Sehat. Kirim Encounter terlebih dahulu.');
                return;
            }
            if (empty($this->ssSelectedServiceRequests)) {
                $this->toastError('Silakan pilih minimal 1 Lab Service Request untuk dikirim.');
                return;
            }
            $result = new ErmFhirService()->sendLabServiceRequests($this->reg, $encounter, $this->ssSelectedServiceRequests, $this->ssBundleLog);
            $result['success'] ? $this->toastSuccess($result['message']) : $this->toastError($result['message']);
        } catch (\Exception $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    public function sendSsRadServiceRequests(): void
    {
        try {
            $encounter = $this->getEncounter();
            if (!$encounter) {
                $this->toastError('Encounter belum dikirim ke Satu Sehat. Kirim Encounter terlebih dahulu.');
                return;
            }
            if (empty($this->ssSelectedServiceRequests)) {
                $this->toastError('Silakan pilih minimal 1 Rad Service Request untuk dikirim.');
                return;
            }
            $result = new ErmFhirService()->sendRadServiceRequests($this->reg, $encounter, $this->ssSelectedServiceRequests, $this->ssBundleLog);
            $result['success'] ? $this->toastSuccess($result['message']) : $this->toastError($result['message']);
        } catch (\Exception $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    public function sendSsUsgServiceRequests(): void
    {
        try {
            $encounter = $this->getEncounter();
            if (!$encounter) {
                $this->toastError('Encounter belum dikirim ke Satu Sehat. Kirim Encounter terlebih dahulu.');
                return;
            }
            if (empty($this->ssSelectedServiceRequests)) {
                $this->toastError('Silakan pilih minimal 1 USG Service Request untuk dikirim.');
                return;
            }
            $result = new ErmFhirService()->sendUsgServiceRequests($this->reg, $encounter, $this->ssSelectedServiceRequests, $this->ssBundleLog);
            $result['success'] ? $this->toastSuccess($result['message']) : $this->toastError($result['message']);
        } catch (\Exception $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    public function sendSsImagingStudies(): void
    {
        try {
            $encounter = $this->getEncounter();
            if (!$this->reg || !$encounter) {
                $this->toastError('Registrasi atau Encounter tidak ditemukan.');
                return;
            }
            if (empty($this->ssSelectedImagingStudies)) {
                $this->toastError('Pilih setidaknya 1 Imaging Study.');
                return;
            }
            $result = new ErmFhirService()->sendImagingStudies($this->reg, $encounter, $this->ssSelectedImagingStudies, $this->ssBundleLog);
            $result['success'] ? $this->toastSuccess($result['message']) : $this->toastError($result['message']);
        } catch (\Exception $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    public function sendSsLabDiagnosticReports(): void
    {
        try {
            $encounter = $this->getEncounter();
            if (!$this->reg || !$encounter) {
                $this->toastError('Registrasi atau Encounter tidak ditemukan.');
                return;
            }
            if (empty($this->ssSelectedDiagnosticReports)) {
                $this->toastError('Pilih setidaknya 1 Diagnostic Report Lab.');
                return;
            }
            $result = new ErmFhirService()->sendLabDiagnosticReports($this->reg, $encounter, $this->ssSelectedDiagnosticReports, $this->ssBundleLog);
            $result['success'] ? $this->toastSuccess($result['message']) : $this->toastError($result['message']);
        } catch (\Exception $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    public function sendSsRadDiagnosticReports(): void
    {
        try {
            $encounter = $this->getEncounter();
            if (!$this->reg || !$encounter) {
                $this->toastError('Registrasi atau Encounter tidak ditemukan.');
                return;
            }
            if (empty($this->ssSelectedDiagnosticReports)) {
                $this->toastError('Pilih setidaknya 1 Diagnostic Report Rad.');
                return;
            }
            $result = new ErmFhirService()->sendRadDiagnosticReports($this->reg, $encounter, $this->ssSelectedDiagnosticReports, $this->ssBundleLog);
            $result['success'] ? $this->toastSuccess($result['message']) : $this->toastError($result['message']);
        } catch (\Exception $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    public function sendSsUsgDiagnosticReports(): void
    {
        try {
            $encounter = $this->getEncounter();
            if (!$this->reg || !$encounter) {
                $this->toastError('Registrasi atau Encounter tidak ditemukan.');
                return;
            }
            if (empty($this->ssSelectedDiagnosticReports)) {
                $this->toastError('Pilih setidaknya 1 Diagnostic Report USG.');
                return;
            }
            $result = new ErmFhirService()->sendUsgDiagnosticReports($this->reg, $encounter, $this->ssSelectedDiagnosticReports, $this->ssBundleLog);
            $result['success'] ? $this->toastSuccess($result['message']) : $this->toastError($result['message']);
        } catch (\Exception $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    public function sendSsLabSpecimens(): void
    {
        try {
            $encounter = $this->getEncounter();
            if (!$this->reg || !$encounter) {
                $this->toastError('Registrasi atau Encounter tidak ditemukan.');
                return;
            }
            if (empty($this->ssSelectedSpecimens)) {
                $this->toastError('Pilih setidaknya 1 Specimen Lab.');
                return;
            }
            $result = new ErmFhirService()->sendLabSpecimens($this->reg, $encounter, $this->ssSelectedSpecimens, $this->ssBundleLog);
            $result['success'] ? $this->toastSuccess($result['message']) : $this->toastError($result['message']);
        } catch (\Exception $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    // public function sendSsRadSpecimens(): void
    // {
    //     try {
    //         $encounter = $this->getEncounter();
    //         if (!$this->reg || !$encounter) {
    //             $this->toastError('Registrasi atau Encounter tidak ditemukan.');
    //             return;
    //         }
    //         if (empty($this->ssSelectedSpecimens)) {
    //             $this->toastError('Pilih setidaknya 1 Specimen Rad.');
    //             return;
    //         }
    //         $result = new ErmFhirService()->sendRadSpecimens($this->reg, $encounter, $this->ssSelectedSpecimens);
    //         $result['success'] ? $this->toastSuccess($result['message']) : $this->toastError($result['message']);
    //     } catch (\Exception $e) {
    //         $this->toastError('Error: ' . $e->getMessage());
    //     }
    // }

    public function sendSsCompositions(): void
    {
        try {
            $encounter = $this->getEncounter();
            if (!$this->reg || !$encounter) {
                $this->toastError('Registrasi atau Encounter tidak ditemukan.');
                return;
            }
            if (empty($this->ssSelectedCompositions)) {
                $this->toastError('Pilih setidaknya 1 Composition / Resume.');
                return;
            }
            $result = new ErmFhirService()->sendCompositions($this->reg, $encounter, $this->ssSelectedCompositions, $this->ssBundleLog);
            $result['success'] ? $this->toastSuccess($result['message']) : $this->toastError($result['message']);
        } catch (\Exception $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    public function sendSsAdimeGiziCompositions(): void
    {
        try {
            $encounter = $this->getEncounter();
            if (!$this->reg || !$encounter) {
                $this->toastError('Registrasi atau Encounter tidak ditemukan.');
                return;
            }
            if (empty($this->ssSelectedAdimeGizis)) {
                $this->toastError('Pilih setidaknya 1 Catatan Gizi ADIME.');
                return;
            }
            $result = new ErmFhirService()->sendAdimeGiziCompositions($this->reg, $encounter, $this->ssSelectedAdimeGizis, $this->ssBundleLog);
            $result['success'] ? $this->toastSuccess($result['message']) : $this->toastError($result['message']);
        } catch (\Exception $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    public function sendMedicationCompositions(): void
    {
        try {
            $encounter = $this->getEncounter();
            if (!$this->reg || !$encounter) {
                $this->toastError('Registrasi atau Encounter tidak ditemukan.');
                return;
            }

            $encIhs = $encounter->ihs_number;

            // Hitung jumlah obat yang diharapkan dari SIMRS
            $expectedCount = 0;
            try {
                $expectedCount = \App\Models\Simrs\DetailPemberianObat::where('no_rawat', $this->reg->no_rawat)->count();
            } catch (\Exception) {
                $this->toastError('Gagal membaca data obat dari SIMRS untuk validasi.');
                return;
            }

            if ($expectedCount === 0) {
                $this->toastError('Tidak ada data obat untuk kunjungan ini.');
                return;
            }

            // Validasi: semua MedReq, MedDisp, MedAdm harus sudah terkirim seluruhnya
            $sentReq = \App\Models\SatuSehat\SatuSehatMedicationRequest::where('encounter_ihs', $encIhs)->whereNotNull('ihs_number')->count();
            $sentDisp = \App\Models\SatuSehat\SatuSehatMedicationDispense::where('encounter_ihs', $encIhs)->whereNotNull('ihs_number')->count();
            $sentAdm = \App\Models\SatuSehat\SatuSehatMedicationAdministration::where('encounter_ihs', $encIhs)->whereNotNull('ihs_number')->count();

            $incomplete = [];
            if ($sentReq < $expectedCount) {
                $incomplete[] = "MedicationRequest ({$sentReq}/{$expectedCount})";
            }
            if ($sentDisp < $expectedCount) {
                $incomplete[] = "MedicationDispense ({$sentDisp}/{$expectedCount})";
            }
            if ($sentAdm < $expectedCount) {
                $incomplete[] = "MedicationAdministration ({$sentAdm}/{$expectedCount})";
            }

            if (!empty($incomplete)) {
                $this->toastError('Belum semua resource terkirim: ' . implode(', ', $incomplete) . '.');
                return;
            }

            $result = new ErmFhirService()->sendMedicationCompositions($this->reg, $encounter, $this->ssBundleLog);
            $result['success'] ? $this->toastSuccess($result['message']) : $this->toastError($result['message']);
        } catch (\Exception $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    public function sendSsClinicalImpressions(): void
    {
        try {
            $encounter = $this->getEncounter();
            if (!$this->reg || !$encounter) {
                $this->toastError('Registrasi atau Encounter tidak ditemukan.');
                return;
            }
            if (empty($this->ssSelectedClinicalImpressions)) {
                $this->toastError('Pilih setidaknya 1 Clinical Impression.');
                return;
            }
            $result = new ErmFhirService()->sendClinicalImpressions($this->reg, $encounter, $this->ssSelectedClinicalImpressions, $this->ssBundleLog);
            $result['success'] ? $this->toastSuccess($result['message']) : $this->toastError($result['message']);
        } catch (\Exception $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    public function sendSsAllergyIntolerances(): void
    {
        try {
            $encounter = $this->getEncounter();
            if (!$this->reg || !$encounter) {
                $this->toastError('Registrasi atau Encounter tidak ditemukan.');
                return;
            }
            if (empty($this->ssSelectedAllergyIntolerances)) {
                $this->toastError('Pilih setidaknya 1 Data Alergi.');
                return;
            }

            // Guard: pastikan semua alergi yang dipilih sudah dipetakan ke SNOMED CT
            $allergyMaps = \App\Models\Mapping\AllergyMap::getCached();
            $alergiPasiensMap = AlergiPasien::where('no_rawat_ref', $this->reg->no_rawat)->get()->keyBy(fn($a) => ($a->getRawOriginal('tanggal') ?? '') . '|' . str_replace(':', '', $a->jam ?? ''));
            $unmapped = [];
            foreach ($this->ssSelectedAllergyIntolerances as $idStr) {
                $a = $alergiPasiensMap->get($idStr);
                if ($a && !$allergyMaps->has($a->id_alergi)) {
                    $unmapped[] = $a->id_alergi;
                }
            }
            if (!empty($unmapped)) {
                $this->toastError('Alergi belum dipetakan ke SNOMED CT (id: ' . implode(', ', array_unique($unmapped)) . '). ' . 'Lakukan pemetaan di Local Terminology → Allergy Intolerance terlebih dahulu.');
                return;
            }

            $result = new ErmFhirService()->sendAllergyIntolerances($this->reg, $encounter, $this->ssSelectedAllergyIntolerances, $this->ssBundleLog);
            $result['success'] ? $this->toastSuccess($result['message']) : $this->toastError($result['message']);
        } catch (\Exception $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    public function sendSsImmunizations(): void
    {
        try {
            $encounter = $this->getEncounter();
            if (!$this->reg || !$encounter) {
                $this->toastError('Registrasi atau Encounter tidak ditemukan.');
                return;
            }
            if (empty($this->ssSelectedImmunizations)) {
                $this->toastError('Pilih setidaknya 1 Data Imunisasi.');
                return;
            }
            $result = new ErmFhirService()->sendImmunizations($this->reg, $encounter, $this->ssSelectedImmunizations, $this->ssBundleLog);
            $result['success'] ? $this->toastSuccess($result['message']) : $this->toastError($result['message']);
        } catch (\Exception $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    public function sendSsVaksinFromObat(): void
    {
        try {
            $encounter = $this->getEncounter();
            if (!$this->reg || !$encounter) {
                $this->toastError('Registrasi atau Encounter tidak ditemukan.');
                return;
            }
            if (empty($this->ssSelectedVaksinObat)) {
                $this->toastError('Pilih setidaknya 1 data vaksin.');
                return;
            }

            // Guard: kode_brng diambil dari idStr format: {no_rawat}-IM-{kode_brng}_{tgl}_{jam}
            $selectedKodes = collect($this->ssSelectedVaksinObat)->map(fn($id) => preg_replace('/^.*-IMM_(.+)-\d{8}-\d{6}$/', '$1', $id))->unique();
            $maps = \App\Models\Mapping\MedicationMap::whereIn('local_code', $selectedKodes)->get()->keyBy('local_code');
            $unmappedKfa = $selectedKodes->filter(fn($k) => !$maps->has($k) || !$maps->get($k)->kfa_code)->values();
            $unmappedReason = $selectedKodes->filter(fn($k) => $maps->has($k) && !$maps->get($k)->immunization_reason_code)->values();
            if ($unmappedKfa->isNotEmpty()) {
                $this->toastError('Vaksin belum dipetakan ke KFA: ' . $unmappedKfa->implode(', ') . '. Lakukan pemetaan di Local Mapping → Vaksin.');
                return;
            }
            if ($unmappedReason->isNotEmpty()) {
                $this->toastError('Alasan imunisasi belum dipilih untuk: ' . $unmappedReason->implode(', ') . '. Lengkapi mapping di Local Mapping → Vaksin.');
                return;
            }

            $result = new ErmFhirService()->sendImmunizations($this->reg, $encounter, $this->ssSelectedVaksinObat, $this->ssBundleLog);
            $result['success'] ? $this->toastSuccess($result['message']) : $this->toastError($result['message']);
        } catch (\Exception $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    public function sendSsMedicationStatements(): void
    {
        try {
            $encounter = $this->getEncounter();
            if (!$this->reg || !$encounter) {
                $this->toastError('Registrasi atau Encounter tidak ditemukan.');
                return;
            }
            if (empty($this->ssSelectedMedicationStatements)) {
                $this->toastError('Pilih setidaknya 1 Data Medication Statement.');
                return;
            }
            $result = new ErmFhirService()->sendMedicationStatements($this->reg, $encounter, $this->ssSelectedMedicationStatements, $this->ssBundleLog);
            $result['success'] ? $this->toastSuccess($result['message']) : $this->toastError($result['message']);
        } catch (\Exception $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    public function sendSsMedicationAdministrations(): void
    {
        try {
            $encounter = $this->getEncounter();
            if (!$this->reg || !$encounter) {
                $this->toastError('Registrasi atau Encounter tidak ditemukan.');
                return;
            }
            $result = new ErmFhirService()->sendMedicationAdministrations($this->reg, $encounter, empty($this->ssSelectedMedicationAdministrations) ? null : $this->ssSelectedMedicationAdministrations, $this->ssBundleLog);
            $result['success'] ? $this->toastSuccess($result['message']) : $this->toastError($result['message']);
        } catch (\Exception $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    public function sendSsCarePlans(): void
    {
        try {
            $encounter = $this->getEncounter();
            if (!$encounter) {
                $this->toastError('Encounter belum dikirim ke Satu Sehat. Kirim Encounter terlebih dahulu.');
                return;
            }
            if (empty($this->ssSelectedCarePlans)) {
                $this->toastError('Silakan pilih minimal 1 Care Plan/Instruksi untuk dikirim.');
                return;
            }
            $result = new ErmFhirService()->sendCarePlans($this->reg, $encounter, $this->ssSelectedCarePlans, $this->ssBundleLog);
            $result['success'] ? $this->toastSuccess($result['message']) : $this->toastError($result['message']);
        } catch (\Exception $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    public function sendSsQuestionnaireResponses(): void
    {
        try {
            $encounter = $this->getEncounter();
            if (!$encounter) {
                $this->toastError('Encounter belum dikirim ke Satu Sehat. Kirim Encounter terlebih dahulu.');
                return;
            }
            if (empty($this->ssSelectedQuestionnaireResponses)) {
                $this->toastError('Silakan pilih minimal 1 Telaah Farmasi untuk dikirim.');
                return;
            }
            $result = new ErmFhirService()->sendQuestionnaireResponses($this->reg, $encounter, $this->ssSelectedQuestionnaireResponses, $this->ssBundleLog);
            $result['success'] ? $this->toastSuccess($result['message']) : $this->toastError($result['message']);
        } catch (\Exception $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    public function sendSsDocumentReferences(): void
    {
        try {
            $encounter = $this->getEncounter();
            if (!$encounter) {
                $this->toastError('Encounter belum dikirim ke Satu Sehat. Kirim Encounter terlebih dahulu.');
                return;
            }
            if (empty($this->ssSelectedDocumentReferences)) {
                $this->toastError('Silakan pilih minimal 1 resep untuk dikirim.');
                return;
            }
            $result = new ErmFhirService()->sendDocumentReferences($this->reg, $encounter, $this->ssSelectedDocumentReferences, $this->ssBundleLog);
            $result['success'] ? $this->toastSuccess($result['message']) : $this->toastError($result['message']);
        } catch (\Exception $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    public function checkOrthancStudy(string $accession): void
    {
        try {
            $orthanc = new \App\Services\Dicom\OrthancService();
            $studyIds = $orthanc->findStudyIdsByAccession($accession);

            if (empty($studyIds)) {
                $this->toastError("Study dengan Accession Number {$accession} tidak ditemukan di Orthanc PACS.");
                return;
            }

            // Ambil detail study pertama yang ditemukan
            $orthancId = $studyIds[0];
            $detail = $orthanc->getStudy($orthancId);

            if (!$detail['success']) {
                $this->toastError('Gagal mengambil detail study dari Orthanc: ' . ($detail['message'] ?? 'Unknown error'));
                return;
            }

            $studyData = $detail['data'];
            $mainTags = $studyData['MainDicomTags'] ?? [];
            $patientTags = $studyData['PatientMainDicomTags'] ?? [];

            // Update atau buat record DicomStudy lokal
            \App\Models\Dicom\DicomStudy::updateOrCreate(
                ['noorder' => $accession],
                [
                    'no_rawat' => $this->reg->no_rawat,
                    'orthanc_study_id' => $orthancId,
                    'study_instance_uid' => $mainTags['StudyInstanceUID'] ?? null,
                    'patient_id' => $patientTags['PatientID'] ?? null,
                    'modality' => $mainTags['ModalitiesInStudy'] ?? null,
                    'study_description' => $mainTags['StudyDescription'] ?? null,
                    'study_date' => isset($mainTags['StudyDate']) ? \Carbon\Carbon::createFromFormat('Ymd', $mainTags['StudyDate']) : null,
                    'series_count' => count($studyData['Series'] ?? []),
                    'status' => 'received',
                ],
            );

            $this->toastSuccess('Study ditemukan di Orthanc. Data lokal telah diperbarui.');
        } catch (\Exception $e) {
            $this->toastError('Gagal sinkronisasi Orthanc: ' . $e->getMessage());
        }
    }

    public function openSsDetail(string $ihsNumber): void
    {
        $modelMap = [
            'Condition' => SatuSehatCondition::class,
            'Observation' => SatuSehatObservation::class,
            'Procedure' => SatuSehatProcedure::class,
            'Medication Request' => SatuSehatMedicationRequest::class,
            'Medication Dispense' => SatuSehatMedicationDispense::class,
            'Medication Statement' => SatuSehatMedicationStatement::class,
            'Service Request' => SatuSehatServiceRequest::class,
            'Imaging Study' => SatuSehatImagingStudy::class,
            'Diagnostic Report' => SatuSehatDiagnosticReport::class,
            'Specimen' => SatuSehatSpecimen::class,
            'Composition' => SatuSehatComposition::class,
            'Clinical Impression' => SatuSehatClinicalImpression::class,
            'Allergy Intolerance' => SatuSehatAllergyIntolerance::class,
            'Immunization' => SatuSehatImmunization::class,
            'Care Plan' => SatuSehatCarePlan::class,
            'Questionnaire Response' => SatuSehatQuestionnaireResponse::class,
        ];

        foreach ($modelMap as $label => $class) {
            $record = $class::where('ihs_number', $ihsNumber)->first();
            if (!$record) {
                continue;
            }

            $log = SatuSehatLog::where('ihs_number', $ihsNumber)
                ->whereIn('action', ['create', 'update'])
                ->latest()
                ->first();

            $this->ssDetailData = [
                'resource_type' => $label,
                'ihs_number' => $record->ihs_number,
                'synced_at' => $record->synced_at?->format('d/m/Y H:i') ?? '-',
                'request_body' => $log?->request_body,
                'response_body' => $log?->response_body ?? $record->raw_response,
            ];
            $this->showSsDetailModal = true;
            return;
        }
    }

    public function sendSsEpisodeOfCare(string $eocCode, string $firstDate): void
    {
        try {
            $this->reg = RegPeriksa::with(['pasien'])->find($this->reg->no_rawat);
            app(EpisodeOfCareService::class)->sendEpisode($this->reg->no_rkm_medis, $eocCode, new \DateTime($firstDate));
            $this->toastSuccess("Episode of Care ({$eocCode}) berhasil dikirim ke SatuSehat.");
        } catch (\Throwable $e) {
            $this->toastError($e->getMessage());
        }
    }

    public function updateSsEpisodeOfCareStatus(string $localId, string $newStatus): void
    {
        try {
            app(EpisodeOfCareService::class)->updateEpisodeStatus($localId, $newStatus);
            $this->toastSuccess("Status Episode of Care diperbarui menjadi '{$newStatus}'.");
        } catch (\Throwable $e) {
            $this->toastError($e->getMessage());
        }
    }

    public function updateSsEncounterStatus(string $newStatus): void
    {
        try {
            if (!$this->reg) {
                $this->toastError('Data kunjungan tidak ditemukan.');
                return;
            }
            $result = app(ErmFhirService::class)->updateEncounterStatus($this->reg->no_rawat, $newStatus);
            $result['success'] ? $this->toastSuccess($result['message']) : $this->toastError($result['message']);
        } catch (\Throwable $e) {
            $this->toastError($e->getMessage());
        }
    }

    public function sendSsAllBundle(): void
    {
        $this->showSendAllModal = false;

        try {
            $bundle =
                $this->ssBundleLog ??
                \App\Models\SatuSehat\SatuSehatBundle::create([
                    'no_rawat' => $this->reg->no_rawat,
                    'status' => \App\Models\SatuSehat\SatuSehatBundle::STATUS_QUEUED,
                    'triggered_by' => auth()->id(),
                ]);
            \App\Jobs\SendSatuSehatBundleJob::dispatch($this->reg->no_rawat, $bundle);
            $this->toastSuccess('Pengiriman bundle dijadwalkan. Proses berjalan di background.');
        } catch (\Exception $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    public function placeholder()
    {
        return view('placeholders._erm-satusehat');
    }

    public function with(): array
    {
        $ssEncounter = $this->getEncounter();
        $ssConditions = $ssObservations = $ssProcedures = $ssMedications = collect();
        $ssMedicationDispenses = $ssServiceRequests = $ssDiagnosticReports = collect();
        $ssSpecimens = $ssCompositions = $ssClinicalImpressions = collect();
        $ssAllergyIntolerances = $ssImmunizations = $ssMedicationStatements = $ssMedicationAdministrations = $ssAdimeGiziCompositions = $ssMedicationCompositions = $ssCarePlans = collect();
        $ssQuestionnaireResponses = $ssImagingStudies = collect();
        $ssDocumentReferences = collect();
        $ssEpisodeOfCares = collect();
        $ssDetectedEpisodes = collect();
        $telaahFarmasis = collect();

        $noRawat = $this->reg->no_rawat;

        $this->ssBundleLog = SatuSehatBundle::where('no_rawat', $noRawat)->latest()->first();

        $diagnosas = DiagnosaPasien::where('no_rawat', $noRawat)->with('penyakit')->orderBy('prioritas')->get();
        $prosedurs = ProsedurPasien::where('no_rawat', $noRawat)->with('icd9')->orderBy('prioritas')->get();

        $pemeriksaans = $this->reg->status_lanjut === 'Ralan' ? PemeriksaanRalan::where('no_rawat', $noRawat)->with('petugas')->orderByDesc('tgl_perawatan')->orderByDesc('jam_rawat')->get() : PemeriksaanRanap::where('no_rawat', $noRawat)->with('petugas')->orderByDesc('tgl_perawatan')->orderByDesc('jam_rawat')->get();

        $catatanGizis = CatatanAdimeGizi::where('no_rawat', $noRawat)->with('petugas')->orderByDesc('tanggal')->get();

        $obats = DetailPemberianObat::where('no_rawat', $noRawat)
            ->with(['dataBarang', 'bangsal', 'aturanPakai', 'dataBatch'])
            ->orderBy('tgl_perawatan')
            ->orderBy('jam')
            ->get();

        $vaksin = $obats->filter(fn($o) => str_starts_with(strtolower($o->dataBarang?->nama_brng ?? ''), 'vaksin'))->values();

        $resepPulangs =
            $this->reg->status_lanjut === 'Ranap'
                ? ResepPulang::where('no_rawat', $noRawat)
                    ->with(['dataBarang', 'dataBatch'])
                    ->orderBy('tanggal')
                    ->orderBy('jam')
                    ->get()
                : collect();

        $permintaanLabsPk = PermintaanLab::where('no_rawat', $noRawat)
            ->with(['dokterPerujuk', 'kesanSaran', 'periksaLab.jenisPerawatan', 'periksaLab.dokter', 'periksaLab.detailPeriksaLab', 'periksaLab.templateLaboratoriums'])
            ->orderBy('tgl_permintaan')
            ->orderBy('jam_permintaan')
            ->get();
        $periksaLabsPk = $permintaanLabsPk->flatMap(fn($p) => $p->periksaLab)->values();
        $saranKesanLabs = $permintaanLabsPk->map(fn($p) => $p->kesanSaran)->filter()->values();

        $permintaanRadiologis = PermintaanRadiologi::where('no_rawat', $noRawat)
            ->with(['dokterPerujuk', 'allPeriksaRad.jenisPerawatan', 'allPeriksaRad.dokter', 'allHasilRadiologi', 'allGambarRadiologi', 'allBhpRadiologi.dataBarang'])
            ->orderBy('tgl_permintaan')
            ->orderBy('jam_permintaan')
            ->get();

        $permintaanUsgs = \App\Models\Simrs\Usg\PermintaanUsg::where('no_rawat', $noRawat)->hasResult()->orderBy('waktu_hasil')->get();
        $periksaRadiologis = $permintaanRadiologis->flatMap(fn($pr) => $pr->periksa_rad);
        $hasilRadiologis = $permintaanRadiologis->map(fn($pr) => $pr->hasilRadiologi)->filter()->values();

        $noorders = $permintaanRadiologis->pluck('noorder')->merge($permintaanUsgs->pluck('noorder'))->filter()->unique()->values()->all();
        $dicomStudies = $noorders ? DicomStudy::whereIn('noorder', $noorders)->get()->keyBy('noorder') : collect();

        $dicomRouterResponses = $noorders ? DicomRouterResponse::whereIn('accession_number', $noorders)->orderByDesc('created_at')->get()->groupBy('accession_number') : collect();

        $alergiPasiens = AlergiPasien::where('no_rawat_ref', $noRawat)
            ->with(['alergi', 'reaksi', 'tingkatKeparahan', 'kritisitas', 'pegawai'])
            ->orderBy('tanggal')
            ->orderBy('jam')
            ->get();

        $operasis = Operasi::where('no_rawat', $noRawat)->with('paket')->get();
        $laporanOperasis = LaporanOperasi::where('no_rawat', $noRawat)->orderBy('tanggal')->get();

        // Tindakan dari SIMRS (rawat_jl_* / rawat_inap_*)
        $isRalan = $this->reg->status_lanjut === 'Ralan';
        $refTable = $isRalan ? 'jns_perawatan' : 'jns_perawatan_inap';
        $tndTables = $isRalan ? ['DR' => 'rawat_jl_dr', 'PR' => 'rawat_jl_pr', 'DRPR' => 'rawat_jl_drpr'] : ['DR' => 'rawat_inap_dr', 'PR' => 'rawat_inap_pr', 'DRPR' => 'rawat_inap_drpr'];

        $allTindakan = collect();
        try {
            $simrsTnd = DB::connection('simrs');
            foreach ($tndTables as $suffix => $table) {
                $simrsTnd
                    ->table("{$table} as t")
                    ->join("{$refTable} as ref", 't.kd_jenis_prw', '=', 'ref.kd_jenis_prw')
                    ->where('t.no_rawat', $noRawat)
                    ->select('t.kd_jenis_prw', 't.tgl_perawatan', 't.jam_rawat', 'ref.nm_perawatan')
                    ->orderBy('t.tgl_perawatan')
                    ->orderBy('t.jam_rawat')
                    ->get()
                    ->each(fn($row) => $allTindakan->push((object) array_merge((array) $row, ['_suffix' => $suffix])));
            }
        } catch (\Throwable) {
            // SIMRS tidak tersedia
        }

        // Data USG (dari berbagai tabel USG di SIMRS - Gunakan Config dari UsgService)
        $usgConfigs = \App\Services\UsgService::getUsgTypeConfigs();

        $usgResults = [];
        $totalUsgCount = 0;
        foreach ($usgConfigs as $key => $config) {
            $data = $config['model']::where('no_rawat', $noRawat)->get();
            if ($data->isNotEmpty()) {
                $images = $config['gambar_model']::where('no_rawat', $noRawat)->whereIn('noorder', $data->pluck('noorder'))->get()->pluck('photo_url');

                $usgResults[$key] = [
                    'label' => $config['label'],
                    'data' => $data,
                    'images' => $images,
                ];
                $totalUsgCount += $data->count();
            }
        }

        // Validasi prasyarat Satu Sehat
        $ssValidator = new SatuSehatErmValidator();
        $ssValidationMessages = $ssValidator->validate($this->reg);
        $hasSsErrors = $ssValidator->hasErrors($ssValidationMessages);

        if ($ssEncounter) {
            $encIhs = $ssEncounter->ihs_number;
            $ssConditions = SatuSehatCondition::where('encounter_ihs', $encIhs)->get();
            $ssObservations = SatuSehatObservation::where('encounter_ihs', $encIhs)->get();
            $ssProcedures = SatuSehatProcedure::where('encounter_ihs', $encIhs)->get();
            $ssMedications = SatuSehatMedicationRequest::where('encounter_ihs', $encIhs)->get();
            $ssMedicationDispenses = SatuSehatMedicationDispense::where('encounter_ihs', $encIhs)->get();
            $ssServiceRequests = SatuSehatServiceRequest::where('encounter_ihs', $encIhs)->get();
            $ssImagingStudies = SatuSehatImagingStudy::where('encounter_ihs', $encIhs)->get();
            $ssDiagnosticReports = SatuSehatDiagnosticReport::where('encounter_ihs', $encIhs)->get();
            $ssSpecimens = SatuSehatSpecimen::where('encounter_ihs', $encIhs)->get();
            $ssCompositions = SatuSehatComposition::where('encounter_ihs', $encIhs)
                ->whereNotIn('composition_type', [SatuSehatComposition::TYPE_CATATAN_GIZI, SatuSehatComposition::TYPE_RESUME_FARMASI])
                ->get();
            $ssClinicalImpressions = SatuSehatClinicalImpression::where('encounter_ihs', $encIhs)->get();
            $ssAllergyIntolerances = SatuSehatAllergyIntolerance::where('encounter_ihs', $encIhs)->get();
            $ssImmunizations = SatuSehatImmunization::where('encounter_ihs', $encIhs)->get();
            $ssMedicationStatements = SatuSehatMedicationStatement::where('encounter_ihs', $encIhs)->get();
            $ssMedicationAdministrations = SatuSehatMedicationAdministration::where('encounter_ihs', $encIhs)->get();
            $ssAdimeGiziCompositions = SatuSehatComposition::where('encounter_ihs', $encIhs)->where('composition_type', SatuSehatComposition::TYPE_CATATAN_GIZI)->get();
            $ssMedicationCompositions = SatuSehatComposition::where('encounter_ihs', $encIhs)->where('composition_type', SatuSehatComposition::TYPE_RESUME_FARMASI)->get();
            $ssCarePlans = SatuSehatCarePlan::where('encounter_ihs', $encIhs)->get();
            $ssQuestionnaireResponses = SatuSehatQuestionnaireResponse::where('encounter_ihs', $encIhs)->get();
            $ssDocumentReferences = SatuSehatDocumentReference::where('encounter_ihs', $encIhs)->get();
        }

        // Episode of Care — deteksi & status kirim per pasien
        try {
            $eocService = app(EpisodeOfCareService::class);
            $allDetected = $eocService->detectForPatient($this->reg->no_rkm_medis);
            $ssDetectedEpisodes = $allDetected->filter(fn($e) => !$e['sent'])->values();
            $ssEpisodeOfCares = SatuSehatEpisodeOfCare::where('local_id', 'like', "{$this->reg->no_rkm_medis}-%")->get();
        } catch (\Throwable) {
            // biarkan collect() default
        }

        // Telaah Farmasi dari SIMRS (untuk pilihan kirim)
        try {
            $connSimrs = DB::connection('simrs');
            foreach (['nota_resep' => 'no_nota', 'resep_obat' => 'no_resep'] as $joinTable => $joinKey) {
                try {
                    $telaahFarmasis = $connSimrs
                        ->table('telaah_farmasi as tf')
                        ->join("{$joinTable} as r", 'tf.no_resep', '=', "r.{$joinKey}")
                        ->where('r.no_rawat', $this->reg->no_rawat)
                        ->select('tf.*', 'r.tgl_peresepan as tgl_telaah', 'r.jam as jam_telaah')
                        ->get();
                    break;
                } catch (\Exception $e) {
                }
            }
        } catch (\Exception $e) {
        }

        // Enrich telaahFarmasis: tambahkan kode_brng per no_resep dari SIMRS
        if ($telaahFarmasis->isNotEmpty()) {
            $telaahFarmasis = $telaahFarmasis->map(function ($tf) use ($connSimrs) {
                $tf->kode_brng_list = collect();
                foreach (['resep_obat' => 'no_resep', 'nota_resep' => 'no_nota'] as $tbl => $col) {
                    try {
                        $list = $connSimrs->table($tbl)
                            ->where($col, $tf->no_resep)
                            ->whereNotNull('kode_brng')
                            ->pluck('kode_brng');
                        if ($list->isNotEmpty()) {
                            $tf->kode_brng_list = $list;
                            break;
                        }
                    } catch (\Exception) {}
                }
                return $tf;
            });
        }

        // Riwayat bundle job per no_rawat (5 terbaru)
        $ssBundleLogs = SatuSehatBundle::where('no_rawat', $this->reg->no_rawat)->with('items')->latest()->limit(5)->get();
        $isBundleProcessing = $ssBundleLogs->first() && in_array($ssBundleLogs->first()->status, [SatuSehatBundle::STATUS_QUEUED, SatuSehatBundle::STATUS_RUNNING]);

        // Medication List (Kombinasi Obat Ralan/Ranap & Resep Pulang)
        $medList = collect();
        foreach ($obats as $o) {
            $idStr = $this->reg->no_rawat . '-MED_REQ_' . $o->kode_brng . '-' . ($o->tgl_perawatan ? \Carbon\Carbon::parse($o->tgl_perawatan)->format('Ymd') : '') . '-' . str_replace(':', '', $o->jam ?? '');
            $medList->push(
                (object) [
                    'idStr' => $idStr,
                    'kode_brng' => $o->kode_brng,
                    'nama_brng' => $o->dataBarang?->nama_brng,
                    'tgl' => $o->tgl_perawatan ? \Carbon\Carbon::parse($o->tgl_perawatan)->format('d/m/Y') : '',
                    'jam' => $o->jam,
                    'jml' => $o->jml,
                ],
            );
        }
        foreach ($resepPulangs as $r) {
            $idStr = $this->reg->no_rawat . '-MED_REQ_' . $r->kode_brng . '-' . ($r->tanggal ? \Carbon\Carbon::parse($r->tanggal)->format('Ymd') : '') . '-' . str_replace(':', '', $r->jam ?? '');
            $medList->push(
                (object) [
                    'idStr' => $idStr,
                    'kode_brng' => $r->kode_brng,
                    'nama_brng' => $r->dataBarang?->nama_brng,
                    'tgl' => $r->tanggal ? \Carbon\Carbon::parse($r->tanggal)->format('d/m/Y') : '',
                    'jam' => $r->jam,
                    'jml' => $r->jml_barang,
                ],
            );
        }

        // CarePlan List (dari Instruksi Medik di Pemeriksaan)
        $instruksiList = $pemeriksaans->filter(fn($p) => !empty($p->instruksi))->map(function ($p) {
            $tgl = $p->tgl_perawatan instanceof \Carbon\Carbon ? $p->tgl_perawatan->format('Ymd') : str_replace('-', '', $p->tgl_perawatan);
            $jam = str_replace(':', '', $p->jam_rawat ?? '000000');
            $p->idStr = "{$this->reg->no_rawat}-CARE_PLAN-{$tgl}-{$jam}";
            return $p;
        });

        // Detail Lab untuk Observation Lab
        $detailLabsCount = $periksaLabsPk->flatMap(fn($pl) => $pl->detailPeriksaLab)->count();

        // Ringkasan target pengiriman (berdasarkan data SIMRS lokal)
        // Sub-type totals (untuk $ssSummary dan $prereqMap)
        $ptSrLab = $periksaLabsPk->count();
        $ptSrRad = $permintaanRadiologis->count();
        $ptSrUsg = $totalUsgCount;
        $ptSpecimen = $periksaLabsPk->count();
        $ptObsTtv = $pemeriksaans->sum(fn($p) => count(app(ErmFhirService::class)->extractVitalSigns($p)));
        $ptObsLab = $detailLabsCount;
        $ptObsRad = $hasilRadiologis->count();
        $ptObsUsg = $totalUsgCount;
        $ptDrLab = $periksaLabsPk->count();
        $ptDrRad = $hasilRadiologis->count();
        $ptDrUsg = $totalUsgCount;
        $ptImgRad = $permintaanRadiologis->count();
        $ptImgUsg = $totalUsgCount;
        $ptComp = ($this->reg->resumePasien ? 1 : 0) + ($this->reg->resumePasienRanap ? 1 : 0) + $catatanGizis->count() + ($medList->count() > 0 ? 1 : 0);

        // Consolidated — dipakai $ssTabs, $grandTotalTarget, dan $prereqMap total
        $potentialSummary = [
            'Encounter' => 1,
            'Condition' => $diagnosas->count(),
            'Procedure' => $prosedurs->count() + $allTindakan->count(),
            'Surgery' => $operasis->count(),
            'Observation' => $ptObsTtv + $ptObsLab + $ptObsRad + $ptObsUsg,
            'AllergyIntolerance' => $alergiPasiens->count(),
            'ClinicalImpression' => $pemeriksaans->filter(fn($p) => !empty($p->keluhan) || !empty($p->penilaian) || !empty($p->tindak_lanjut))->count(),
            'CarePlan' => $instruksiList->count(),
            'QuestionnaireResponse' => $telaahFarmasis->count(),
            'DocumentReference' => $telaahFarmasis->count(),
            'ServiceRequest' => $ptSrLab + $ptSrRad + $ptSrUsg,
            'Specimen' => $ptSpecimen,
            'ImagingStudy' => $ptImgRad + $ptImgUsg,
            'DiagnosticReport' => $ptDrLab + $ptDrRad + $ptDrUsg,
            'MedicationRequest' => $medList->count(),
            'MedicationDispense' => $medList->count(),
            'MedicationStatement' => $medList->count(),
            'MedicationAdministration' => $medList->count(),
            'Immunization' => $vaksin->count(),
            'Composition' => $ptComp,
        ];
        $grandTotalTarget = array_sum($potentialSummary);

        // Ringkasan pengiriman Satu Sehat per resource (yang sudah terkirim)
        $ssSummary = [
            'klinis' => [
                ['label' => 'Encounter', 'count' => $ssEncounter ? 1 : 0, 'total' => $potentialSummary['Encounter']],
                ['label' => 'Condition', 'count' => $ssConditions->count(), 'total' => $potentialSummary['Condition']],
                ['label' => 'Procedure', 'count' => $ssProcedures->filter(fn($p) => !str_contains($p->local_id, '-SURGERY-'))->count(), 'total' => $potentialSummary['Procedure']],
                ['label' => 'Surgery', 'count' => $ssProcedures->filter(fn($p) => str_contains($p->local_id, '-SURGERY-'))->count(), 'total' => $potentialSummary['Surgery']],
                ['label' => 'Observation', 'count' => $ssObservations->where('category', 'vital-signs')->count(), 'total' => $ptObsTtv],
                ['label' => 'AllergyIntolerance', 'count' => $ssAllergyIntolerances->count(), 'total' => $potentialSummary['AllergyIntolerance']],
                ['label' => 'ClinicalImpression', 'count' => $ssClinicalImpressions->count(), 'total' => $potentialSummary['ClinicalImpression']],
                ['label' => 'CarePlan', 'count' => $ssCarePlans->count(), 'total' => $potentialSummary['CarePlan']],
            ],
            'laboratorium' => [['label' => 'ServiceRequest', 'count' => $ssServiceRequests->where('note', 'LAB')->count(), 'total' => $ptSrLab], ['label' => 'Specimen', 'count' => $ssSpecimens->count(), 'total' => $ptSpecimen], ['label' => 'Observation', 'count' => $ssObservations->where('category', 'laboratory')->count(), 'total' => $ptObsLab], ['label' => 'DiagnosticReport', 'count' => $ssDiagnosticReports->filter(fn($dr) => str_contains($dr->local_id, 'DR_LAB_'))->count(), 'total' => $ptDrLab]],
            'radiologi' => [['label' => 'ServiceRequest', 'count' => $ssServiceRequests->where('note', 'RAD')->count(), 'total' => $ptSrRad], ['label' => 'ImagingStudy', 'count' => $ssImagingStudies->filter(fn($is) => str_contains($is->local_id, 'IMG_RAD_'))->count(), 'total' => $ptImgRad], ['label' => 'Observation', 'count' => $ssObservations->filter(fn($o) => str_contains($o->local_id, 'OBS_RAD_'))->count(), 'total' => $ptObsRad], ['label' => 'DiagnosticReport', 'count' => $ssDiagnosticReports->filter(fn($dr) => str_contains($dr->local_id, 'DR_RAD_'))->count(), 'total' => $ptDrRad]],
            'usg' => [['label' => 'ServiceRequest', 'count' => $ssServiceRequests->where('note', 'USG')->count(), 'total' => $ptSrUsg], ['label' => 'ImagingStudy', 'count' => $ssImagingStudies->filter(fn($is) => str_contains($is->local_id, 'IMG_USG_'))->count(), 'total' => $ptImgUsg], ['label' => 'Observation', 'count' => $ssObservations->filter(fn($o) => str_contains($o->local_id, 'OBS_USG_'))->count(), 'total' => $ptObsUsg], ['label' => 'DiagnosticReport', 'count' => $ssDiagnosticReports->filter(fn($dr) => str_contains($dr->local_id, 'DR_USG_'))->count(), 'total' => $ptDrUsg]],
            'farmasi' => [['label' => 'MedicationRequest', 'count' => $ssMedications->count(), 'total' => $potentialSummary['MedicationRequest']], ['label' => 'MedicationDispense', 'count' => $ssMedicationDispenses->count(), 'total' => $potentialSummary['MedicationDispense']], ['label' => 'MedicationStatement', 'count' => $ssMedicationStatements->count(), 'total' => $potentialSummary['MedicationStatement']], ['label' => 'MedicationAdministration', 'count' => $ssMedicationAdministrations->count(), 'total' => $potentialSummary['MedicationAdministration']], ['label' => 'Immunization', 'count' => $ssImmunizations->count(), 'total' => $potentialSummary['Immunization']], ['label' => 'QuestionnaireResponse', 'count' => $ssQuestionnaireResponses->where('type', 'telaah_farmasi')->count(), 'total' => $potentialSummary['QuestionnaireResponse']], ['label' => 'DocumentReference', 'count' => $ssDocumentReferences->count(), 'total' => $potentialSummary['DocumentReference']]],
            'komposisi' => [['label' => 'Composition', 'count' => $ssCompositions->count() + $ssAdimeGiziCompositions->count() + $ssMedicationCompositions->count(), 'total' => $potentialSummary['Composition']]],
        ];

        $hasSsErrors ??= false;
        $ssValidationMessages ??= [];

        $ssBundleLog = $this->ssBundleLog;

        return compact(
            'isBundleProcessing',
            'ssEncounter',
            'ssConditions',
            'ssObservations',
            'ssProcedures',
            'ssMedications',
            'ssMedicationDispenses',
            'ssServiceRequests',
            'ssImagingStudies',
            'ssDiagnosticReports',
            'ssSpecimens',
            'ssCompositions',
            'ssClinicalImpressions',
            'ssAllergyIntolerances',
            'ssImmunizations',
            'ssMedicationStatements',
            'ssMedicationAdministrations',
            'ssAdimeGiziCompositions',
            'ssMedicationCompositions',
            'ssCarePlans',
            'ssQuestionnaireResponses',
            'ssDocumentReferences',
            'ssEpisodeOfCares',
            'ssDetectedEpisodes',
            'ssSummary',
            'ssBundleLog',
            'ssBundleLogs',
            'telaahFarmasis',
            'hasSsErrors',
            'ssValidationMessages',
            'diagnosas',
            'prosedurs',
            'pemeriksaans',
            'catatanGizis',
            'obats',
            'resepPulangs',
            'periksaLabsPk',
            'saranKesanLabs',
            'permintaanRadiologis',
            'periksaRadiologis',
            'hasilRadiologis',
            'permintaanUsgs',
            'alergiPasiens',
            'vaksin',
            'dicomStudies',
            'dicomRouterResponses',
            'grandTotalTarget',
            'potentialSummary',
            'medList',
            'instruksiList',
            'usgResults',
            'operasis',
            'laporanOperasis',
            'allTindakan',
        );
    }
};
?>

@php
    // Variabel prasyarat FHIR — dipakai oleh semua tab partial
    $prereq_encounter = $ssEncounter !== null;
    $prereq_sr_lab = isset($ssServiceRequests) && $ssServiceRequests->where('note', 'LAB')->count() > 0;
    $prereq_sr_rad = isset($ssServiceRequests) && $ssServiceRequests->where('note', 'RAD')->count() > 0;
    $prereq_specimen = isset($ssSpecimens) && $ssSpecimens->count() > 0;
    $prereq_med_req = isset($ssMedications) && $ssMedications->count() > 0;
    $prereq_imaging = isset($ssImagingStudies) && $ssImagingStudies->count() > 0;
    $prereq_obs_lab = isset($ssObservations) && $ssObservations->where('category', 'laboratory')->count() > 0;
    $prereq_obs_rad = isset($ssObservations) && $ssObservations->where('category', 'imaging')->count() > 0;

    // Ketersediaan Location reference — wajib untuk pengiriman Medication, Lab, dan Rad
    $prereq_location_apotek = \App\Models\SatuSehat\SatuSehatLocation::where('type', 'apotek')
        ->whereNotNull('ihs_number')
        ->exists();
    $prereq_location_lab = \App\Models\SatuSehat\SatuSehatLocation::where('type', 'lab')
        ->whereNotNull('ihs_number')
        ->exists();
    $prereq_location_rad = \App\Models\SatuSehat\SatuSehatLocation::where('type', 'rad')
        ->whereNotNull('ihs_number')
        ->exists();
@endphp
<div x-data="{
    ssFhirTab: new URLSearchParams(location.search).get('ssFhirTab') || 'encounter'
}" x-init="$watch('ssFhirTab', val => {
    if (activeTab === 'satusehat') {
        let url = new URL(window.location.href);
        url.searchParams.set('ssFhirTab', val);
        window.history.replaceState({}, '', url);
    }
});
$watch('activeTab', val => {
    if (val === 'satusehat') {
        let url = new URL(window.location.href);
        url.searchParams.set('ssFhirTab', ssFhirTab);
        window.history.replaceState({}, '', url);
    }
});" @if ($isBundleProcessing)
    wire:poll.5000ms
    @endif>
    @if (!$ssEncounter)
        <div class="space-y-3">
            {{-- Panel validasi prasyarat --}}
            @if (!empty($ssValidationMessages))
                <div
                    class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                    <div class="flex items-center gap-2 px-4 py-3 border-b border-zinc-100 dark:border-primary-dark-700">
                        @if ($hasSsErrors)
                            <flux:icon name="x-circle" class="h-4 w-4 text-red-500 shrink-0" />
                            <p class="text-sm font-semibold text-red-600 dark:text-red-400">Prasyarat Belum Terpenuhi</p>
                        @else
                            <flux:icon name="exclamation-triangle" class="h-4 w-4 text-amber-500 shrink-0" />
                            <p class="text-sm font-semibold text-amber-600 dark:text-amber-400">Peringatan Konfigurasi
                            </p>
                        @endif
                    </div>
                    <div class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
                        @foreach ($ssValidationMessages as $msg)
                            <div
                                class="flex items-start gap-3 px-4 py-3
                                {{ $msg['type'] === 'error' ? 'bg-red-50/60 dark:bg-red-900/10' : 'bg-amber-50/60 dark:bg-amber-900/10' }}">
                                <flux:icon name="{{ $msg['type'] === 'error' ? 'x-circle' : 'exclamation-triangle' }}"
                                    class="h-4 w-4 mt-0.5 shrink-0 {{ $msg['type'] === 'error' ? 'text-red-500' : 'text-amber-500' }}" />
                                <div class="text-sm">
                                    <span
                                        class="font-semibold {{ $msg['type'] === 'error' ? 'text-red-700 dark:text-red-400' : 'text-amber-700 dark:text-amber-400' }}">
                                        {{ $msg['section'] }}
                                    </span>
                                    <span
                                        class="{{ $msg['type'] === 'error' ? 'text-red-600 dark:text-red-400/80' : 'text-amber-600 dark:text-amber-400/80' }}">
                                        — {{ $msg['text'] }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Empty state + tombol kirim --}}
            <div
                class="p-8 text-center bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                <div
                    class="flex h-14 w-14 mx-auto items-center justify-center rounded-2xl
                    {{ $hasSsErrors ? 'bg-zinc-100 dark:bg-zinc-700/30' : 'bg-sky-100 dark:bg-sky-900/30' }}">
                    <flux:icon name="{{ $hasSsErrors ? 'lock-closed' : 'cloud-arrow-up' }}"
                        class="h-7 w-7 {{ $hasSsErrors ? 'text-zinc-400 dark:text-zinc-500' : 'text-sky-600 dark:text-sky-400' }}" />
                </div>
                <p class="mt-3 text-sm font-semibold text-zinc-700 dark:text-primary-dark-300">
                    {{ $hasSsErrors ? 'Tidak Dapat Mengirim Encounter' : 'Belum Ada Data Encounter' }}
                </p>
                <p class="mt-1 text-sm text-zinc-500 dark:text-primary-dark-400">
                    {{ $hasSsErrors
                        ? 'Perbaiki prasyarat di atas sebelum mengirim data ke Satu Sehat.'
                        : 'Data kunjungan ini belum dikirim ke Satu Sehat.' }}
                </p>
                @if (!$hasSsErrors)
                    <div class="mt-4 flex items-center justify-center gap-2">
                        <x-atoms.button wire:click="sendSsEncounter('arrived')" icon="paper-airplane" variant="outline"
                            size="sm" :disabled="$isBundleProcessing">
                            <span wire:loading.remove wire:target="sendSsEncounter">Kirim Encounter</span>
                            <span wire:loading wire:target="sendSsEncounter">Mengirim...</span>
                        </x-atoms.button>
                        <x-atoms.button wire:click="sendSsAllBundle" icon="paper-airplane" variant="primary"
                            size="sm" :disabled="$isBundleProcessing">
                            Kirim Semua
                        </x-atoms.button>
                        <x-atoms.button wire:click="$refresh" icon="arrow-path" variant="ghost" size="sm"
                            title="Refresh data Satu Sehat">
                            <span wire:loading.remove wire:target="$refresh">Refresh</span>
                            <span wire:loading wire:target="$refresh">Memuat...</span>
                        </x-atoms.button>
                    </div>
                @endif
            </div>
        </div>
    @else
        @php
            $ps = $potentialSummary;
            $ssTabs = [
                // [$key, $icon, $label, $sent, $desc, $total]
                [
                    'encounter',
                    'server-stack',
                    'Encounter',
                    $ssEncounter ? 1 : 0,
                    'Data riwayat Kunjungan Pasien',
                    $ps['Encounter'],
                ],
                [
                    'condition',
                    'clipboard-document-list',
                    'Condition',
                    $ssConditions->count(),
                    'Riwayat Diagnosa ICD-10',
                    $ps['Condition'],
                ],
                [
                    'observation',
                    'chart-bar',
                    'Observation',
                    $ssObservations->count(),
                    'Tanda vital, Lab, Rad & USG',
                    $ps['Observation'],
                ],
                [
                    'surgery',
                    'scissors',
                    'Surgery (Operasi)',
                    $ssProcedures->filter(fn($p) => str_contains($p->local_id, '-SURGERY-'))->count(),
                    'Laporan tindakan operasi',
                    $ps['Surgery'],
                ],
                [
                    'procedure',
                    'hand-raised',
                    'Procedure',
                    $ssProcedures->filter(fn($p) => !str_contains($p->local_id, '-SURGERY-'))->count(),
                    'Tindakan medis/prosedur',
                    $ps['Procedure'],
                ],
                [
                    'medication-request',
                    'beaker',
                    'Med. Request',
                    $ssMedications->count(),
                    'Data peresepan obat',
                    $ps['MedicationRequest'],
                ],
                [
                    'medication-dispense',
                    'archive-box',
                    'Med. Dispense',
                    $ssMedicationDispenses->count(),
                    'Data penyerahan obat',
                    $ps['MedicationDispense'],
                ],
                [
                    'medication-statement',
                    'clipboard-document-check',
                    'Med. Statement',
                    $ssMedicationStatements->count(),
                    'Riwayat penggunaan obat',
                    $ps['MedicationStatement'],
                ],
                [
                    'medication-administration',
                    'hand-raised',
                    'Med. Administration',
                    $ssMedicationAdministrations->count(),
                    'Pemberian obat kepada pasien',
                    $ps['MedicationAdministration'],
                ],
                [
                    'service-request',
                    'magnifying-glass',
                    'Service Request',
                    $ssServiceRequests->count(),
                    'Order Lab, Rad & USG',
                    $ps['ServiceRequest'],
                ],
                [
                    'imaging-study',
                    'photo',
                    'Imaging Study',
                    $ssImagingStudies->count(),
                    'Data gambar Rad & USG',
                    $ps['ImagingStudy'],
                ],
                [
                    'diagnostic-report',
                    'document-chart-bar',
                    'Diagnostic Report',
                    $ssDiagnosticReports->count(),
                    'Laporan hasil Lab, Rad & USG',
                    $ps['DiagnosticReport'],
                ],
                [
                    'specimen',
                    'eye-dropper',
                    'Specimen',
                    $ssSpecimens->count(),
                    'Pengambilan spesimen laboratorium',
                    $ps['Specimen'],
                ],
                [
                    'composition',
                    'document-text',
                    'Composition',
                    $ssCompositions->count() + $ssAdimeGiziCompositions->count() + $ssMedicationCompositions->count(),
                    'Resume medis & catatan gizi ADIME',
                    $ps['Composition'],
                ],
                [
                    'clinical-impression',
                    'eye',
                    'Clinical Impression',
                    $ssClinicalImpressions->count(),
                    'Catatan kesan klinis',
                    $ps['ClinicalImpression'],
                ],
                [
                    'allergy',
                    'shield-exclamation',
                    'Allergy Intolerance',
                    $ssAllergyIntolerances->count(),
                    'Data riwayat alergi pasien',
                    $ps['AllergyIntolerance'],
                ],
                [
                    'immunization',
                    'shield-check',
                    'Immunization',
                    $ssImmunizations->count(),
                    'Data riwayat imunisasi',
                    $ps['Immunization'],
                ],
                [
                    'care-plan',
                    'clipboard',
                    'Care Plan',
                    $ssCarePlans->count(),
                    'Instruksi medik/perawatan',
                    $ps['CarePlan'],
                ],
                [
                    'episode-of-care',
                    'document-magnifying-glass',
                    'Episode of Care',
                    $ssEpisodeOfCares->count(),
                    'Episode program perawatan pasien',
                    0,
                ],
                [
                    'questionnaire-response',
                    'document-magnifying-glass',
                    'Questionnaire Response',
                    $ssQuestionnaireResponses->count(),
                    'Telaah Farmasi & lainnya',
                    $ps['QuestionnaireResponse'],
                ],
                [
                    'document-reference',
                    'document-text',
                    'Doc. Reference',
                    $ssDocumentReferences->count(),
                    'Instruksi resep farmasi',
                    $telaahFarmasis->count(),
                ],
            ];
        @endphp

        {{-- Bundle Log Info Bar --}}
        @if ($ssBundleLog)
            <div
                class="flex flex-col lg:flex-row items-center gap-3 px-4 py-2.5 mt-4 rounded-xl bg-white dark:bg-primary-dark-800 border border-zinc-200 dark:border-primary-dark-700 shadow-sm text-xs">
                <flux:icon name="archive-box" class="w-3.5 h-3.5 text-zinc-400 shrink-0" />
                <span class="text-zinc-400 dark:text-primary-dark-500 shrink-0">Bundle Log</span>
                <code
                    class="font-mono text-zinc-600 dark:text-primary-dark-300 bg-zinc-100 dark:bg-primary-dark-700 px-1.5 py-0.5 rounded">{{ $ssBundleLog->id }}</code>
                <flux:badge :color="$ssBundleLog->status_color" size="sm">
                    {{ $ssBundleLog->status_label }}
                </flux:badge>
                @if ($ssBundleLog->total_sent)
                    <span class="text-emerald-600 dark:text-emerald-400 font-medium">
                        {{ $ssBundleLog->total_sent }} terkirim
                    </span>
                @endif
                @if ($ssBundleLog->total_errors)
                    <span class="text-red-500 font-medium">{{ $ssBundleLog->total_errors }} gagal</span>
                @endif
                @if ($ssBundleLog->completed_at)
                    <span class="text-zinc-400 dark:text-primary-dark-500 ml-auto shrink-0">
                        {{ $ssBundleLog->completed_at->diffForHumans() }}
                    </span>
                @elseif ($ssBundleLog->started_at)
                    <span class="text-zinc-400 dark:text-primary-dark-500 ml-auto shrink-0">
                        {{ $ssBundleLog->started_at->diffForHumans() }}
                    </span>
                @endif
            </div>
        @endif

        <div class="flex flex-col lg:flex-row gap-6 mt-4">

            {{-- Sidebar Navigasi --}}
            <aside class="w-full lg:w-64 shrink-0">
                <div
                    class="relative lg:sticky lg:top-8 bg-white dark:bg-primary-dark-800 lg:rounded-2xl lg:border border-zinc-200 dark:border-primary-dark-700 lg:shadow-sm overflow-hidden flex flex-col gap-1 lg:block">

                    <div
                        class="hidden lg:flex items-center justify-between px-4 pt-4 pb-2 border-b border-zinc-100 dark:border-primary-dark-700/50 mb-2">
                        <p
                            class="text-[10px] font-bold uppercase tracking-widest text-zinc-400 dark:text-primary-dark-500">
                            Resource FHIR</p>
                        <button wire:click="$refresh" wire:loading.attr="disabled"
                            class="p-1 rounded-md text-zinc-400 hover:text-zinc-600 dark:hover:text-primary-dark-200 hover:bg-zinc-100 dark:hover:bg-primary-dark-700 transition-colors disabled:opacity-50"
                            title="Refresh data Satu Sehat">
                            <flux:icon name="arrow-path" class="w-3.5 h-3.5" wire:loading.class="animate-spin"
                                wire:target="$refresh" />
                        </button>
                    </div>

                    {{-- Horizontal Layout for Mobile / Tablet --}}
                    <div class="flex lg:hidden items-center gap-1 p-1">
                        <button wire:click="$refresh" wire:loading.attr="disabled"
                            class="shrink-0 p-2 rounded-lg text-zinc-400 hover:text-zinc-600 dark:hover:text-primary-dark-200 hover:bg-zinc-100 dark:hover:bg-primary-dark-700 transition-colors disabled:opacity-50"
                            title="Refresh data Satu Sehat">
                            <flux:icon name="arrow-path" class="w-4 h-4" wire:loading.class="animate-spin"
                                wire:target="$refresh" />
                        </button>
                    </div>
                    <div
                        class="flex lg:hidden gap-1 p-1 overflow-x-auto bg-zinc-100 rounded-xl dark:bg-primary-dark-900/50">
                        @foreach ($ssTabs as [$key, $icon, $label, $sent, $desc, $total])
                            <button @click="ssFhirTab = '{{ $key }}'"
                                :class="ssFhirTab === '{{ $key }}'
                                    ?
                                    'bg-white shadow-sm text-zinc-900 dark:bg-primary-dark-800 dark:text-primary-dark-100' :
                                    'text-zinc-500 hover:text-zinc-700 dark:text-primary-dark-400 dark:hover:text-primary-dark-200'"
                                class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition-all whitespace-nowrap border dark:border-primary-dark-700/50"
                                :class="ssFhirTab === '{{ $key }}' ? 'border-zinc-200 dark:border-primary-dark-600' :
                                    'border-transparent'">
                                <flux:icon name="{{ $icon }}" class="h-4 w-4" />
                                {{ $label }}
                                @if ($total > 0)
                                    <flux:badge
                                        color="{{ $sent >= $total ? 'emerald' : ($sent > 0 ? 'primary' : 'zinc') }}"
                                        size="sm">
                                        {{ $sent }}/{{ $total }}
                                    </flux:badge>
                                @elseif ($sent > 0)
                                    <flux:badge color="primary" size="sm">{{ $sent }}</flux:badge>
                                @endif
                            </button>
                        @endforeach
                    </div>

                    {{-- Vertical Layout for Desktop --}}
                    <nav class="max-h-[calc(100vh-24rem)] overflow-auto hidden lg:flex flex-col px-2 pb-2 space-y-0.5">
                        @foreach ($ssTabs as [$key, $icon, $label, $sent, $desc, $total])
                            <button @click="ssFhirTab = '{{ $key }}'"
                                :class="ssFhirTab === '{{ $key }}'
                                    ?
                                    'bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300' :
                                    'text-zinc-600 dark:text-primary-dark-400 hover:bg-zinc-50 dark:hover:bg-primary-dark-700/40 hover:text-zinc-800 dark:hover:text-primary-dark-200'"
                                class="group flex items-center w-full gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors text-left relative">
                                <flux:icon name="{{ $icon }}" class="h-5 w-5 shrink-0" />
                                <div class="flex-1 flex flex-col min-w-0 pr-14">
                                    <span class="truncate">{{ $label }}</span>
                                    <span
                                        :class="ssFhirTab === '{{ $key }}' ?
                                            'text-primary-500 dark:text-primary-400' :
                                            'text-zinc-400 dark:text-primary-dark-500 group-hover:text-zinc-500'"
                                        class="text-[10px] font-normal truncate">{{ $desc }}</span>
                                </div>
                                <div class="absolute right-3 top-1/2 -translate-y-1/2">
                                    @if ($total > 0)
                                        <flux:badge
                                            color="{{ $sent >= $total ? 'emerald' : ($sent > 0 ? 'primary' : 'zinc') }}"
                                            size="sm">
                                            {{ $sent }}/{{ $total }}
                                        </flux:badge>
                                    @elseif ($sent > 0)
                                        <flux:badge color="primary" size="sm">{{ $sent }}</flux:badge>
                                    @endif
                                </div>
                            </button>
                        @endforeach
                    </nav>

                    {{-- Action Buttons — Desktop --}}
                    @php $lastBundle = $ssBundleLogs->first(); @endphp
                    <div
                        class="hidden lg:flex flex-col px-2 pb-3 pt-1 gap-1 border-t border-zinc-100 dark:border-primary-dark-700/50 mt-1">
                        <button wire:click="$set('showSendAllModal', true)"
                            @if ($isBundleProcessing) disabled @endif
                            class="w-full flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm font-medium text-primary-600 dark:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-900/20 transition-colors {{ $isBundleProcessing ? 'opacity-50 cursor-not-allowed' : '' }}">
                            <flux:icon name="paper-airplane" class="h-5 w-5 shrink-0" />
                            <div class="text-left">
                                <span class="flex-1">Kirim Bundle & Selesaikan</span>
                                @if ($lastBundle)
                                    <flux:badge color="{{ $lastBundle->status_color }}" size="sm">
                                        {{ $lastBundle->status_label }}
                                    </flux:badge>
                                @endif
                            </div>
                        </button>
                        <button wire:click="$set('showSsRingkasanModal', true)"
                            class="w-full flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm font-medium text-zinc-500 dark:text-primary-dark-400 hover:bg-zinc-50 dark:hover:bg-primary-dark-700/40 hover:text-zinc-700 dark:hover:text-primary-dark-200 transition-colors">
                            <flux:icon name="clipboard-document-list" class="h-5 w-5 shrink-0" />
                            <span>Ringkasan Pengiriman</span>
                        </button>
                    </div>

                </div>

                {{-- Action Buttons — Mobile --}}
                <div class="lg:hidden px-1 mt-2 flex gap-2">
                    <button wire:click="$set('showSendAllModal', true)"
                        @if ($isBundleProcessing) disabled @endif
                        class="flex-1 flex items-center justify-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold text-primary-600 dark:text-primary-400 border border-primary-200 dark:border-primary-800/60 bg-primary-50/60 dark:bg-primary-900/20 hover:bg-primary-100 dark:hover:bg-primary-900/40 transition-colors {{ $isBundleProcessing ? 'opacity-50 cursor-not-allowed' : '' }}">
                        <flux:icon name="paper-airplane" class="h-4 w-4 shrink-0" />
                        Kirim Semua
                    </button>
                    <button wire:click="$set('showSsRingkasanModal', true)"
                        class="flex-1 flex items-center justify-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold text-zinc-500 dark:text-primary-dark-400 border border-zinc-200 dark:border-primary-dark-700 hover:bg-zinc-50 dark:hover:bg-primary-dark-700/40 transition-colors">
                        <flux:icon name="clipboard-document-list" class="h-4 w-4 shrink-0" />
                        Ringkasan
                    </button>
                </div>

            </aside>

            {{-- Konten Dinamis --}}
            <div class="flex-1 min-w-0">

                {{-- Panel Status Bundle Job --}}
                @php $lastBundle = $ssBundleLogs->first(); @endphp
                @if ($lastBundle)
                    @if (
                        $lastBundle->status === \App\Models\SatuSehat\SatuSehatBundle::STATUS_PARTIAL ||
                            $lastBundle->status === \App\Models\SatuSehat\SatuSehatBundle::STATUS_FAILED)
                        <div class="mb-4 rounded-xl border border-red-200 dark:border-red-800/40 overflow-hidden"
                            x-data="{ open: false }">
                            <button @click="open = !open"
                                class="w-full flex items-center gap-3 px-4 py-3 bg-red-50 dark:bg-red-900/20 text-left">
                                <flux:icon name="exclamation-circle" class="w-4 h-4 text-red-500 shrink-0" />
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-red-700 dark:text-red-300">
                                        @php
                                            $errCount = $lastBundle->items->where('status', 'failed')->count();
                                            $warnCount = $lastBundle->items->where('status', 'warning')->count();
                                        @endphp
                                        @if ($errCount > 0 && $warnCount > 0)
                                            {{ $errCount }} error & {{ $warnCount }} warning ditemukan
                                        @elseif($errCount > 0)
                                            {{ $errCount }} error ditemukan
                                        @elseif($warnCount > 0)
                                            {{ $warnCount }} peringatan ditemukan
                                        @else
                                            {{ $lastBundle->total_errors }} masalah ditemukan
                                        @endif
                                        <span
                                            class="font-normal text-red-500 dark:text-red-400">({{ $lastBundle->created_at->diffForHumans() }})</span>
                                    </p>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <flux:badge color="{{ $lastBundle->status_color }}" size="sm">
                                        {{ $lastBundle->status_label }}
                                    </flux:badge>
                                    <flux:icon name="chevron-down"
                                        class="w-4 h-4 text-red-400 transition-transform duration-200"
                                        ::class="open ? 'rotate-180' : ''" />
                                </div>
                            </button>

                            <div x-show="open" x-cloak
                                class="bg-white dark:bg-primary-dark-800 divide-y divide-red-50 dark:divide-red-900/20">
                                {{-- Itemized Error List --}}
                                @php
                                    $errorItems = $lastBundle->items->filter(
                                        fn($i) => $i->status === 'failed' || $i->status === 'warning',
                                    );
                                @endphp

                                @foreach ($errorItems as $item)
                                    <div class="px-4 py-2.5 space-y-1">
                                        <div class="flex items-start gap-3">
                                            <span
                                                class="w-2 h-2 rounded-full shrink-0 mt-1.5 {{ $item->status === 'warning' ? 'bg-amber-400' : 'bg-red-400' }}"></span>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2 mb-0.5">
                                                    <span
                                                        class="text-xs font-bold text-zinc-700 dark:text-primary-dark-200 uppercase tracking-tight">{{ $item->resource_type }}</span>
                                                    @if ($item->local_id)
                                                        <span
                                                            class="text-[10px] text-zinc-400 font-mono">#{{ $item->local_id }}</span>
                                                    @endif
                                                </div>
                                                <p
                                                    class="text-xs leading-relaxed {{ $item->status === 'warning' ? 'text-amber-700 dark:text-amber-400' : 'text-red-600 dark:text-red-400' }}">
                                                    {{ $item->error_message }}
                                                </p>
                                            </div>
                                            <span
                                                class="text-[10px] text-zinc-400 font-mono shrink-0">{{ $item->created_at->format('H:i:s') }}</span>
                                        </div>
                                    </div>
                                @endforeach

                                {{-- Encounter finish status --}}
                                <div class="px-4 py-2.5 bg-zinc-50 dark:bg-primary-dark-900/30">
                                    <div class="flex items-center gap-2">
                                        <flux:icon
                                            name="{{ $lastBundle->encounter_finished ? 'check-circle' : 'x-circle' }}"
                                            class="w-3.5 h-3.5 {{ $lastBundle->encounter_finished ? 'text-emerald-500' : 'text-red-400' }}" />
                                        <span
                                            class="text-xs {{ $lastBundle->encounter_finished ? 'text-zinc-500 dark:text-primary-dark-400' : 'text-red-600 dark:text-red-400 font-medium' }}">
                                            Encounter
                                            {{ $lastBundle->encounter_finished ? 'berhasil diselesaikan' : 'belum diselesaikan' }}
                                        </span>
                                        <span class="ml-auto text-xs text-zinc-400 dark:text-primary-dark-500">
                                            {{ $lastBundle->total_sent }} resource terkirim
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                @endif

                @php
                    $flattenedSs = collect($ssSummary)->flatten(1);
                    $totalCumulativeSent = $flattenedSs->sum('count');

                    $procBundle = $ssBundleLogs->first();
                    $procItems = $procBundle ? $procBundle->items : collect();

                    $displaySuccess = $totalCumulativeSent;
                    $displayFailed = $procItems->where('status', 'failed')->count();
                    $displayWarning = $procItems->where('status', 'warning')->count();
                    $countTarget = $grandTotalTarget;
                @endphp

                {{-- Rekap Pengiriman Live --}}
                @if ($procBundle)
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                        <x-organisms.stat-card title="Progres Resource" :value="$displaySuccess . ' / ' . $countTarget" icon="document-duplicate"
                            color="zinc" />
                        <x-organisms.stat-card title="Success" :value="$displaySuccess" icon="check-circle"
                            color="emerald" />
                        <x-organisms.stat-card title="Failed" :value="$displayFailed" icon="x-circle" color="red" />
                        <x-organisms.stat-card title="Warning" :value="$displayWarning" icon="exclamation-triangle"
                            color="amber" />
                    </div>
                @endif
                {{-- Notice Bundle Processing --}}
                @if ($isBundleProcessing)
                    @php $currentStatus = $ssBundleLogs->first()->status; @endphp
                    <div
                        class="mb-4 flex items-center gap-3 p-4 bg-primary-50 border border-primary-200 rounded-2xl dark:bg-primary-900/10 dark:border-primary-800/50">
                        <flux:icon name="arrow-path" class="w-5 h-5 text-primary-500 animate-spin shrink-0" />
                        <div class="flex-1">
                            <p class="text-sm font-semibold text-primary-700 dark:text-primary-300">
                                {{ $currentStatus === SatuSehatBundle::STATUS_QUEUED ? 'Bundle dalam antrian...' : 'Bundle sedang dikirim...' }}
                            </p>
                            <p class="text-xs text-primary-600 dark:text-primary-400">
                                {{ $currentStatus === SatuSehatBundle::STATUS_QUEUED
                                    ? 'Proses sinkronisasi sedang menunggu antrian background job.'
                                    : 'Proses sinkronisasi sedang berjalan. Pengiriman manual dinonaktifkan sementara.' }}
                            </p>
                        </div>
                        <flux:badge color="{{ $ssBundleLogs->first()->status_color }}" size="sm"
                            class="animate-pulse">
                            {{ strtoupper($ssBundleLogs->first()->status_label) }}
                        </flux:badge>
                    </div>
                @endif

                <div class="flex-1 min-w-0"
                    :class="{ 'opacity-60 pointer-events-none cursor-not-allowed select-none': @js($isBundleProcessing) }">
                    {{-- Encounter --}}
                    <div x-show="ssFhirTab === 'encounter'" x-cloak
                        class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                        @include('pages.erm.detail-tabs._satusehat.encounter')
                    </div>

                    @php
                        $thClass =
                            'px-4 py-2 text-xs font-medium text-left uppercase text-zinc-500 dark:text-primary-dark-400';
                        $tdMono = 'px-4 py-2 font-mono text-sm text-primary-600 dark:text-primary-400';
                        $tdText = 'px-4 py-2 text-sm text-zinc-900 dark:text-primary-dark-100';
                        $tdMuted = 'px-4 py-2 text-sm text-zinc-500 dark:text-primary-dark-400';
                    @endphp

                    {{-- Condition --}}
                    <div x-show="ssFhirTab === 'condition'" x-cloak
                        class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                        @include('pages.erm.detail-tabs._satusehat.condition')
                    </div>

                    {{-- Observation --}}
                    <div x-show="ssFhirTab === 'observation'" x-cloak x-data="{ obsSubTab: 'vital' }"
                        class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                        @include('pages.erm.detail-tabs._satusehat.observation')
                    </div>

                    {{-- Procedure --}}
                    <div x-show="ssFhirTab === 'procedure'" x-cloak
                        class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                        @include('pages.erm.detail-tabs._satusehat.procedure')
                    </div>

                    {{-- Surgery --}}
                    <div x-show="ssFhirTab === 'surgery'" x-cloak
                        class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                        @include('pages.erm.detail-tabs._satusehat.surgery')
                    </div>

                    {{-- Medication Request --}}
                    <div x-show="ssFhirTab === 'medication-request'" x-cloak
                        class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                        @include('pages.erm.detail-tabs._satusehat.medication-request')
                    </div>

                    {{-- Medication Dispense --}}
                    <div x-show="ssFhirTab === 'medication-dispense'" x-cloak
                        class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                        @include('pages.erm.detail-tabs._satusehat.medication-dispense')
                    </div>

                    {{-- Medication Statement --}}
                    <div x-show="ssFhirTab === 'medication-statement'" x-cloak
                        class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                        @include('pages.erm.detail-tabs._satusehat.medication-statement')
                    </div>

                    {{-- Medication Administration --}}
                    <div x-show="ssFhirTab === 'medication-administration'" x-cloak
                        class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                        @include('pages.erm.detail-tabs._satusehat.medication-administration')
                    </div>

                    {{-- Service Request --}}

                    <div x-show="ssFhirTab === 'service-request'" x-cloak x-data="{ srSubTab: 'lab' }"
                        class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                        @include('pages.erm.detail-tabs._satusehat.service-request')
                    </div>

                    {{-- Imaging Study --}}
                    <div x-show="ssFhirTab === 'imaging-study'" x-cloak
                        class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                        @include('pages.erm.detail-tabs._satusehat.imaging-study')
                    </div>

                    {{-- Diagnostic Report --}}
                    <div x-show="ssFhirTab === 'diagnostic-report'" x-cloak x-data="{ drSubTab: 'lab' }"
                        class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                        @include('pages.erm.detail-tabs._satusehat.diagnostic-report')
                    </div>

                    {{-- Specimen --}}
                    <div x-show="ssFhirTab === 'specimen'" x-cloak x-data="{ spSubTab: 'lab' }"
                        class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                        @include('pages.erm.detail-tabs._satusehat.specimen')
                    </div>

                    {{-- Composition --}}
                    {{-- Composition — Resume Medis & Catatan Gizi ADIME --}}
                    <div x-show="ssFhirTab === 'composition'" x-cloak class="space-y-4">
                        @include('pages.erm.detail-tabs._satusehat.composition')
                    </div>

                    {{-- Clinical Impression --}}
                    <div x-show="ssFhirTab === 'clinical-impression'" x-cloak
                        class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                        @include('pages.erm.detail-tabs._satusehat.clinical-impression')
                    </div>

                    {{-- Allergy Intolerance --}}
                    <div x-show="ssFhirTab === 'allergy'" x-cloak
                        class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                        @include('pages.erm.detail-tabs._satusehat.allergy-intolerance')
                    </div>

                    {{-- Immunization --}}
                    <div x-show="ssFhirTab === 'immunization'" x-cloak
                        class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                        @include('pages.erm.detail-tabs._satusehat.immunization')
                    </div>

                    {{-- Care Plan --}}
                    <div x-show="ssFhirTab === 'care-plan'" x-cloak
                        class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                        @include('pages.erm.detail-tabs._satusehat.care-plan')
                    </div>

                    {{-- Episode of Care --}}
                    <div x-show="ssFhirTab === 'episode-of-care'" x-cloak
                        class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                        @include('pages.erm.detail-tabs._satusehat.episode-of-care')
                    </div>

                    {{-- Questionnaire Response --}}
                    <div x-show="ssFhirTab === 'questionnaire-response'" x-cloak
                        class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                        @include('pages.erm.detail-tabs._satusehat.questionnaire-response')
                    </div>

                    {{-- Document Reference --}}
                    <div x-show="ssFhirTab === 'document-reference'" x-cloak
                        class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                        @include('pages.erm.detail-tabs._satusehat.document-reference')
                    </div>
                </div>
            </div>
        </div>
        <x-organisms.modal wire:model="showSsDetailModal" maxWidth="4xl" title="">
            @if ($ssDetailData)
                <div x-data="{ payloadTab: 'request' }" class="space-y-6">
                    {{-- Header --}}
                    <div class="flex items-start gap-4 pb-4 border-b border-zinc-200 dark:border-primary-dark-700">
                        <div
                            class="flex items-center justify-center flex-shrink-0 w-12 h-12 rounded-lg bg-green-100 dark:bg-green-900/50">
                            <flux:icon name="check-circle" class="w-6 h-6 text-green-600 dark:text-green-400" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <h2 class="text-lg font-bold text-zinc-900 dark:text-primary-dark-100">
                                {{ $ssDetailData['resource_type'] }} — Berhasil Dikirim
                            </h2>
                            <div class="flex flex-wrap items-center gap-2 mt-2">
                                <flux:badge color="green" size="sm">Terkirim</flux:badge>
                                <span
                                    class="text-xs font-mono text-zinc-500 dark:text-primary-dark-400">{{ $ssDetailData['ihs_number'] }}</span>
                                <span
                                    class="text-xs text-zinc-400 dark:text-primary-dark-500">{{ $ssDetailData['synced_at'] }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Info Grid --}}
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                        <div class="p-3 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                            <h4 class="text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">
                                Resource
                                Type</h4>
                            <p class="mt-1 text-sm text-zinc-900 dark:text-primary-dark-100">
                                {{ $ssDetailData['resource_type'] }}
                            </p>
                        </div>
                        <div class="p-3 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                            <h4 class="text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">
                                Disinkronkan
                            </h4>
                            <p class="mt-1 text-sm text-zinc-900 dark:text-primary-dark-100">
                                {{ $ssDetailData['synced_at'] }}
                            </p>
                        </div>
                        <div class="p-3 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                            <h4 class="text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">
                                IHS
                                Number
                            </h4>
                            <p class="mt-1 font-mono text-sm break-all text-zinc-900 dark:text-primary-dark-100">
                                {{ $ssDetailData['ihs_number'] }}
                            </p>
                        </div>
                    </div>

                    {{-- Request / Response Tabs --}}
                    <div>
                        <div class="flex w-full gap-1 p-1 bg-zinc-100 dark:bg-primary-dark-900/50 rounded-xl mb-3">
                            <button @click="payloadTab = 'request'"
                                :class="payloadTab === 'request'
                                    ?
                                    'bg-white dark:bg-primary-dark-800 text-zinc-900 dark:text-primary-dark-100 shadow-sm' :
                                    'text-zinc-500 dark:text-primary-dark-400 hover:text-zinc-700 dark:hover:text-primary-dark-200'"
                                class="flex-1 px-4 py-1.5 text-sm font-medium rounded-lg transition-all">
                                Request
                            </button>
                            <button @click="payloadTab = 'response'"
                                :class="payloadTab === 'response'
                                    ?
                                    'bg-white dark:bg-primary-dark-800 text-zinc-900 dark:text-primary-dark-100 shadow-sm' :
                                    'text-zinc-500 dark:text-primary-dark-400 hover:text-zinc-700 dark:hover:text-primary-dark-200'"
                                class="flex-1 px-4 py-1.5 text-sm font-medium rounded-lg transition-all">
                                Response
                            </button>
                        </div>

                        <div x-show="payloadTab === 'request'" x-cloak>
                            @if (!empty($ssDetailData['request_body']))
                                <x-atoms.code-block language="json"
                                    maxHeight="max-h-96">{{ json_encode($ssDetailData['request_body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</x-atoms.code-block>
                            @else
                                <div
                                    class="flex flex-col items-center py-8 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                                    <flux:icon name="document-text"
                                        class="w-8 h-8 text-zinc-300 dark:text-primary-dark-600" />
                                    <p class="mt-2 text-sm text-zinc-400 dark:text-primary-dark-500">Tidak ada data
                                        request
                                        tersimpan.</p>
                                </div>
                            @endif
                        </div>

                        <div x-show="payloadTab === 'response'" x-cloak>
                            @if (!empty($ssDetailData['response_body']))
                                <x-atoms.code-block language="json"
                                    maxHeight="max-h-96">{{ json_encode($ssDetailData['response_body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</x-atoms.code-block>
                            @else
                                <div
                                    class="flex flex-col items-center py-8 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                                    <flux:icon name="document-text"
                                        class="w-8 h-8 text-zinc-300 dark:text-primary-dark-600" />
                                    <p class="mt-2 text-sm text-zinc-400 dark:text-primary-dark-500">Tidak ada data
                                        response
                                        tersimpan.</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="flex justify-end pt-4 border-t border-zinc-200 dark:border-primary-dark-700">
                        <x-atoms.button variant="ghost"
                            wire:click="$set('showSsDetailModal', false)">Tutup</x-atoms.button>
                    </div>
                </div>
            @endif

        </x-organisms.modal>

        {{-- Modal Konfirmasi Kirim Bundle --}}
        <x-organisms.modal wire:model="showSendAllModal" title="Kirim Bundle Satu Sehat" maxWidth="md">
            <div class="space-y-4">
                <div
                    class="flex items-start gap-3 p-4 rounded-xl bg-primary-50 dark:bg-primary-900/20 border border-primary-100 dark:border-primary-800/40">
                    <flux:icon name="information-circle"
                        class="w-5 h-5 text-primary-600 dark:text-primary-400 shrink-0 mt-0.5" />
                    <div class="text-sm text-primary-700 dark:text-primary-300 space-y-1">
                        <p class="font-semibold">Semua resource akan dikirim ke Satu Sehat</p>
                        <p class="text-primary-600/80 dark:text-primary-400/80">Proses berjalan berurutan:
                            Encounter →
                            Klinis → Lab → Radiologi → Obat → Komposisi.</p>
                    </div>
                </div>

                <div
                    class="flex items-start gap-3 p-4 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-100 dark:border-amber-800/40">
                    <flux:icon name="exclamation-triangle" class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" />
                    <div class="text-sm text-amber-700 dark:text-amber-300 space-y-1">
                        <p class="font-semibold">Encounter akan diselesaikan</p>
                        <p class="text-amber-600/80 dark:text-amber-400/80">Di akhir proses, status Encounter akan
                            diubah
                            menjadi <span class="font-mono font-bold">finished</span>. Resource yang sudah terkirim
                            akan
                            dilewati secara otomatis.</p>
                    </div>
                </div>

                <p class="text-xs text-zinc-400 dark:text-primary-dark-500">
                    No. Rawat: <span class="font-mono font-semibold">{{ $this->reg->no_rawat }}</span>
                    &nbsp;·&nbsp; Pasien: <span class="font-semibold">{{ $this->reg->pasien?->nm_pasien }}</span>
                </p>
            </div>

            <x-slot:footer>
                <div class="flex items-center justify-between w-full">
                    <x-atoms.button wire:click="$set('showSendAllModal', false)"
                        variant="ghost">Batal</x-atoms.button>
                    <x-atoms.button wire:click="sendSsAllBundle" icon="paper-airplane" variant="primary">
                        <span wire:loading.remove wire:target="sendSsAllBundle">Mulai Kirim Bundle</span>
                        <span wire:loading wire:target="sendSsAllBundle">Memproses...</span>
                    </x-atoms.button>
                </div>
            </x-slot:footer>
        </x-organisms.modal>

        {{-- Modal Ringkasan Pengiriman --}}
        <x-organisms.modal wire:model="showSsRingkasanModal" title="Ringkasan Pengiriman Satu Sehat" :description="'No. Rawat: ' . ($this->reg->no_rawat ?? '-')"
            maxWidth="2xl">

            @php
                $groupMeta = [
                    'klinis' => ['label' => 'Klinis', 'color' => 'sky'],
                    'laboratorium' => ['label' => 'Laboratorium', 'color' => 'teal'],
                    'radiologi' => ['label' => 'Radiologi', 'color' => 'violet'],
                    'usg' => ['label' => 'USG', 'color' => 'indigo'],
                    'farmasi' => ['label' => 'Farmasi', 'color' => 'amber'],
                    'komposisi' => ['label' => 'Komposisi', 'color' => 'emerald'],
                ];
                $flattened = collect($ssSummary)->flatten(1);
                $totalSent = $flattened->sum('count');
                $totalResourcesAda = $flattened->filter(fn($r) => $r['total'] > 0)->count();
                $totalUnfinished = $flattened->filter(fn($r) => $r['count'] < $r['total'] && $r['total'] > 0)->count();

                // --- Prasyarat per resource ---
                $encOk = $ssEncounter !== null;
                $srLabOk = $ssServiceRequests->where('note', 'LAB')->count() > 0;
                $srRadOk = $ssServiceRequests->where('note', 'RAD')->count() > 0;
                $srUsgOk = $ssServiceRequests->where('note', 'USG')->count() > 0;
                $specOk = $ssSpecimens->count() > 0;
                $medReqOk = $ssMedications->count() > 0;
                $imgOk = $ssImagingStudies->count() > 0;
                $imgUsgOk = $ssImagingStudies->filter(fn($is) => str_contains($is->local_id, 'IMG_USG_'))->count() > 0;
                $obsLabOk = $ssObservations->where('category', 'laboratory')->count() > 0;
                $obsRadOk = $ssObservations->filter(fn($o) => str_contains($o->local_id, 'OBS_RAD_'))->count() > 0;
                $obsUsgOk = $ssObservations->filter(fn($o) => str_contains($o->local_id, 'OBS_USG_'))->count() > 0;
                // Cek mapping obat: apakah kode_brng sudah ada di map_medication (local_code)
                $obatKodes = $obats->pluck('kode_brng')->filter()->unique()->all();
                $vaksinKodes = $vaksin->pluck('kode_brng')->filter()->unique()->all();
                $medMappingOk = empty($obatKodes)
                    ? false
                    : \App\Models\Mapping\MedicationMap::whereIn('local_code', $obatKodes)->exists();
                $vaksinMappingOk = empty($vaksinKodes)
                    ? false
                    : \App\Models\Mapping\MedicationMap::whereIn('local_code', $vaksinKodes)->exists();

                $prereqMap = [
                    // Klinis
                    // Klinis
                    'Encounter' => [],
                    'Condition' => [['Encounter', $encOk]],
                    'Procedure' => [['Encounter', $encOk]],
                    'Surgery' => [['Encounter', $encOk]],
                    'Observation' => [['Encounter', $encOk]],
                    'AllergyIntolerance' => [['Encounter', $encOk]],
                    'ClinicalImpression' => [['Encounter', $encOk]],
                    'CarePlan' => [['Encounter', $encOk]],
                    'QuestionnaireResponse' => [['Encounter', $encOk]],
                    // Lab/Rad/USG — ServiceRequest, Specimen, DiagnosticReport berbagi label generik
                    // karena $prereqMap di-lookup by label dari $ssSummary rows
                    'ServiceRequest' => [['Encounter', $encOk]],
                    'Specimen' => [['Encounter', $encOk], ['ServiceRequest', $srLabOk]],
                    'ImagingStudy' => [['Encounter', $encOk], ['ServiceRequest', $srRadOk || $srUsgOk]],
                    'DiagnosticReport' => [['Encounter', $encOk], ['ServiceRequest', $srLabOk || $srRadOk || $srUsgOk]],
                    // Obat & Vaksin
                    'MedicationRequest' => [['Encounter', $encOk], ['Mapping Medication', $medMappingOk]],
                    'MedicationDispense' => [['Encounter', $encOk], ['MedicationRequest', $medReqOk]],
                    'MedicationStatement' => [['Encounter', $encOk], ['MedicationRequest', $medReqOk]],
                    'MedicationAdministration' => [['Encounter', $encOk], ['MedicationRequest', $medReqOk]],
                    'Immunization' => [['Encounter', $encOk], ['Mapping Vaksin', $vaksinMappingOk]],
                    // Komposisi
                    'Composition' => [
                        ['Encounter', $encOk],
                        ['MedicationRequest', $ssMedications->count() >= $medList->count() && $medList->count() > 0],
                        [
                            'MedicationDispense',
                            $ssMedicationDispenses->count() >= $medList->count() && $medList->count() > 0,
                        ],
                        [
                            'MedicationAdministration',
                            $ssMedicationAdministrations->count() >= $medList->count() && $medList->count() > 0,
                        ],
                    ],
                ];
            @endphp

            {{-- Stat header --}}
            <div class="grid grid-cols-3 gap-3 mb-5">
                <div
                    class="text-center px-3 py-2.5 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/40 border border-zinc-100 dark:border-primary-dark-700">
                    <p class="text-2xl font-bold text-zinc-800 dark:text-primary-dark-100">{{ $totalSent }} /
                        {{ $grandTotalTarget }}
                    </p>
                    <p class="text-xs text-zinc-500 dark:text-primary-dark-400 mt-0.5">Total Terkirim</p>
                </div>
                <div
                    class="text-center px-3 py-2.5 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-100 dark:border-emerald-800/40">
                    <p class="text-2xl font-bold text-emerald-700 dark:text-emerald-400">{{ $totalResourcesAda }}
                    </p>
                    <p class="text-xs text-emerald-600 dark:text-emerald-500 mt-0.5">Resource Ada</p>
                </div>
                <div
                    class="text-center px-3 py-2.5 rounded-xl
                {{ $totalUnfinished > 0 ? 'bg-red-50 dark:bg-red-900/20 border border-red-100 dark:border-red-800/40' : 'bg-zinc-50 dark:bg-primary-dark-900/40 border border-zinc-100 dark:border-primary-dark-700' }}">
                    <p
                        class="text-2xl font-bold {{ $totalUnfinished > 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-400 dark:text-primary-dark-500' }}">
                        {{ $totalUnfinished }}
                    </p>
                    <p
                        class="text-xs mt-0.5 {{ $totalUnfinished > 0 ? 'text-red-500 dark:text-red-500' : 'text-zinc-400 dark:text-primary-dark-500' }}">
                        Belum Selesai
                    </p>
                </div>
            </div>

            {{-- Per-group list --}}
            <div class="space-y-4">
                @foreach ($ssSummary as $groupKey => $rows)
                    @php $meta = $groupMeta[$groupKey]; @endphp
                    <div>
                        <p
                            class="text-[10px] font-bold uppercase tracking-widest text-{{ $meta['color'] }}-600 dark:text-{{ $meta['color'] }}-400 mb-1.5 px-1">
                            {{ $meta['label'] }}
                        </p>
                        <div class="rounded-xl border border-zinc-100 dark:border-primary-dark-700 overflow-hidden">
                            @foreach ($rows as $i => $row)
                                @php
                                    $isFinished = $row['count'] >= $row['total'] && $row['total'] > 0;
                                    $isPartial = $row['count'] > 0 && $row['count'] < $row['total'];
                                    $isPending = $row['count'] == 0 && $row['total'] > 0;
                                    $isEmpty = $row['total'] == 0;

                                    $rowBg = $isPending
                                        ? 'bg-red-50/40 dark:bg-red-900/10'
                                        : ($isPartial
                                            ? 'bg-amber-50/40 dark:bg-amber-900/10'
                                            : 'bg-white dark:bg-primary-dark-800');
                                    $rowColor = $isPending
                                        ? 'text-red-500 dark:text-red-400'
                                        : ($isPartial
                                            ? 'text-amber-600 dark:text-amber-400'
                                            : ($isFinished
                                                ? 'text-zinc-700 dark:text-primary-dark-200'
                                                : 'text-zinc-400 dark:text-primary-dark-500'));
                                    $badgeColor = $isPending
                                        ? 'red'
                                        : ($isPartial
                                            ? 'amber'
                                            : ($isFinished
                                                ? $meta['color']
                                                : 'zinc'));
                                @endphp
                                <div
                                    class="flex items-center justify-between px-4 py-2.5
                                {{ $i > 0 ? 'border-t border-zinc-50 dark:border-primary-dark-700/50' : '' }}
                                {{ $rowBg }}">
                                    @php
                                        $rowPrereqs = $prereqMap[$row['label']] ?? [];
                                        $prereqsMet =
                                            empty($rowPrereqs) || collect($rowPrereqs)->every(fn($p) => $p[1]);
                                        $prereqsBlocked = !empty($rowPrereqs) && !$prereqsMet && $row['total'] > 0;
                                    @endphp
                                    <div class="flex flex-col gap-1 min-w-0">
                                        <div class="flex items-center gap-2.5">
                                            @if ($isFinished)
                                                <span
                                                    class="w-2 h-2 rounded-full shrink-0 bg-{{ $meta['color'] }}-400"></span>
                                            @elseif ($isPartial)
                                                <span class="w-2 h-2 rounded-full shrink-0 bg-amber-400"></span>
                                            @elseif ($prereqsBlocked)
                                                <flux:icon name="lock-closed"
                                                    class="w-3.5 h-3.5 shrink-0 text-zinc-400 dark:text-primary-dark-500" />
                                            @elseif ($isPending)
                                                <flux:icon name="exclamation-circle"
                                                    class="w-4 h-4 shrink-0 text-red-400 dark:text-red-500" />
                                            @else
                                                <span
                                                    class="w-2 h-2 rounded-full shrink-0 bg-zinc-300 dark:bg-zinc-600"></span>
                                            @endif
                                            <span
                                                class="text-sm font-medium {{ $rowColor }}">{{ $row['label'] }}</span>
                                        </div>
                                        @if (!empty($rowPrereqs))
                                            <div class="flex flex-wrap gap-1 pl-6">
                                                @foreach ($rowPrereqs as [$prereqLabel, $prereqMet])
                                                    <span @class([
                                                        'inline-flex items-center gap-0.5 text-[10px] px-1.5 py-0.5 rounded-full font-medium',
                                                        'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' => $prereqMet,
                                                        'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400' => !$prereqMet,
                                                    ])>
                                                        @if ($prereqMet)
                                                            <flux:icon name="check" class="w-2.5 h-2.5" />
                                                        @else
                                                            <flux:icon name="x-mark" class="w-2.5 h-2.5" />
                                                        @endif
                                                        {{ $prereqLabel }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                    <flux:badge color="{{ $badgeColor }}" size="sm" class="shrink-0">
                                        {{ $row['count'] }} / {{ $row['total'] }} Terkirim
                                    </flux:badge>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Riwayat Bundle Job --}}
            @if ($ssBundleLogs->isNotEmpty())
                <div class="mt-5 pt-4 border-t border-zinc-100 dark:border-primary-dark-700">
                    <p
                        class="text-[10px] font-bold uppercase tracking-widest text-zinc-400 dark:text-primary-dark-500 mb-2 px-1">
                        Riwayat Bundle Job
                    </p>
                    <div class="rounded-xl border border-zinc-100 dark:border-primary-dark-700 overflow-hidden"
                        x-data="{ expanded: null }">
                        @foreach ($ssBundleLogs as $i => $bundle)
                            <div
                                class="{{ $i > 0 ? 'border-t border-zinc-50 dark:border-primary-dark-700/50' : '' }}">
                                <button
                                    @click="expanded === {{ $i }} ? expanded = null : expanded = {{ $i }}"
                                    class="w-full flex items-center gap-3 px-4 py-2.5 text-left hover:bg-zinc-50 dark:hover:bg-primary-dark-700/30 transition-colors">
                                    <flux:badge color="{{ $bundle->status_color }}" size="sm">
                                        {{ $bundle->status_label }}
                                    </flux:badge>
                                    <span class="text-xs text-zinc-500 dark:text-primary-dark-400 flex-1">
                                        {{ $bundle->created_at->format('d M Y H:i') }}
                                    </span>
                                    <div class="flex items-center gap-3 text-xs shrink-0">
                                        @if ($bundle->total_sent > 0)
                                            <span
                                                class="text-emerald-600 dark:text-emerald-400 font-semibold">{{ $bundle->total_sent }}
                                                terkirim</span>
                                        @endif
                                        @if ($bundle->total_errors > 0)
                                            <span
                                                class="text-red-500 dark:text-red-400 font-semibold">{{ $bundle->total_errors }}
                                                error</span>
                                        @endif
                                        @if ($bundle->duration)
                                            <span
                                                class="text-zinc-400 dark:text-primary-dark-500 font-mono">{{ $bundle->duration }}</span>
                                        @endif
                                        <flux:icon name="chevron-down"
                                            class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-200"
                                            ::class="expanded === {{ $i }} ? 'rotate-180' : ''" />
                                    </div>
                                </button>

                                <div x-show="expanded === {{ $i }}" x-cloak
                                    class="px-4 pb-3 space-y-1 bg-zinc-50/60 dark:bg-primary-dark-900/30">
                                    @if (!empty($bundle->results))
                                        @foreach ($bundle->results as $label => $step)
                                            <div class="flex items-center gap-2 py-1 text-xs">
                                                <span
                                                    class="w-2 h-2 rounded-full shrink-0 {{ $step['success'] ? 'bg-emerald-400' : 'bg-red-400' }}"></span>
                                                <span
                                                    class="flex-1 text-zinc-600 dark:text-primary-dark-300">{{ $label }}</span>
                                                <span
                                                    class="font-semibold {{ $step['success'] ? 'text-zinc-500 dark:text-primary-dark-400' : 'text-red-500 dark:text-red-400' }}">
                                                    {{ $step['count'] ?? 0 }} terkirim
                                                </span>
                                            </div>
                                        @endforeach
                                    @endif
                                    @if (!empty($bundle->errors))
                                        <div
                                            class="mt-2 pt-2 border-t border-zinc-200 dark:border-primary-dark-700 space-y-1">
                                            @foreach (array_slice($bundle->errors, 0, 5) as $err)
                                                <p class="text-[11px] text-red-500 dark:text-red-400 leading-snug">
                                                    {{ $err }}
                                                </p>
                                            @endforeach
                                            @if (\count($bundle->errors) > 5)
                                                <p class="text-[11px] text-zinc-400 dark:text-primary-dark-500 italic">
                                                    +{{ \count($bundle->errors) - 5 }} error lainnya
                                                </p>
                                            @endif
                                        </div>
                                    @endif
                                    @php
                                        $bundleEncErrors = collect($bundle->errors ?? [])->filter(
                                            fn($e) => str_starts_with($e, '[Finish Encounter]'),
                                        );
                                    @endphp
                                    <div
                                        class="flex items-center gap-2 pt-2 text-[11px] {{ $bundle->encounter_finished ? 'text-zinc-400 dark:text-primary-dark-500' : 'text-red-500 dark:text-red-400' }}">
                                        <flux:icon
                                            name="{{ $bundle->encounter_finished ? 'check-circle' : 'x-circle' }}"
                                            class="w-3.5 h-3.5 {{ $bundle->encounter_finished ? 'text-emerald-500' : 'text-red-400' }}" />
                                        Encounter
                                        {{ $bundle->encounter_finished ? 'berhasil diselesaikan' : 'belum diselesaikan' }}
                                    </div>
                                    @foreach ($bundleEncErrors as $err)
                                        <p class="text-[11px] text-red-500 dark:text-red-400 leading-snug pl-5">
                                            ↳ {{ Str::after($err, '[Finish Encounter] ') }}
                                        </p>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <x-slot:footer>
                <x-atoms.button wire:click="$set('showSsRingkasanModal', false)"
                    variant="ghost">Tutup</x-atoms.button>
            </x-slot:footer>
        </x-organisms.modal>
    @endif
</div>
