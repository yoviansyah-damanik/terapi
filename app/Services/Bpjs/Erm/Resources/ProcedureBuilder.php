<?php

namespace App\Services\Bpjs\Erm\Resources;

use App\Constants\BpjsErmCodes;
use App\Helpers\ErmHelper;
use App\Models\Bpjs\BpjsIcd9;
use App\Models\Bpjs\BpjsProcedure;
use App\Models\Mapping\EmployeeMap;
use App\Models\Mapping\Icd9Map;
use App\Models\Mapping\ProcedureMap;

class ProcedureBuilder extends BaseResourceBuilder
{
    /** @return array Bundle entries for all procedure resources */
    public function build(): array
    {
        $entries = [];
        $reg = $this->ctx->reg;

        // ICD-9 procedures
        foreach ($reg->prosedurPasien as $prosedur) {
            $idProcedure = BpjsIcd9::where('code', $prosedur->kode)->firstOrFail()?->id;
            $entries[] = [
                'resource' => [$this->buildProcedureResource($idProcedure, $prosedur, 'icd')],
            ];
        }

        // Tindakan procedures
        $tindakanCollections = $reg->status_lanjut === 'Ranap'
            ? [$reg->rawatInapDr, $reg->rawatInapPr, $reg->rawatInapDrPr]
            : [$reg->rawatJlDr, $reg->rawatJlPr, $reg->rawatJlDrPr];

        foreach ($tindakanCollections as $collection) {
            foreach ($collection as $tindakan) {
                $idProcedure = BpjsProcedure::where('type', strtolower($reg->status_lanjut))
                    ->where('local_code', $tindakan->kd_jenis_prw)->firstOrFail()?->id;
                $entries[] = [
                    'resource' => [$this->buildProcedureResource($idProcedure, $tindakan, 'tindakan')],
                ];
            }
        }

        return $entries;
    }

    private function buildProcedureResource(string $id, $prosedur, string $type): array
    {
        $reg = $this->ctx->reg;
        $tglPeriksa = $reg->tgl_registrasi->format('Y-m-d') . ' ' . $reg->jam_reg;

        if ($type === 'tindakan') {
            $tglMasuk = ErmHelper::formatDateTime($prosedur->tgl_perawatan->format('Y-m-d') . ' ' . $prosedur->jam_rawat);
            $tglKeluar = $tglMasuk;
        } else {
            $tglMasuk = ErmHelper::formatDateTime($tglPeriksa);
            $tglKeluar = ErmHelper::formatDateTime($reg->mutasiBerkas?->kembali ?? now());

            if ($reg->status_lanjut === 'Ranap' && $reg->kamarInap->isNotEmpty()) {
                $kamar = $reg->kamarInap->first();
                $tglMasuk = ErmHelper::formatDateTime($kamar->tgl_masuk->format('Y-m-d') . ' ' . $kamar->jam_masuk);
                $tglKeluar = ErmHelper::formatDateTime($kamar->tgl_keluar->format('Y-m-d') . ' ' . $kamar->jam_keluar);
            }
        }

        if ($type === 'icd') {
            $mapping = Icd9Map::where('icd9_code', $prosedur->kode)->first();
        } else {
            $sourceTable = str_contains($prosedur->getTable(), 'jl_') ? 'jalan' : 'inap';
            $mapping = ProcedureMap::where('procedure_code', $prosedur->kd_jenis_prw)->where('source_table', $sourceTable)->first();
        }

        $doctorSpecialty = EmployeeMap::where('employee_id', $reg->dokter?->kd_dokter)->first();

        $codings = [];
        $textDisplay = '';

        if ($type === 'icd') {
            $icd9Desc = $prosedur->icd9?->deskripsi_panjang ?? $prosedur->icd9?->deskripsi_pendek ?? '';
            $codings[] = [
                'system' => 'http://hl7.org/fhir/sid/icd-9-cm',
                'code' => $prosedur->kode ?? '',
                'display' => $icd9Desc,
            ];
            $textDisplay = $icd9Desc;
        }

        if ($mapping && $mapping->system_code) {
            $codings[] = [
                'system' => $mapping->system_display ?? '',
                'code' => $mapping->system_code ?? '',
                'display' => $mapping->system_term ?? '',
            ];
            if (!$textDisplay) {
                $textDisplay = $mapping->system_term ?? '';
            }
        }

        if (empty($codings)) {
            $codings[] = [
                'system' => '',
                'code' => '',
                'display' => '',
            ];
        }

        return [
            'resourceType' => 'Procedure',
            'id' => $this->ctx->generateId($id),
            'text' => [
                'status' => 'generated',
                'div' => '<div xmlns="http://www.w3.org/1999/xhtml">Generated Narrative with Details</div>',
            ],
            'status' => 'completed',
            'code' => [
                'coding' => $codings,
                'text' => $textDisplay,
            ],
            'subject' => [
                'reference' => "Patient/{$this->ctx->patientData['id']}",
                'display' => $this->ctx->patientData['data']['nm_pasien'],
            ],
            'context' => [
                'reference' => "Encounter/{$this->ctx->encounterData['id']}",
                'display' => $this->ctx->patientData['data']['nm_pasien'] . ' encounter on  ' . $tglMasuk,
            ],
            'performedPeriod' => ['start' => $tglMasuk, 'end' => $tglKeluar],
            'performer' => [
                [
                    'role' => [
                        'coding' => [
                            [
                                'system' => $doctorSpecialty?->system_display ?? '',
                                'code' => $doctorSpecialty?->system_code ?? '',
                                'display' => $doctorSpecialty?->system_term ?? '',
                            ],
                        ],
                        'text' => $doctorSpecialty?->system_term ?? '',
                    ],
                    'actor' => [
                        'reference' => "Practitioner/{$this->ctx->idPractitioner}",
                        'display' => $reg->dokter?->nm_dokter ?? '',
                    ],
                ],
            ],
            'reasonCode' => [['text' => $mapping?->system_term ?? '']],
            'bodySite' => [['coding' => [['system' => '', 'code' => '', 'display' => '']]]],
            'focalDevice' => [
                [
                    'action' => ['coding' => [['system' => '', 'code' => '', 'display' => '']]],
                    'manipulated' => ['reference' => ''],
                ],
            ],
            'note' => [
                ['text' => $type == 'icd' ? 'Prosedur ICD-9' : $mapping->system_term]
            ],
        ];
    }
}
