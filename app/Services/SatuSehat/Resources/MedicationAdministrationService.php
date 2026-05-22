<?php

namespace App\Services\SatuSehat\Resources;

use App\Services\SatuSehat\DTO\FhirResponse;
use App\Services\SatuSehat\FhirDictionary;
use App\Services\SatuSehat\SatuSehatBaseService;

class MedicationAdministrationService extends SatuSehatBaseService
{
    protected function getResourceType(): string
    {
        return 'MedicationAdministration';
    }

    public function searchBySubject(string $patientId): FhirResponse
    {
        return $this->search(['subject' => $patientId]);
    }

    public function searchByContext(string $encounterId): FhirResponse
    {
        return $this->search(['context' => $encounterId]);
    }

    public function searchByRequest(string $requestId): FhirResponse
    {
        return $this->search(['request' => $requestId]);
    }

    /**
     * Kirim MedicationAdministration ke SatuSehat.
     *
     * @param array|null $medicationContained  Array resource Medication yang di-contain (null = gunakan medicationIhs)
     * @param string|null $medicationIhs       IHS Medication jika tidak contained
     * @param array|null  $dosage              ['route_code', 'route_display', 'dose_value', 'dose_unit']
     */
    public function createMedicationAdministration(
        string $patientId,
        string $encounterId,
        string $performerId,
        string $medicationRequestId,
        string $effectiveStart,
        string $effectiveEnd,
        string $medicationIhs,
        string $status = 'completed',
        string $category = 'outpatient',
        string $categoryDisplay = 'Outpatient',
        string $identifier,
        array $medicationContained,
        array $dosage,
    ): FhirResponse {
        $payload = [
            'status' => $status,
            'category' => [
                'coding' => [
                    [
                        'system' => 'http://terminology.hl7.org/CodeSystem/medication-admin-category',
                        'code' => $category,
                        'display' => $categoryDisplay,
                    ]
                ],
            ],
            'subject' => ['reference' => "Patient/{$patientId}"],
            'context' => ['reference' => "Encounter/{$encounterId}"],
            'effectivePeriod' => [
                'start' => $effectiveStart,
                'end' => $effectiveEnd,
            ],
            'performer' => [
                [
                    'actor' => ['reference' => "Practitioner/{$performerId}"],
                ]
            ],
            'reasonCode' => [
                [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/reason-medication-given',
                            'code' => 'b',
                            'display' => 'Given as Ordered',
                        ]
                    ],
                ]
            ],
            'request' => ['reference' => "MedicationRequest/{$medicationRequestId}"],
        ];

        // medicationReference selalu menunjuk ke resource Medication eksternal
        $payload['medicationReference'] = ['reference' => "Medication/{$medicationIhs}"];

        // Contained Medication — data inline dengan identifier wajib
        if ($medicationContained) {
            $medicationContained['id'] ??= $medicationIhs;
            $medicationContained['identifier'] = [
                [
                    'system' => "http://sys-ids.kemkes.go.id/medication/{$this->getOrganizationId()}",
                    'use' => 'official',
                    'value' => $medicationIhs,
                ]
            ];
            $payload['contained'] = [$medicationContained];
        }

        if ($identifier) {
            $payload['identifier'] = [
                [
                    'system' => FhirDictionary::prescriptionItemSystem($this->getOrganizationId()),
                    'use' => 'official',
                    'value' => $identifier,
                ]
            ];
        }

        $dosagePayload = [];

        if (!empty($dosage['route_code'])) {
            $dosagePayload['route'] = [
                'coding' => [
                    [
                        'system' => 'http://www.whocc.no/atc',
                        'code' => $dosage['route_code'],
                        'display' => $dosage['route_display'] ?? $dosage['route_code'],
                    ]
                ],
            ];
        }

        if (isset($dosage['dose_value'])) {
            $dosagePayload['dose'] = [
                'value' => $dosage['dose_value'],
                'unit' => $dosage['dose_term'],
                'system' => $dosage['dose_display'],
                'code' => $dosage['dose_unit'],
            ];
        }

        if ($dosagePayload) {
            $payload['dosage'] = $dosagePayload;
        }

        return $this->create($payload);
    }
}
