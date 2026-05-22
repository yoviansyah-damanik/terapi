<?php

namespace App\Services\SatuSehat\Resources;

use App\Models\SatuSehat\SatuSehatCondition;
use App\Models\SatuSehat\SatuSehatLocation;
use App\Models\SatuSehat\SatuSehatPatient;
use App\Models\SatuSehat\SatuSehatPractitioner;
use App\Services\SatuSehat\DTO\FhirResponse;
use App\Services\SatuSehat\FhirDictionary;
use App\Services\SatuSehat\SatuSehatBaseService;

class EncounterService extends SatuSehatBaseService
{
    protected function getResourceType(): string
    {
        return 'Encounter';
    }

    public function searchBySubject(string $patientId): FhirResponse
    {
        return $this->search([
            'subject' => $patientId,
        ]);
    }

    public function searchByPatient(string $patientId): FhirResponse
    {
        return $this->searchBySubject($patientId);
    }

    public function createEncounter(
        SatuSehatPatient $patient,
        SatuSehatPractitioner $practitioner,
        SatuSehatLocation $location,
        ?SatuSehatCondition $condition = null,
        string $status = 'finished',
        string $class = 'AMB',
        string $careNumber,
        string $periodStart,
        string $periodEnd,
        ?string $serviceProviderId = null,
    ): FhirResponse {
        $payload = [
            'status' => $status,
            'identifier' => [
                [
                    'system' => FhirDictionary::KEMKES_SYS_ENCOUNTER . '/' . ($serviceProviderId ?? $this->getOrganizationId()),
                    'value' => $careNumber
                ]
            ],
            'class' => [
                'system' => FhirDictionary::HL7_CS_V3_ACT_CODE,
                'code' => $class,
                'display' => $this->getClassDisplay($class),
            ],
            'subject' => [
                'reference' => "Patient/{$patient->ihs_number}",
                'display' => $patient->name
            ],
            'serviceProvider' => [
                'reference' => 'Organization/' . ($serviceProviderId ?? $this->getOrganizationId()),
            ],
        ];

        $payload['location'] = [
            [
                'location' => [
                    'reference' => "Location/{$location->ihs_number}",
                ],
            ],
        ];

        $payload['participant'] = [
            [
                'type' => [
                    [
                        'coding' => [
                            [
                                'system' => FhirDictionary::HL7_CS_V3_PARTICIPATION,
                                'code' => 'ATND',
                                'display' => 'attender',
                            ],
                        ],
                    ],
                ],
                'individual' => [
                    'reference' => "Practitioner/{$practitioner->ihs_number}",
                    'display' => $practitioner->name
                ],
            ],
        ];

        $payload['statusHistory'] = [
            // tgl_registrasi dan jam dari reg_periksa
            [
                'status' => 'arrived',
                'period' => [
                    'start' => $periodStart,
                    'end' => $periodEnd
                ]
            ],
            // sedang diproses
            // ralan diambil dari mutasi_berkas diterima
            // ranap diambil dari tgl_masuk dan jam_keluar kamar_inap
            // [
            //     'status' => 'in-progress',
            //     'period' => [
            //         'start' => $periodStart,
            //         'end' => $periodEnd
            //     ]
            // ],
            // selesai
            // ralan diambil dari mutasi_berkas kembali
            // ranap diambil dari tgl_keluar dan jam_keluar kamar_inap
            // [
            //     'status' => 'finished',
            //     'period' => [
            //         'start' => $periodStart,
            //         'end' => $periodEnd
            //     ]
            // ]
        ];

        $payload['period'] = [
            'start' => $periodStart
        ];

        if ($status == 'finished') {
            $payload['diagnosis'] = [
                [
                    'condition' => [
                        'reference' => 'Condition/' . $condition->ihs_number,
                        'display' => $condition->icd_display,
                    ],
                    'use' => [
                        'coding' => [
                            [
                                "system" => FhirDictionary::HL7_CS_DIAGNOSIS_ROLE,
                                "code" => "DD",
                                "display" => "Discharge diagnosis"
                            ]
                        ]
                    ]
                ],
                'rank' => 1
            ];
        }

        return $this->create($payload);
    }

    public function updateStatus(string $id, string $status): FhirResponse
    {
        return $this->patch($id, [
            [
                'op' => 'replace',
                'path' => '/status',
                'value' => $status,
            ],
        ]);
    }

    public function finishEncounter(string $id, string $periodEnd): FhirResponse
    {
        return $this->patch($id, [
            [
                'op' => 'replace',
                'path' => '/status',
                'value' => 'finished',
            ],
            [
                'op' => 'add',
                'path' => '/period/end',
                'value' => $periodEnd,
            ],
        ]);
    }

    public function addDiagnosis(string $id, array $diagnosis): FhirResponse
    {
        return $this->patch($id, [
            [
                'op' => 'add',
                'path' => '/diagnosis/-',
                'value' => $diagnosis,
            ],
        ]);
    }

    protected function getClassDisplay(string $code): string
    {
        $displays = [
            'AMB' => 'ambulatory',
            'IMP' => 'inpatient encounter',
            'EMER' => 'emergency',
            'OBSENC' => 'observation encounter',
            'PRENC' => 'pre-admission',
            'VR' => 'virtual',
            'HH' => 'home health',
            'SS' => 'short stay',
        ];

        return $displays[$code] ?? $code;
    }
}
