<?php

namespace App\Services\Bpjs\Erm\Resources;

use App\Constants\BpjsErmCodes;
use App\Helpers\ErmHelper;
use App\Models\Bpjs\BpjsMedication;
use App\Models\Bpjs\BpjsOrganization;
use App\Models\Mapping\MedicationMap;
use App\Models\Mapping\OrganizationMap;

class MedicationRequestBuilder extends BaseResourceBuilder
{
    /** @return array Bundle entries including pharmacy org + all MedicationRequest resources */
    public function build(): array
    {
        $reg = $this->ctx->reg;

        if (!($reg->resepPulang?->isNotEmpty() || $reg->detailPemberianObat?->isNotEmpty())) {
            return [];
        }

        $pharmacyOrgData = BpjsOrganization::where('identifier', $this->ctx->pharmacyCode)->firstOrFail();
        $this->ctx->pharmacyOrganization = [
            'id' => $this->ctx->generateId($pharmacyOrgData->id),
            'display' => $pharmacyOrgData->name,
            'code' => $this->ctx->pharmacyCode,
            'map' => OrganizationMap::select('org_type_code', 'org_type_term', 'org_type_display')
                ->where('dep_id', $this->ctx->pharmacyCode)->first()->toArray(),
        ];

        $entries = [
            (new OrganizationBuilder($this->ctx))->buildForOrganization($this->ctx->pharmacyOrganization),
        ];

        if ($reg->resepPulang?->isNotEmpty()) {
            foreach ($reg->resepPulang as $resep) {
                if ($resep->dataBarang) {
                    $id = $this->ctx->generateId(
                        BpjsMedication::where('local_code', $resep->dataBarang->kode_brng)->first()->id
                    );
                    $entries[] = [
                        'resource' => [
                            $this->buildMedicationRequestResource($id, [
                                'kode_brng' => $resep->kode_brng,
                                'nama_brng' => $resep->dataBarang->nama_brng ?? '',
                                'jml' => $resep->jml_barang,
                                'satuan' => $resep->dataBarang->kode_sat ?? 'TAB',
                                'aturan_pakai' => $resep->dosis ?? '',
                                'waktu_pemberian' => $resep->tanggal->format('Y-m-d') . ' ' . $resep->jam,
                            ], 'pulang'),
                        ],
                    ];
                }
            }
        }

        if ($reg->detailPemberianObat?->isNotEmpty()) {
            foreach ($reg->detailPemberianObat as $detail) {
                if ($detail->dataBarang) {
                    $id = $this->ctx->generateId(
                        BpjsMedication::where('local_code', $detail->dataBarang->kode_brng)->first()->id
                    );
                    $entries[] = [
                        'resource' => [
                            $this->buildMedicationRequestResource($id, [
                                'kode_brng' => $detail->kode_brng,
                                'nama_brng' => $detail->dataBarang->nama_brng ?? '',
                                'jml' => $detail->jml,
                                'satuan' => $detail->dataBarang->kode_sat ?? 'TAB',
                                'aturan_pakai' => $detail->aturanPakai->aturan ?? '',
                                'waktu_pemberian' => $detail->tgl_perawatan->format('Y-m-d') . ' ' . $detail->jam,
                            ], 'rawatan'),
                        ],
                    ];
                }
            }
        }

        return $entries;
    }

    private function buildMedicationRequestResource(string $id, array $obat, string $type): array
    {
        $drugMap = MedicationMap::where('local_code', $obat['kode_brng'] ?? '')->first();

        return [
            'resourceType' => 'MedicationRequest',
            'text' => [
                'div' => '<div xmlns="http://www.w3.org/1999/xhtml">' . $obat['nama_brng'] . '</div>',
                'status' => 'completed',
            ],
            'identifier' => [
                'use' => 'usual',
                'type' => [
                    'coding' => [
                        [
                            'system' => BpjsErmCodes::SYSTEM_PRESCRIPTION_CATEGORY,
                            'code' => $type == 'pulang' ? 'PRESCRIPTION-DISCHARGE' : 'PRESCRIPTION-IPD',
                            'display' => $type == 'pulang' ? 'Resep Pulang' : 'Resep Rawatan',
                        ],
                    ],
                    'text' => $type == 'pulang' ? 'Resep Pulang' : 'Resep Rawatan',
                ],
                'system' => BpjsErmCodes::SYSTEM_PRESCRIPTION_CATEGORY,
                'value' => $id,
                'assigner' => ['display' => config('hospital.name', '-')],
            ],
            'subject' => [
                'reference' => "Patient/{$this->ctx->patientData['id']}",
                'display' => $this->ctx->patientData['data']['nm_pasien'],
            ],
            'meta' => ['lastUpdated' => ErmHelper::formatDateTime($obat['waktu_pemberian'])],
            'intent' => 'order',
            'medicationCodeableConcept' => [
                'coding' => [
                    [
                        'system' => config('app.url') . '/drug',
                        'code' => $obat['kode_brng'] ?? '',
                        'display' => $obat['nama_brng'] ?? '',
                    ],
                ],
                'text' => $obat['nama_brng'] ?? '',
            ],
            'requester' => [
                'agent' => [
                    'display' => $this->ctx->reg->dokter?->nm_dokter ?? '',
                    'reference' => "Practitioner/{$this->ctx->idPractitioner}",
                ],
                'onBehalfOf' => [
                    'reference' => "Organization/{$this->ctx->pharmacyOrganization['id']}",
                ],
            ],
            'dosageInstruction' => [
                [
                    'doseQuantity' => [
                        'value' => $obat['jml'] ?? 1,
                        'unit' => $obat['satuan'] ?? 'TAB',
                        'system' => BpjsErmCodes::SYSTEM_UCUM,
                        'code' => $obat['satuan'] ?? 'TAB',
                    ],
                    'route' => [
                        'coding' => [
                            [
                                'system' => $drugMap?->system_url ?? '',
                                'code' => $drugMap?->kfa_code ?? ($obat['kode_brng'] ?? ''),
                                'display' => $drugMap?->kfa_name ?? ($obat['nama_brng'] ?? ''),
                            ],
                        ],
                        'text' => $drugMap?->kfa_name ?? ($obat['nama_brng'] ?? ''),
                    ],
                    'additionalInstruction' => [['text' => $obat['aturan_pakai'] ?? '']],
                    'timing' => ['repeat' => ['frequency' => 1, 'period' => 1, 'periodUnit' => 'd']],
                ],
            ],
        ];
    }
}
