<?php

namespace App\Services\Bpjs\Erm\Resources;

use App\Constants\BpjsErmCodes;
use App\Helpers\ErmHelper;

class EncounterBuilder extends BaseResourceBuilder
{
    public function build(): array
    {
        $reg = $this->ctx->reg;
        $nmPoli = $reg->poliklinik->nm_poli ?? '-';
        $nmRs = config('hospital.name');
        $tglPeriksa = $reg->tgl_registrasi->format('Y-m-d') . ' ' . $reg->jam_reg;

        $tglMasuk = ErmHelper::formatDateTime($tglPeriksa);
        $tglKeluar = ErmHelper::formatDateTime($reg->mutasiBerkas?->kembali ?? now());

        if ($reg->status_lanjut === 'Ranap' && $reg->kamarInap->isNotEmpty()) {
            $kamar = $reg->kamarInap->first();
            $nmBangsal = $kamar->bangsal?->nm_bangsal ?? '-';
            $tglMasuk = ErmHelper::formatDateTime($kamar->tgl_masuk->format('Y-m-d') . ' ' . $kamar->jam_masuk);
            $tglKeluar = ErmHelper::formatDateTime($kamar->tgl_keluar->format('Y-m-d') . ' ' . $kamar->jam_keluar);
            $divText = "Admitted to {$nmBangsal}, {$nmRs} at {$tglPeriksa} between {$tglMasuk} and {$tglKeluar}";
        } else {
            $divText = "Admitted to {$nmPoli}, {$nmRs} at {$tglPeriksa} between {$tglMasuk} and {$tglKeluar}";
        }

        $noRujukan = '-';
        if (!empty($this->ctx->sepData['noskdp'])) {
            $noRujukan = $this->ctx->sepData['noskdp'];
        } elseif (!empty($this->ctx->sepData['no_rujukan'])) {
            $noRujukan = $this->ctx->sepData['no_rujukan'];
        }

        $encounter = [
            'resourceType' => 'Encounter',
            'id' => $this->ctx->generateId(),
            'identifier' => [
                [
                    'use' => 'usual',
                    'type' => [
                        'coding' => BpjsErmCodes::CODING_ID_VISIT_NUMBER,
                        'text' => 'Nomor SEP',
                    ],
                    'system' => config('bpjs.vclaim.base_url', 'https://apijkn-dev.bpjs-kesehatan.go.id/'),
                    'value' => $this->ctx->sepData['no_sep'],
                    'assigner' => ['display' => 'BPJS Kesehatan'],
                ],
            ],
            'subject' => [
                'reference' => "Patient/{$this->ctx->patientData['id']}",
                'display' => $this->ctx->patientData['data']['nm_pasien'],
                'noSep' => $this->ctx->sepData['no_sep'],
            ],
            'class' => [
                'system' => BpjsErmCodes::SYSTEM_V3_ACT_CODE,
                'code' => $reg->status_lanjut === 'Ranap' ? 'IMP' : ($reg->kd_poli == 'IGDK' ? 'EMER' : 'AMB'),
                'display' => $reg->status_lanjut === 'Ranap' ? 'Inpatient Encounter' : ($reg->kd_poli == 'IGDK' ? 'Emergency' : 'Ambulatory'),
            ],
            'incomingReferral' => [
                [
                    'identifier' => [
                        [
                            'use' => 'usual',
                            'type' => ['coding' => BpjsErmCodes::CODING_ID_RESOURCE, 'text' => 'Nomor Rujukan BPJS'],
                            'system' => 'nomor_rujukan_bpjs',
                            'value' => $noRujukan,
                            'assigner' => ['display' => $this->ctx->sepData['nmppkrujukan']],
                        ],
                        [
                            'use' => 'usual',
                            'type' => ['coding' => BpjsErmCodes::CODING_ID_RESOURCE, 'text' => 'Nomor Rujukan Internal RS'],
                            'system' => 'nomor_rujukan_internal_rs',
                            'value' => '-',
                            'assigner' => ['display' => '-'],
                        ],
                    ],
                ],
            ],
            'reason' => [
                [
                    'coding' => [
                        [
                            'system' => 'http://hl7.org/fhir/sid/icd-10',
                            'code' => $this->ctx->sepData['diagawal'],
                            'display' => $this->ctx->sepData['nmdiagnosaawal'],
                        ],
                    ],
                    'text' => $this->ctx->sepData['nmdiagnosaawal'],
                ],
            ],
            'diagnosis' => $this->buildDiagnosis(),
            'hospitalization' => [
                'dischargeDisposition' => [ErmHelper::getDischargeDisposition($reg->stts)],
            ],
            'period' => ['start' => $tglMasuk, 'end' => $tglKeluar],
            'status' => 'finished',
            'text' => [
                'status' => 'generated',
                'div' => '<div xmlns="http://www.w3.org/1999/xhtml">' . $divText . '</div>',
            ],
        ];

        $this->ctx->encounterData = $encounter;

        return ['resource' => $encounter];
    }

    private function buildDiagnosis(): array
    {
        if (empty($this->ctx->conditions)) {
            return [];
        }

        $diagnoses = [];
        foreach ($this->ctx->conditions as $index => $condition) {
            $diagnoses[] = [
                'condition' => [
                    'reference' => "Condition/{$condition['resource']['id']}",
                    'role' => [
                        'coding' => [BpjsErmCodes::CODING_DIAGNOSIS_ROLE_DD],
                        "code" => "DD",
                        'text' => 'Discharge Diagnosis',
                    ],
                    'rank' => $index + 1,
                ],
            ];
        }

        return $diagnoses;
    }
}
