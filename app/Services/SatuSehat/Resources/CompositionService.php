<?php

namespace App\Services\SatuSehat\Resources;

use App\Models\Simrs\ResumePasien;
use App\Models\Simrs\ResumePasienRanap;
use App\Services\SatuSehat\DTO\FhirResponse;
use App\Services\SatuSehat\FhirDictionary;
use App\Services\SatuSehat\SatuSehatBaseService;
use Carbon\Carbon;

class CompositionService extends SatuSehatBaseService
{
    const KEMKES = 'http://terminology.kemkes.go.id';

    protected function getResourceType(): string
    {
        return 'Composition';
    }

    public function searchBySubject(string $patientId): FhirResponse
    {
        return $this->search(['subject' => $patientId]);
    }

    public function searchByEncounter(string $encounterId): FhirResponse
    {
        return $this->search(['encounter' => $encounterId]);
    }

    public function searchByType(string $type): FhirResponse
    {
        return $this->search(['type' => $type]);
    }

    // =========================================================================
    // Base builder
    // =========================================================================

    public function createComposition(
        string $patientId,
        string $encounterId,
        string $authorId,
        string $typeCode,
        string $typeDisplay,
        string $title,
        array $sections,
        string $status = 'final',
        ?string $identifier = null,
        ?string $date = null,
        ?array $category = null,
    ): FhirResponse {
        $payload = [
            'status' => $status,
            'type' => [
                'coding' => [
                    [
                        'system' => FhirDictionary::LOINC,
                        'code' => $typeCode,
                        'display' => $typeDisplay,
                    ]
                ],
            ],
            'subject' => ['reference' => "Patient/{$patientId}"],
            'encounter' => ['reference' => "Encounter/{$encounterId}"],
            'date' => $date ?? now()->toIso8601String(),
            'author' => [['reference' => "Practitioner/{$authorId}"]],
            'title' => $title,
            'custodian' => $this->buildOrganizationReference(),
            'section' => $sections,
        ];

        if ($category) {
            $payload['category'] = $category;
        }

        if ($identifier) {
            $payload['identifier'] = [
                'system' => FhirDictionary::KEMKES_SYS_COMPOSITION . '/' . $this->getOrganizationId(),
                'value' => $identifier,
            ];
        }

        return $this->create($payload);
    }

    // =========================================================================
    // Resume Medis — Rawat Jalan (88645-7)
    // =========================================================================

    public function createResumeRalan(
        string $patientId,
        string $encounterId,
        string $authorId,
        string $patientName,
        ResumePasien $resume,
        array $resourceRefs,
        ?string $identifier = null,
        ?string $date = null,
    ): FhirResponse {
        $dateFormatted = $date
            ? Carbon::parse($date)->translatedFormat('j F Y')
            : now()->translatedFormat('j F Y');

        return $this->createComposition(
            patientId: $patientId,
            encounterId: $encounterId,
            authorId: $authorId,
            typeCode: '88645-7',
            typeDisplay: 'Outpatient hospital Discharge summary',
            title: "Resume Medis Pasien Rawat Jalan {$patientName} pada tanggal {$dateFormatted}",
            sections: $this->buildResumeRalanSections($resume, $resourceRefs),
            identifier: $identifier,
            date: $date ?? now()->toIso8601String(),
            category: [
                [
                    'coding' => [
                        [
                            'system' => FhirDictionary::LOINC,
                            'code' => 'LP173421-1',
                            'display' => 'Report',
                        ]
                    ],
                ]
            ],
        );
    }

    // =========================================================================
    // Resume Medis — Rawat Inap (34105-7)
    // =========================================================================

    public function createResumeRanap(
        string $patientId,
        string $encounterId,
        string $authorId,
        string $patientName,
        ResumePasienRanap $resume,
        array $resourceRefs,
        ?string $identifier = null,
        ?string $date = null,
    ): FhirResponse {
        $dateFormatted = $date
            ? Carbon::parse($date)->translatedFormat('j F Y')
            : now()->translatedFormat('j F Y');

        return $this->createComposition(
            patientId: $patientId,
            encounterId: $encounterId,
            authorId: $authorId,
            typeCode: '34105-7',
            typeDisplay: 'Hospital Discharge summary',
            title: "Resume Medis Pasien Rawat Inap {$patientName} pada tanggal {$dateFormatted}",
            sections: $this->buildResumeRanapSections($resume, $resourceRefs),
            identifier: $identifier,
            date: $date ?? now()->toIso8601String(),
            category: [
                [
                    'coding' => [
                        [
                            'system' => FhirDictionary::LOINC,
                            'code' => 'LP173421-1',
                            'display' => 'Report',
                        ]
                    ],
                ]
            ],
        );
    }

    // =========================================================================
    // ADIME Gizi
    // =========================================================================

    public function createAdimeGiziComposition(
        string $patientId,
        string $encounterId,
        string $authorId,
        string $asesmen,
        string $diagnosaGizi,
        string $intervensi,
        string $monitoringEvaluasi,
        string $identifier,
        ?string $date = null,
    ): FhirResponse {
        $payload = [
            'status' => 'final',
            'type' => [
                'coding' => [
                    [
                        'system' => FhirDictionary::LOINC,
                        'code' => '18842-5',
                        'display' => 'Discharge summary',
                    ]
                ],
            ],
            'category' => [
                [
                    'coding' => [
                        [
                            'system' => FhirDictionary::LOINC,
                            'code' => 'LP173421-1',
                            'display' => 'Report',
                        ]
                    ],
                ]
            ],
            'subject' => ['reference' => "Patient/{$patientId}"],
            'encounter' => ['reference' => "Encounter/{$encounterId}"],
            'date' => $date ?? now()->toIso8601String(),
            'author' => [['reference' => "Practitioner/{$authorId}"]],
            'title' => 'Catatan Asuhan Gizi ADIME',
            'custodian' => $this->buildOrganizationReference(),
            'section' => [
                $this->buildAdimeSection('75305-3', 'Nutrition assessment Note', $asesmen),
                $this->buildAdimeSection('75330-1', 'Nutrition diagnosis', $diagnosaGizi),
                $this->buildAdimeSection('42344-2', 'Discharge diet (narrative)', $intervensi),
                $this->buildAdimeSection('75322-8', 'Nutrition monitoring and evaluation', $monitoringEvaluasi),
            ],
        ];

        $payload['identifier'] = [
            'system' => FhirDictionary::KEMKES_SYS_COMPOSITION . '/' . $this->getOrganizationId(),
            'value' => $identifier,
        ];

        return $this->create($payload);
    }

    // =========================================================================
    // Section builders
    // =========================================================================

    /**
     * Section umum dengan kode LOINC, teks narratif, entries, dan sub-sections opsional.
     */
    public function buildSection(
        string $code,
        string $display,
        string $text,
        array $entries = [],
        ?string $title = null,
        ?array $subSections = null,
        string $system = FhirDictionary::LOINC,
    ): array {
        $section = [
            'code' => [
                'coding' => [
                    [
                        'system' => $system,
                        'code' => $code,
                        'display' => $display,
                    ]
                ],
            ],
            'text' => [
                'status' => 'generated',
                'div' => '<div xmlns="http://www.w3.org/1999/xhtml">' . e($text) . '</div>',
            ],
        ];

        if ($title !== null) {
            $section = array_merge(['title' => $title], $section);
        }

        if (!empty($entries)) {
            $section['entry'] = array_map(fn($ref) => ['reference' => $ref], $entries);
        }

        if (!empty($subSections)) {
            $section['section'] = $subSections;
        }

        return $section;
    }

    // =========================================================================
    // Medication Composition (TK000013)
    // =========================================================================

    /**
     * Buat Composition tipe "Obat" (TK000013) untuk satu encounter.
     * Entries diurutkan interleaved per obat: MedRequest → MedDispense(s) → MedAdmin(s).
     *
     * @param  array<string>  $groupedEntries  e.g. ["MedicationRequest/xxx", "MedicationDispense/yyy", ...]
     */
    public function createMedicationComposition(
        string $patientId,
        string $encounterId,
        string $authorId,
        array $groupedEntries,
        string $status = 'final',
        ?string $identifier = null,
        ?string $date = null,
    ): FhirResponse {
        $payload = [
            'status' => $status,
            'type' => [
                'coding' => [
                    [
                        'system' => self::KEMKES,
                        'code' => 'TK000013',
                        'display' => 'Obat',
                    ]
                ],
            ],
            'subject' => ['reference' => "Patient/{$patientId}"],
            'encounter' => ['reference' => "Encounter/{$encounterId}"],
            'date' => $date ?? now()->toIso8601String(),
            'author' => [['reference' => "Practitioner/{$authorId}"]],
            'title' => 'Medication',
            'custodian' => $this->buildOrganizationReference(),
            'section' => [
                [
                    'code' => [
                        'coding' => [
                            [
                                'system' => FhirDictionary::LOINC,
                                'code' => '42346-7',
                                'display' => 'Medications on admission (narrative)',
                            ]
                        ],
                    ],
                    'entry' => array_map(fn($ref) => ['reference' => $ref], $groupedEntries),
                ]
            ],
        ];

        if ($identifier) {
            $payload['identifier'] = [
                'system' => FhirDictionary::KEMKES_SYS_COMPOSITION . '/' . $this->getOrganizationId(),
                'value' => $identifier,
            ];
        }

        return $this->create($payload);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function buildAdimeSection(string $code, string $display, string $text): array
    {
        return [
            'code' => [
                'coding' => [
                    [
                        'system' => FhirDictionary::LOINC,
                        'code' => $code,
                        'display' => $display,
                    ]
                ],
            ],
            'text' => [
                'status' => 'additional',
                'div' => '<div xmlns="http://www.w3.org/1999/xhtml">' . e($text) . '</div>',
            ],
        ];
    }

    /** Bangun section dengan entry saja (tanpa teks), dilewati jika entries kosong. */
    private function section(
        string $title,
        string $system,
        string $code,
        string $display,
        string $text,
        array $entries = [],
        array $subSections = [],
    ): ?array {
        if (empty($entries) && empty($subSections) && $text === '') {
            return null;
        }

        $s = [
            'title' => $title,
            'code' => [
                'coding' => [
                    [
                        'system' => $system,
                        'code' => $code,
                        'display' => $display,
                    ]
                ],
            ],
        ];

        if ($text !== '') {
            $s['text'] = [
                'status' => 'additional',
                'div' => '<div xmlns="http://www.w3.org/1999/xhtml">' . e($text) . '</div>',
            ];
        }

        if (!empty($entries)) {
            $s['entry'] = array_map(fn($ref) => ['reference' => $ref], $entries);
        }

        if (!empty($subSections)) {
            $s['section'] = array_values(array_filter($subSections));
        }

        return $s;
    }

    private function refs(array $ihsNumbers): array
    {
        return array_filter(array_unique($ihsNumbers));
    }

    private function prefixed(string $type, array $ids): array
    {
        return array_map(fn($id) => "{$type}/{$id}", array_filter($ids));
    }

    // =========================================================================
    // Resume Ralan — section builder
    // =========================================================================

    private function buildResumeRalanSections(ResumePasien $r, array $refs): array
    {
        $conditions = $this->prefixed('Condition', $refs['conditions'] ?? []);
        $condPrimary = array_slice($conditions, 0, 1);
        $condOther = array_slice($conditions, 1);
        $allergies = $this->prefixed('AllergyIntolerance', $refs['allergies'] ?? []);
        $medStmts = $this->prefixed('MedicationStatement', $refs['med_statements'] ?? []);
        $obsTtv = $this->prefixed('Observation', $refs['observations_ttv'] ?? []);
        $clinImps = $this->prefixed('ClinicalImpression', $refs['clinical_imps'] ?? []);
        $carePlans = $this->prefixed('CarePlan', $refs['care_plans'] ?? []);
        $srLab = $this->prefixed('ServiceRequest', $refs['sr_lab'] ?? []);
        $specLab = $this->prefixed('Specimen', $refs['specimens'] ?? []);
        $drLab = $this->prefixed('DiagnosticReport', $refs['dr_lab'] ?? []);
        $srRad = $this->prefixed('ServiceRequest', $refs['sr_rad'] ?? []);
        $imaging = $this->prefixed('ImagingStudy', $refs['imaging'] ?? []);
        $drRad = $this->prefixed('DiagnosticReport', $refs['dr_rad'] ?? []);
        $procedures = $this->prefixed('Procedure', $refs['procedures'] ?? []);
        $medReqs = $this->prefixed('MedicationRequest', $refs['med_requests'] ?? []);
        $medDisp = $this->prefixed('MedicationDispense', $refs['med_dispenses'] ?? []);
        $medAdmins = $this->prefixed('MedicationAdministration', $refs['med_admins'] ?? []);

        // Diagnosa: gabungkan teks dari semua kolom diagnosa
        $diagnosaTeks = collect([
            $r->diagnosa_utama,
            $r->diagnosa_sekunder,
            $r->diagnosa_sekunder2,
            $r->diagnosa_sekunder3,
            $r->diagnosa_sekunder4,
        ])->filter()->implode('; ');

        // Prosedur: gabungkan teks
        $prosedurTeks = collect([
            $r->prosedur_utama,
            $r->prosedur_sekunder,
            $r->prosedur_sekunder2,
            $r->prosedur_sekunder3,
        ])->filter()->implode('; ');

        $sections = [];

        // 1. Anamnesis
        $anamSubs = array_filter([
            $this->section('Keluhan Utama', FhirDictionary::LOINC, '10154-3', 'Chief complaint Narrative - Reported', $r->keluhan_utama ?? '', $condPrimary),
            !empty($condOther) ? $this->section('Keluhan Penyerta', FhirDictionary::LOINC, '11450-4', 'Problem list - Reported', '', $condOther) : null,
            !empty($allergies) ? $this->section('Riwayat Alergi', FhirDictionary::LOINC, '48765-2', 'Allergies', '', $allergies) : null,
            !empty($medStmts) ? $this->section('Riwayat Pengobatan', FhirDictionary::LOINC, '10160-0', 'History of Medication use Narrative', '', $medStmts) : null,
        ]);
        if (!empty($anamSubs)) {
            $sections[] = $this->section('Anamnesis', self::KEMKES, 'TK000003', 'Anamnesis', '', [], array_values($anamSubs));
        }

        // 2. Pemeriksaan Fisik (TTV)
        if (!empty($obsTtv)) {
            $sections[] = $this->section('Pemeriksaan Fisik', self::KEMKES, 'TK000007', 'Pemeriksaan Fisik', '', [], [
                $this->section('Tanda Vital', FhirDictionary::LOINC, '8716-3', 'Vital signs', '', $obsTtv),
            ]);
        }

        // 3. Perencanaan Perawatan
        $perencanaanEntries = array_merge($clinImps, $carePlans);
        if (!empty($perencanaanEntries)) {
            $sections[] = $this->section('Perencanaan Perawatan', FhirDictionary::LOINC, '18776-5', 'Plan of care note', '', $perencanaanEntries);
        }

        // 4. Pemeriksaan Penunjang
        $penunjangSubs = array_filter([
            (!empty($srLab) || !empty($specLab) || !empty($drLab) || ($r->pemeriksaan_penunjang || $r->hasil_laborat))
            ? $this->section(
                'Hasil Pemeriksaan Laboratorium',
                FhirDictionary::LOINC,
                '11502-2',
                'Laboratory report',
                trim(($r->pemeriksaan_penunjang ?? '') . "\n" . ($r->hasil_laborat ?? '')),
                array_merge($srLab, $specLab, $drLab)
            )
            : null,
            (!empty($srRad) || !empty($imaging) || !empty($drRad))
            ? $this->section(
                'Hasil Pemeriksaan Radiologi',
                FhirDictionary::LOINC,
                '18782-3',
                'Radiology Study observation (narrative)',
                '',
                array_merge($srRad, $imaging, $drRad)
            )
            : null,
        ]);
        if (!empty($penunjangSubs)) {
            $sections[] = $this->section('Pemeriksaan Penunjang', self::KEMKES, 'TK000009', 'Hasil Pemeriksaan Penunjang', '', [], array_values($penunjangSubs));
        }

        // 5. Diagnosis
        if (!empty($conditions) || $diagnosaTeks) {
            $sections[] = $this->section('Diagnosis', self::KEMKES, 'TK000004', 'Diagnosis', '', [], [
                $this->section('Diagnosis Akhir', FhirDictionary::LOINC, '78375-3', 'Discharge diagnosis Narrative', $diagnosaTeks, $conditions),
            ]);
        }

        // 6. Tindakan/Prosedur Medis
        if (!empty($procedures) || $prosedurTeks) {
            $sections[] = $this->section('Tindakan/Prosedur Medis', self::KEMKES, 'TK000005', 'Tindakan/Prosedur Medis', $prosedurTeks, $procedures);
        }

        // 7. Farmasi
        $farmSubs = array_filter([
            (!empty($medReqs) || !empty($medDisp) || !empty($medAdmins))
            ? $this->section(
                'Obat Saat Kunjungan',
                FhirDictionary::LOINC,
                '42346-7',
                'Medications on admission (narrative)',
                '',
                array_merge($medReqs, $medDisp, $medAdmins)
            )
            : null,
            $r->obat_pulang
            ? $this->section('Obat Pulang', FhirDictionary::LOINC, '75311-1', 'Discharge medications Narrative', $r->obat_pulang ?? '')
            : null,
        ]);
        if (!empty($farmSubs)) {
            $sections[] = $this->section('Farmasi', self::KEMKES, 'TK000013', 'Obat', '', [], array_values($farmSubs));
        }

        // 8. Kondisi Saat Meninggalkan RS
        if ($r->kondisi_pulang) {
            $sections[] = $this->section('Kondisi Saat Meninggalkan Rumah Sakit', FhirDictionary::LOINC, '10184-0', 'Hospital discharge physical findings Narrative', $r->kondisi_pulang ?? '');
        }

        // 9. Perjalanan Kunjungan
        if ($r->jalannya_penyakit) {
            $sections[] = $this->section('Perjalanan Kunjungan Pasien', FhirDictionary::LOINC, '8648-8', 'Hospital course Narrative', $r->jalannya_penyakit ?? '');
        }

        return array_values(array_filter($sections));
    }

    // =========================================================================
    // Resume Ranap — section builder
    // =========================================================================

    private function buildResumeRanapSections(ResumePasienRanap $r, array $refs): array
    {
        $conditions = $this->prefixed('Condition', $refs['conditions'] ?? []);
        $condPrimary = array_slice($conditions, 0, 1);
        $condOther = array_slice($conditions, 1);
        $allergies = $this->prefixed('AllergyIntolerance', $refs['allergies'] ?? []);
        $medStmts = $this->prefixed('MedicationStatement', $refs['med_statements'] ?? []);
        $obsTtv = $this->prefixed('Observation', $refs['observations_ttv'] ?? []);
        $clinImps = $this->prefixed('ClinicalImpression', $refs['clinical_imps'] ?? []);
        $carePlans = $this->prefixed('CarePlan', $refs['care_plans'] ?? []);
        $srLab = $this->prefixed('ServiceRequest', $refs['sr_lab'] ?? []);
        $specLab = $this->prefixed('Specimen', $refs['specimens'] ?? []);
        $drLab = $this->prefixed('DiagnosticReport', $refs['dr_lab'] ?? []);
        $srRad = $this->prefixed('ServiceRequest', $refs['sr_rad'] ?? []);
        $imaging = $this->prefixed('ImagingStudy', $refs['imaging'] ?? []);
        $drRad = $this->prefixed('DiagnosticReport', $refs['dr_rad'] ?? []);
        $procedures = $this->prefixed('Procedure', $refs['procedures'] ?? []);
        $medReqs = $this->prefixed('MedicationRequest', $refs['med_requests'] ?? []);
        $medDisp = $this->prefixed('MedicationDispense', $refs['med_dispenses'] ?? []);
        $medAdmins = $this->prefixed('MedicationAdministration', $refs['med_admins'] ?? []);

        $diagnosaTeks = collect([
            $r->diagnosa_utama,
            $r->diagnosa_sekunder,
            $r->diagnosa_sekunder2,
            $r->diagnosa_sekunder3,
            $r->diagnosa_sekunder4,
        ])->filter()->implode('; ');

        $prosedurTeks = collect([
            $r->prosedur_utama,
            $r->prosedur_sekunder,
            $r->prosedur_sekunder2,
            $r->prosedur_sekunder3,
            $r->tindakan_dan_operasi,
        ])->filter()->implode('; ');

        $kondisiPulang = collect([
            $r->keadaan,
            $r->ket_keadaan,
            $r->cara_keluar,
            $r->ket_keluar,
        ])->filter()->implode(' — ');

        $rtl = collect([
            $r->kontrol ? 'Kontrol: ' . $r->kontrol : null,
            $r->dilanjutkan ? 'Lanjut: ' . $r->ket_dilanjutkan : null,
            $r->lab_belum ? 'Lab pending: ' . $r->lab_belum : null,
        ])->filter()->implode('; ');

        $sections = [];

        // 1. Anamnesis
        $anamSubs = array_filter([
            $this->section('Keluhan Utama', FhirDictionary::LOINC, '10154-3', 'Chief complaint Narrative - Reported', $r->keluhan_utama ?? '', $condPrimary),
            ($r->alasan || !empty($condOther)) ? $this->section('Keluhan Penyerta', FhirDictionary::LOINC, '11450-4', 'Problem list - Reported', $r->alasan ?? '', $condOther) : null,
            $r->diagnosa_awal ? $this->section('Riwayat Penyakit Pribadi Sekarang', FhirDictionary::LOINC, '10164-2', 'History of Present illness Narrative', $r->diagnosa_awal ?? '') : null,
            ($r->alergi || !empty($allergies)) ? $this->section('Riwayat Alergi', FhirDictionary::LOINC, '48765-2', 'Allergies and adverse reactions Document', $r->alergi ?? '', $allergies) : null,
            !empty($medStmts) ? $this->section('Riwayat Pengobatan', FhirDictionary::LOINC, '10160-0', 'History of Medication use Narrative', '', $medStmts) : null,
        ]);
        if (!empty($anamSubs)) {
            $sections[] = $this->section('Anamnesis', self::KEMKES, 'TK000003', 'Anamnesis', '', [], array_values($anamSubs));
        }

        // 2. Pemeriksaan Fisik
        $pemfizSubs = array_filter([
            (!empty($obsTtv)) ? $this->section('Tanda Vital', FhirDictionary::LOINC, '8716-3', 'Vital signs', '', $obsTtv) : null,
            $r->pemeriksaan_fisik ? $this->section('Pemeriksaan Fisik Head to Toe', FhirDictionary::LOINC, '10187-3', 'Review of systems Narrative - Reported', $r->pemeriksaan_fisik ?? '') : null,
        ]);
        if (!empty($pemfizSubs)) {
            $sections[] = $this->section('Pemeriksaan Fisik', self::KEMKES, 'TK000007', 'Pemeriksaan Fisik', '', [], array_values($pemfizSubs));
        }

        // 3. Perencanaan Perawatan
        $perencanaanEntries = array_merge($clinImps, $carePlans);
        if (!empty($perencanaanEntries)) {
            $sections[] = $this->section('Perencanaan Perawatan', FhirDictionary::LOINC, '18776-5', 'Plan of treatment (narrative)', '', $perencanaanEntries);
        }

        // 4. Pemeriksaan Penunjang
        $penunjangSubs = array_filter([
            (!empty($srLab) || !empty($specLab) || !empty($drLab) || $r->pemeriksaan_penunjang || $r->hasil_laborat)
            ? $this->section(
                'Hasil Pemeriksaan Laboratorium',
                FhirDictionary::LOINC,
                '11502-2',
                'Laboratory report',
                trim(($r->pemeriksaan_penunjang ?? '') . "\n" . ($r->hasil_laborat ?? '')),
                array_merge($srLab, $specLab, $drLab)
            )
            : null,
            (!empty($srRad) || !empty($imaging) || !empty($drRad))
            ? $this->section(
                'Hasil Pemeriksaan Radiologi',
                FhirDictionary::LOINC,
                '18782-3',
                'Radiology Study observation (narrative)',
                '',
                array_merge($srRad, $imaging, $drRad)
            )
            : null,
        ]);
        if (!empty($penunjangSubs)) {
            $sections[] = $this->section('Pemeriksaan Penunjang', self::KEMKES, 'TK000009', 'Hasil Pemeriksaan Penunjang', '', [], array_values($penunjangSubs));
        }

        // 5. Diagnosis
        if (!empty($conditions) || $diagnosaTeks) {
            $sections[] = $this->section('Diagnosis', self::KEMKES, 'TK000004', 'Diagnosis', '', [], [
                $this->section('Diagnosis Akhir', FhirDictionary::LOINC, '78375-3', 'Discharge diagnosis Narrative', $diagnosaTeks, $conditions),
            ]);
        }

        // 6. Tindakan/Prosedur Medis
        if (!empty($procedures) || $prosedurTeks) {
            $sections[] = $this->section('Tindakan/Prosedur Medis', self::KEMKES, 'TK000005', 'Tindakan/Prosedur Medis', $prosedurTeks, $procedures);
        }

        // 7. Farmasi
        $farmSubs = array_filter([
            (!empty($medReqs) || !empty($medDisp) || !empty($medAdmins) || $r->obat_di_rs)
            ? $this->section(
                'Obat Saat Kunjungan',
                FhirDictionary::LOINC,
                '42346-7',
                'Medications on admission (narrative)',
                $r->obat_di_rs ?? '',
                array_merge($medReqs, $medDisp, $medAdmins)
            )
            : null,
            $r->obat_pulang
            ? $this->section('Obat Pulang', FhirDictionary::LOINC, '75311-1', 'Discharge medications Narrative', $r->obat_pulang ?? '')
            : null,
        ]);
        if (!empty($farmSubs)) {
            $sections[] = $this->section('Farmasi', self::KEMKES, 'TK000013', 'Obat', '', [], array_values($farmSubs));
        }

        // 8. Diet
        if ($r->diet) {
            $sections[] = $this->section('Diet', self::KEMKES, 'TK000013', 'Obat', '', [], [
                $this->section('Rekomendasi Diet', FhirDictionary::LOINC, '42344-2', 'Discharge diet (narrative)', $r->diet ?? ''),
            ]);
        }

        // 9. Edukasi
        if ($r->edukasi) {
            $sections[] = $this->section('Edukasi', FhirDictionary::LOINC, '34895-3', 'Education note', $r->edukasi ?? '');
        }

        // 10. Kondisi Saat Meninggalkan RS
        if ($kondisiPulang) {
            $sections[] = $this->section('Kondisi Saat Meninggalkan Rumah Sakit', FhirDictionary::LOINC, '10184-0', 'Hospital discharge physical findings Narrative', $kondisiPulang);
        }

        // 11. Rencana Tindak Lanjut
        if ($rtl) {
            $sections[] = $this->section('Rencana Tindak Lanjut', FhirDictionary::LOINC, '8653-8', 'Hospital Discharge instructions', $rtl);
        }

        // 12. Perjalanan Kunjungan
        if ($r->jalannya_penyakit) {
            $sections[] = $this->section('Perjalanan Kunjungan Pasien', FhirDictionary::LOINC, '8648-8', 'Hospital course Narrative', $r->jalannya_penyakit ?? '');
        }

        return array_values(array_filter($sections));
    }
}
