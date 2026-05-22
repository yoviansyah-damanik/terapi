<?php

namespace App\Services\Bpjs\Erm\Resources;

use App\Constants\BpjsErmCodes;
use App\Helpers\ErmHelper;
use App\Models\Bpjs\BpjsOrganization;
use App\Models\Bpjs\BpjsPractitioner;
use App\Models\Bpjs\BpjsProcedure;
use App\Models\Mapping\LabItemMap;
use App\Models\Mapping\LabSpecimenMap;
use App\Models\Mapping\OrganizationMap;
use App\Models\Simrs\TemplateLaboratorium;

class DiagnosticLabBuilder extends BaseResourceBuilder
{
    /** @return array Bundle entries: lab org + DiagnosticReport + Specimen + Observation resources */
    public function build(): array
    {
        $reg = $this->ctx->reg;

        $hasLab = $reg->permintaanLab?->isNotEmpty()
            || !empty($reg->permintaanLabMb)
            || !empty($reg->permintaanLabPa);

        if (!$hasLab) {
            return [];
        }

        $labOrgData = BpjsOrganization::where('identifier', $this->ctx->labCode)->firstOrFail();
        $this->ctx->labOrganization = [
            'id' => $this->ctx->generateId($labOrgData->id),
            'display' => $labOrgData->name,
            'code' => $this->ctx->labCode,
            'map' => OrganizationMap::select('org_type_code', 'org_type_term', 'org_type_display')
                ->where('dep_id', $this->ctx->labCode)->first()->toArray(),
        ];

        $entries = [
            (new OrganizationBuilder($this->ctx))->buildForOrganization($this->ctx->labOrganization),
        ];

        if ($reg->permintaanLab?->isNotEmpty()) {
            foreach ($reg->permintaanLab as $permintaan) {
                $observationResources = [];
                $specimenResources = [];
                foreach ($permintaan->periksaLab as $periksa) {
                    $practitioner = BpjsPractitioner::where('identifier', $periksa->kd_dokter)->first();
                    $this->ctx->labPerformer = [
                        'id' => $this->ctx->generateId($practitioner?->id),
                        'display' => $practitioner?->name ?? 'Unknown',
                        'code' => $practitioner?->identifier ?? '',
                    ];

                    $specimenId = $this->ctx->generateId(
                        BpjsProcedure::where('type', 'lab')->where('local_code', $periksa->kd_jenis_prw)->first()?->id
                    );

                    $specimenResources[] = $this->buildSpecimenLabResource($specimenId, $permintaan, $periksa);

                    foreach ($periksa->detailPeriksaLab as $detail) {
                        $itemId = $this->ctx->generateId(
                            BpjsProcedure::where('type', 'item_lab')->where('local_code', $detail->id_template)->first()?->id
                        );
                        $observationResources[] = $this->buildObservationLabResource($itemId, $specimenId, $periksa, $detail);
                    }
                }
                $kesan = $permintaan->kesanSaran->kesan ?? '';
                $saran = $permintaan->kesanSaran->saran ?? '';
                $conclusionText = '';
                if ($kesan || $saran) {
                    $conclusionText = "Kesan: " . ($kesan ?: 'Belum ada kesan') . "\nSaran: " . ($saran ?: 'Belum ada saran');
                }

                $this->ctx->diagnosticTestResults[] = '<div><strong>Hasil Lab No. ' . $permintaan->noorder . ':</strong><br/>'
                    . '<strong>Saran:</strong><br/>' . ($saran ?: 'Belum ada saran')
                    . '<br/><strong>Kesan:</strong><br/>' . ($kesan ?: 'Belum ada kesan')
                    . '</br><br/></div>';

                $report = $this->buildDiagnosticReportResource($reg, 'lab', [
                    'result' => $observationResources,
                    'specimen' => $specimenResources,
                    'conclusion' => $conclusionText,
                ]);

                $this->ctx->diagnosticIds[] = ['reference' => "DiagnosticReport/{$report['id']}"];
                $entries[] = ['resource' => [$report]];
            }
        }

        return $entries;
    }

    private function buildDiagnosticReportResource($reg, string $type, array $payload): array
    {
        $diagnosticId = $this->ctx->generateId();

        $report = [
            'resourceType' => 'DiagnosticReport',
            'id' => $diagnosticId,
            'status' => 'final',
            'category' => [
                'coding' => [
                    'system' => BpjsErmCodes::SYSTEM_DIAGNOSTIC_SERVICE,
                    'code' => 'LAB',
                    'display' => 'Laboratory',
                ],
            ],
            'subject' => [
                'reference' => "Patient/{$this->ctx->patientData['id']}",
                'display' => $this->ctx->patientData['data']['nm_pasien'],
                'noSep' => $reg->bridgingSep?->no_sep ?? '',
            ],
            'performer' => [
                [
                    'reference' => 'Organization/' . $this->ctx->labOrganization['id'],
                    'display' => $this->ctx->labOrganization['display'],
                ],
            ],
            'specimen' => $payload['specimen'] ?? [],
            'result' => $payload['result'] ?? [],
        ];

        if (!empty($payload['conclusion'])) {
            $report['conclusion'] = $payload['conclusion'];
        }

        return $report;
    }

    private function buildSpecimenLabResource(string $specimenId, $permintaan, $periksa): array
    {
        $specimenMap = LabSpecimenMap::where('local_code', $periksa->kd_jenis_prw)->first();

        return [
            'resourceType' => 'Specimen',
            'id' => $specimenId,
            'identifier' => [
                [
                    'system' => 'simrs.rumkittnipsp.com/accession-number',
                    'value' => $permintaan->noorder,
                    'assigner' => ['reference' => "Organization/{$this->ctx->labOrganization['id']}"],
                ],
            ],
            'status' => 'available',
            'subject' => [
                'reference' => "Patient/{$this->ctx->patientData['id']}",
                'display' => $this->ctx->patientData['data']['nm_pasien'],
            ],
            'receivedTime' => ErmHelper::formatDateTime($permintaan->tgl_sampel->format('Y-m-d') . ' ' . $permintaan->jam_sampel),
            'collection' => [
                'collectedDateTime' => ErmHelper::formatDateTime($permintaan->tgl_sampel->format('Y-m-d') . ' ' . $permintaan->jam_sampel),
                'extension' => [
                    [
                        'url' => 'https://fhir.kemkes.go.id/r4/StructureDefinition/CollectorOrganization',
                        'valueReference' => ['reference' => "Organization/{$this->ctx->labOrganization['id']}"],
                    ],
                ],
            ],
            'type' => [
                'coding' => [
                    [
                        'system' => $specimenMap?->system_display ?? 'http://snomed.info/sct',
                        'code' => $specimenMap?->system_code ?? '',
                        'display' => $specimenMap?->system_term ?? '',
                    ],
                ],
                'text' => ($periksa->jenisPerawatan?->nm_perawatan ?? $periksa->kd_jenis_prw)
                    . ($specimenMap ? ' (' . $specimenMap->system_term . ')' : ''),
            ],
            'extension' => [
                [
                    'url' => 'https://fhir.kemkes.go.id/r4/StructureDefinition/TransportedTime',
                    'valueDateTime' => ErmHelper::formatDateTime($permintaan->tgl_sampel->format('Y-m-d') . ' ' . $permintaan->jam_sampel),
                ],
            ],
        ];
    }

    private function buildObservationLabResource(string $observationId, string $specimenId, $periksa, $detail): array
    {
        $effectiveDateTime = ErmHelper::formatDateTime($periksa->tgl_periksa->format('Y-m-d') . ' ' . $periksa->jam);
        $template = TemplateLaboratorium::where('kd_jenis_prw', $detail->kd_jenis_prw)
            ->where('id_template', $detail->id_template)->first();

        $unit = $template?->satuan ?: '';
        $nilaiRujukan = $detail->nilai_rujukan ?? ($template ? "{$template->nilai_rujukan_ld} - {$template->nilai_rujukan_la}" : null);

        [$valueLow, $valueTop] = $this->parseNilaiRujukan($nilaiRujukan);

        $itemMap = LabItemMap::where('kd_jenis_prw', $detail->kd_jenis_prw)
            ->where('id_template', $detail->id_template)->first();
        $namaItem = $template?->Pemeriksaan ?? ($periksa->jenisPerawatan?->nm_perawatan ?? $detail->kd_jenis_prw);

        return [
            'resourceType' => 'Observation',
            'id' => $observationId,
            'status' => 'final',
            'code' => [
                'coding' => [
                    'system' => $itemMap?->system_display ?? '',
                    'code' => $itemMap?->system_code ?? '',
                    'display' => $itemMap?->system_term ?? $namaItem,
                ],
                'text' => $itemMap?->system_term ?? $namaItem,
            ],
            'effectiveDateTime' => $effectiveDateTime,
            'issued' => $effectiveDateTime,
            'specimen' => ['reference' => "Specimen/{$specimenId}"],
            'performer' => [
                ['reference' => "Organization/{$this->ctx->labOrganization['id']}"],
                ['reference' => "Practitioner/{$this->ctx->labPerformer['id']}"],
            ],
            'valueString' => $detail->nilai ?? '',
            'valueQuantity' => ['value' => $detail->nilai, 'unit' => $unit, 'code' => $unit],
            'interpretation' => [
                'coding' => [
                    'system' => BpjsErmCodes::SYSTEM_OBSERVATION_INTERPRETATION,
                    'code' => match ($detail->keterangan) {
                        '', null, 'n', 'normal' => 'N',
                        'H', 'h', 'high' => 'H',
                        'L', 'l', 'low' => 'L',
                        default => $detail->keterangan,
                    },
                    'display' => match ($detail->keterangan) {
                        '', null, 'n', 'normal' => 'Normal',
                        'H', 'h', 'high' => 'High',
                        'L', 'l', 'low' => 'Low',
                        default => $detail->keterangan,
                    },
                ],
            ],
            'referenceRange' => [
                'low' => ['value' => $valueLow, 'unit' => $unit, 'system' => BpjsErmCodes::SYSTEM_UCUM, 'code' => $unit],
                'high' => ['value' => $valueTop, 'unit' => $unit, 'system' => BpjsErmCodes::SYSTEM_UCUM, 'code' => $unit],
            ],
            'conclusion' => match ($detail->keterangan) {
                '', null, 'n', 'normal' => 'Normal',
                'H', 'h', 'high' => 'High',
                'L', 'l', 'low' => 'Low',
                default => $detail->keterangan,
            },
        ];
    }

    private function parseNilaiRujukan(?string $nilaiRujukan): array
    {
        if (!$nilaiRujukan) {
            return [0, 0];
        }

        $nr = trim($nilaiRujukan);
        if (preg_match('/^<\s*(\d+\.?\d*)$/', $nr, $m)) {
            return [0, (float) $m[1]];
        }
        if (preg_match('/^>\s*(\d+\.?\d*)$/', $nr, $m)) {
            return [(float) $m[1], 0];
        }
        if (preg_match('/^(\d+\.?\d*)\s*-\s*(\d+\.?\d*)$/', $nr, $m)) {
            return [(float) $m[1], (float) $m[2]];
        }

        return [$nr, $nr];
    }
}
