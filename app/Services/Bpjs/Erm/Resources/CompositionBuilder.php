<?php

namespace App\Services\Bpjs\Erm\Resources;

use App\Constants\BpjsErmCodes;
use App\Helpers\ErmHelper;
use App\Models\Mapping\Icd10Map;
use App\Models\Terminology\Loinc;

class CompositionBuilder extends BaseResourceBuilder
{
    public function build(): array
    {
        // Tambah section hasil diagnostik (lab + rad)
        if (!empty($this->ctx->diagnosticTestResults)) {
            $this->ctx->compositionSections[] = $this->buildDiagnosticTestResultSection(
                $this->ctx->diagnosticIds,
                implode('', $this->ctx->diagnosticTestResults)
            );
        }

        // Tambah section default dari data klinis
        $this->appendDefaultSections();

        $reg = $this->ctx->reg;
        $dokter = $reg->dokter;
        $category = Loinc::where('loinc_num', BpjsErmCodes::LOINC_SECTION_MEDICAL_RECORDS)->firstOrFail();

        return [
            'resource' => [
                'resourceType' => 'Composition',
                'id' => $this->ctx->idComposition,
                'status' => 'final',
                'type' => [
                    'coding' => [
                        ['system' => BpjsErmCodes::SYSTEM_LOINC, 'code' => $category->loinc_num, 'display' => $category->long_common_name],
                    ],
                    'text' => $category->long_common_name,
                ],
                'subject' => [
                    'reference' => "Patient/{$this->ctx->patientData['id']}",
                    'display' => $this->ctx->patientData['data']['nm_pasien'],
                ],
                'encounter' => ['reference' => "Encounter/{$this->ctx->encounterData['id']}"],
                'date' => ErmHelper::formatDateTime($reg->tgl_registrasi->format('Y-m-d') . ' ' . $reg->jam_reg),
                'author' => [
                    ['reference' => "Practitioner/{$this->ctx->idPractitioner}", 'display' => $dokter?->nm_dokter ?? ''],
                ],
                'confidentiality' => 'N',
                'title' => 'Medical records',
                'section' => (object) $this->ctx->compositionSections,
            ],
        ];
    }

    private function appendDefaultSections(): void
    {
        $reg = $this->ctx->reg;

        $methods = [
            'buildAdmissionDiagnosis',
            'buildAdmissionReason',
            'buildChiefComplaint',
            'buildPhysicalFinding',
            'buildAssessmentNote',
            'buildPlanOfCare',
            'buildHospitalDischargeInstruction',
        ];

        foreach ($methods as $method) {
            $section = $this->{$method}($reg);
            if ($section) {
                $this->ctx->compositionSections[] = $section;
            }
        }
    }

    private function buildCompositionSection(Loinc $category, string $value): array
    {
        return [
            'title' => $category->component,
            'code' => [
                'coding' => [
                    ['system' => BpjsErmCodes::SYSTEM_LOINC, 'code' => $category->loinc_num, 'display' => $category->long_common_name],
                ],
            ],
            'text' => [
                'status' => 'additional',
                'div' => '<div xmlns="http://www.w3.org/1999/xhtml">' . ($value ?: 'Belum ada alasan masuk') . '</div>',
            ],
            'entry' => [],
        ];
    }

    private function buildDiagnosticTestResultSection(array $references, string $hasilPemeriksaan): array
    {
        $category = Loinc::where('loinc_num', BpjsErmCodes::LOINC_SECTION_DIAGNOSTIC_RESULTS)->firstOrFail();

        return [
            'title' => 'Diagnostic test results',
            'code' => [
                'coding' => [
                    ['system' => BpjsErmCodes::SYSTEM_LOINC, 'code' => $category->loinc_num, 'display' => $category->long_common_name],
                ],
            ],
            'text' => ['status' => 'additional', 'div' => $hasilPemeriksaan],
            'entry' => $references,
        ];
    }

    private function buildAdmissionDiagnosis($reg): ?array
    {
        $mapping = Icd10Map::where('icd10_code', $reg->bridgingSep->diagawal)->firstOrFail();
        if (!$mapping->system_term) {
            return null;
        }
        $category = Loinc::where('loinc_num', BpjsErmCodes::LOINC_SECTION_ADMISSION_DIAGNOSIS)->firstOrFail();
        return $this->buildCompositionSection($category, $mapping->system_term);
    }

    private function buildAdmissionReason($reg): ?array
    {
        if ($reg->bridgingSep->noskdp == '') {
            $alasan = $reg->kd_poli == 'IGDK'
                ? 'Pasien datang ke IGD dengan keluhan yang memerlukan pemeriksaan lebih lanjut.'
                : 'Rujukan FKTP untuk pemeriksaan lanjutan.';
        } else {
            if ($reg->bridgingSep->jnspelayanan == '1') {
                $alasan = 'Pasien dianjurkan rawat inap untuk observasi dan penatalaksanaan lebih lanjut akibat kondisi klinis yang memerlukan monitoring intensif.';
            } else {
                $alasan = substr($reg->bridgingSep->asal_rujukan, 0, 1) == '1'
                    ? 'Pasien kontrol rutin.'
                    : 'Pasien kontrol pasca rawat inap untuk evaluasi klinis dan terapi lanjutan';
            }
        }

        if (!$alasan) {
            return null;
        }
        $category = Loinc::where('loinc_num', BpjsErmCodes::LOINC_SECTION_ADMISSION_REASON)->firstOrFail();
        return $this->buildCompositionSection($category, $alasan);
    }

    private function buildChiefComplaint($reg): ?array
    {
        $keluhan = $reg->status_lanjut === 'Ranap' && $reg->pemeriksaanRanap->isNotEmpty()
            ? $reg->pemeriksaanRanap->where('keluhan', '!=', '')->first()?->keluhan ?? ''
            : ($reg->pemeriksaanRalan->isNotEmpty() ? $reg->pemeriksaanRalan->where('keluhan', '!=', '')->first()?->keluhan ?? '' : '');

        if (!$keluhan) {
            return null;
        }
        $category = Loinc::where('loinc_num', BpjsErmCodes::LOINC_SECTION_CHIEF_COMPLAINT)->firstOrFail();
        return $this->buildCompositionSection($category, $keluhan);
    }

    private function buildPhysicalFinding($reg): ?array
    {
        $pemeriksaan = $reg->status_lanjut === 'Ranap' && $reg->pemeriksaanRanap->isNotEmpty()
            ? $reg->pemeriksaanRanap->where('pemeriksaan', '!=', '')->first()?->pemeriksaan ?? ''
            : ($reg->pemeriksaanRalan->isNotEmpty() ? $reg->pemeriksaanRalan->where('pemeriksaan', '!=', '')->first()?->pemeriksaan ?? '' : '');

        if (!$pemeriksaan) {
            return null;
        }
        $category = Loinc::where('loinc_num', BpjsErmCodes::LOINC_SECTION_PHYSICAL_FINDINGS)->firstOrFail();
        return $this->buildCompositionSection($category, $pemeriksaan);
    }

    private function buildAssessmentNote($reg): ?array
    {
        $asesmen = $reg->status_lanjut === 'Ranap' && $reg->pemeriksaanRanap->isNotEmpty()
            ? $reg->pemeriksaanRanap->where('penilaian', '!=', '')->first()?->penilaian ?? ''
            : ($reg->pemeriksaanRalan->isNotEmpty() ? $reg->pemeriksaanRalan->where('penilaian', '!=', '')->first()?->penilaian ?? '' : '');

        if (!$asesmen) {
            return null;
        }
        $category = Loinc::where('loinc_num', BpjsErmCodes::LOINC_SECTION_ASSESSMENT)->firstOrFail();
        return $this->buildCompositionSection($category, $asesmen);
    }

    private function buildPlanOfCare($reg): ?array
    {
        $rtl = $reg->status_lanjut === 'Ranap' && $reg->pemeriksaanRanap->isNotEmpty()
            ? $reg->pemeriksaanRanap->where('rtl', '!=', '')->first()?->rtl ?? ''
            : ($reg->pemeriksaanRalan->isNotEmpty() ? $reg->pemeriksaanRalan->where('rtl', '!=', '')->first()?->rtl ?? '' : '');

        if (!$rtl) {
            return null;
        }
        $category = Loinc::where('loinc_num', BpjsErmCodes::LOINC_SECTION_PLAN_OF_CARE)->firstOrFail();
        return $this->buildCompositionSection($category, $rtl);
    }

    private function buildHospitalDischargeInstruction($reg): ?array
    {
        $instruksi = $this->ctx->jenisPelayanan == 1
            ? $reg->pemeriksaanRanap->where('instruksi', '!=', '')->first()?->instruksi ?? ''
            : $reg->pemeriksaanRalan->where('instruksi', '!=', '')->first()?->instruksi ?? '';

        if (!$instruksi) {
            return null;
        }
        $category = Loinc::where('loinc_num', BpjsErmCodes::LOINC_SECTION_DISCHARGE_INSTRUCTIONS)->firstOrFail();
        return $this->buildCompositionSection($category, $instruksi);
    }
}
