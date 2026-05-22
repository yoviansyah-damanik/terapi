<?php

namespace App\Services\Bpjs\Erm\Resources;

use App\Constants\BpjsErmCodes;
use App\Helpers\ErmHelper;
use App\Models\Bpjs\BpjsOrganization;
use App\Models\Bpjs\BpjsProcedure;
use App\Models\Mapping\OrganizationMap;
use App\Models\Mapping\RadMap;
use App\Models\Mapping\RadProcedureMap;

class DiagnosticRadBuilder extends BaseResourceBuilder
{
    /** @return array Bundle entries: rad org + DiagnosticReport + Observation resources */
    public function build(): array
    {
        $reg = $this->ctx->reg;

        if ($reg->permintaanRadiologi?->isEmpty()) {
            return [];
        }

        $radOrgData = BpjsOrganization::where('identifier', $this->ctx->radCode)->firstOrFail();
        $this->ctx->radOrganization = [
            'id' => $this->ctx->generateId($radOrgData->id),
            'display' => $radOrgData->name,
            'code' => $this->ctx->radCode,
            'map' => OrganizationMap::select('org_type_code', 'org_type_term', 'org_type_display')
                ->where('dep_id', $this->ctx->radCode)->first()->toArray(),
        ];

        $entries = [
            (new OrganizationBuilder($this->ctx))->buildForOrganization($this->ctx->radOrganization),
        ];

        foreach ($reg->permintaanRadiologi as $permintaan) {
            foreach ($permintaan->periksaRad as $periksa) {
                $procedureBpjs = BpjsProcedure::where('type', 'rad')->where('local_code', $periksa->kd_jenis_prw)->first();
                $obsId = $this->ctx->generateId($procedureBpjs?->id);

                $hasilRad = $permintaan->hasilRadiologi;
                $gambarRad = $permintaan->gambarRadiologi;

                $payloadObs = (object) [
                    'kd_jenis_prw' => $periksa->kd_jenis_prw,
                    'nama_jenis_prw' => $periksa->jenisPerawatan?->nm_perawatan ?? '',
                    'tgl_permintaan' => $permintaan->tgl_permintaan,
                    'jam_permintaan' => $permintaan->jam_permintaan,
                    'tgl_periksa' => $periksa->tgl_periksa->format('Y-m-d'),
                    'jam_periksa' => $periksa->jam,
                    'nm_dokter' => $periksa->dokter?->nm_dokter ?? '',
                    'lokasi_gambar' => $gambarRad->first()?->lokasi_gambar ?? '',
                    'diagnosa_klinis' => $permintaan->diagnosa_klinis ?? '',
                    'informasi_tambahan' => $permintaan->informasi_tambahan ?? '',
                    'hasil' => $hasilRad?->hasil ?? 'Belum ada hasil',
                ];

                $observationResource = $this->buildObservationRadResource($obsId, $payloadObs);
                $report = $this->buildDiagnosticReportResource($reg, $observationResource);

                $this->ctx->diagnosticIds[] = ['reference' => "DiagnosticReport/{$report['id']}"];
                $entries[] = ['resource' => [$report]];
            }

            $hasilTeks = $permintaan->hasilRadiologi?->hasil ?? 'Belum ada hasil';
            $this->ctx->diagnosticTestResults[] = '<div><strong>Hasil Radiologi No. ' . $permintaan->noorder . ':</strong><br/> ' . $hasilTeks . '</div>';
        }

        return $entries;
    }

    private function buildDiagnosticReportResource($reg, array $observationResource): array
    {
        return [
            'resourceType' => 'DiagnosticReport',
            'id' => $this->ctx->generateId(),
            'status' => 'final',
            'category' => [
                'coding' => [
                    'system' => BpjsErmCodes::SYSTEM_DIAGNOSTIC_SERVICE,
                    'code' => 'RAD',
                    'display' => 'Radiology',
                ],
            ],
            'subject' => [
                'reference' => "Patient/{$this->ctx->patientData['id']}",
                'display' => $this->ctx->patientData['data']['nm_pasien'],
                'noSep' => $reg->bridgingSep?->no_sep ?? '',
            ],
            'performer' => [
                [
                    'reference' => 'Organization/' . $this->ctx->radOrganization['id'],
                    'display' => $this->ctx->radOrganization['display'],
                ],
            ],
            'specimen' => [],
            'result' => [$observationResource],
        ];
    }

    private function buildObservationRadResource(string $observationId, $payload): array
    {
        $map = RadMap::where('local_code', $payload->kd_jenis_prw)->first();
        $specimenMap = RadProcedureMap::where('local_code', $payload->kd_jenis_prw)->first();
        $issued = ErmHelper::formatDateTime($payload->tgl_permintaan->format('Y-m-d') . ' ' . $payload->jam_permintaan);
        $effectiveDate = $payload->tgl_periksa
            ? ErmHelper::formatDateTime($payload->tgl_periksa . ' ' . $payload->jam_periksa)
            : $issued;

        $resource = [
            'resourceType' => 'Observation',
            'id' => $observationId,
            'status' => 'final',
            'text' => [
                'status' => 'generated',
                'div' => '<div xmlns="http://www.w3.org/1999/xhtml">Generated Narrative with Details</div>',
            ],
            'issued' => $issued,
            'effectiveDateTime' => $effectiveDate,
            'code' => [
                'coding' => [
                    'system' => $map?->system_display ?? '',
                    'code' => $map?->system_code ?? '',
                    'display' => $map?->system_term ?? '',
                ],
                'text' => $payload->nama_jenis_prw . ($map ? ' (' . $map->system_term . ')' : ''),
            ],
            'performer' => [
                'reference' => "Practitioner/{$this->ctx->idPractitioner}",
                'display' => $payload->nm_dokter ?? '',
            ],
            'valueQuantity' => ['value' => '', 'unit' => '', 'code' => ''],
            'interpretation' => ['coding' => ['value' => '', 'code' => '']],
            'referenceRange' => ['low' => ['value' => '', 'code' => ''], 'high' => ['value' => '', 'code' => '']],
            'image' => [
                'comment' => 'Hasil pemeriksaan radiologi',
                'link' => [
                    'reference' => config('app.simrs_webapps_url') . '/radiologi/' . $payload->lokasi_gambar ?? '',
                    'display' => 'Hasil pemeriksaan radiologi',
                ],
            ],
            'conclusion' => "<div>Diagnosa Klinis:{$payload->diagnosa_klinis}</div><div>Informasi Tambahan:{$payload->informasi_tambahan}</div><div>Hasil: {$payload->hasil}</div>",
        ];

        if ($specimenMap) {
            $resource['bodySite'] = [
                'coding' => [
                    ['system' => 'http://snomed.info/sct', 'code' => $specimenMap->system_code, 'display' => $specimenMap->system_term],
                ],
                'text' => $specimenMap->system_term,
            ];
        }

        return $resource;
    }
}
