<?php

namespace App\Services\SatuSehat\Resources;

use App\Services\SatuSehat\DTO\FhirResponse;
use App\Services\SatuSehat\FhirDictionary;
use App\Services\SatuSehat\SatuSehatBaseService;

class MedicationStatementService extends SatuSehatBaseService
{
    protected function getResourceType(): string
    {
        return 'MedicationStatement';
    }

    public function searchBySubject(string $patientId): FhirResponse
    {
        return $this->search([
            'subject' => $patientId,
        ]);
    }

    public function createMedicationStatement(
        string $patientId,
        string $encounterId,
        string $medicationId,
        string $medicationDisplay,
        string $categoryCode,
        string $categoryDisplay,
        string $dosageText,
        float $dosageFrequency,
        float $dosagePeriod,
        string $dosagePeriodUnit,
        string $effectiveDateTime,
        string $dateAsserted,
        string $status = 'completed',
        ?string $identifier = null
    ): FhirResponse {
        $payload = [
            'status' => $status,
            'category' => [
                'coding' => [
                    [
                        'system' => FhirDictionary::HL7_CS_MED_STATEMENT_CAT,
                        'code' => $categoryCode,
                        'display' => $categoryDisplay,
                    ]
                ]
            ],
            'medicationReference' => [
                'reference' => "Medication/{$medicationId}",
                'display' => $medicationDisplay,
            ],
            'subject' => [
                'reference' => "Patient/{$patientId}",
            ],
            'context' => [
                'reference' => "Encounter/{$encounterId}",
            ],
            'effectiveDateTime' => $effectiveDateTime,
            'dateAsserted' => $dateAsserted,
            'informationSource' => [
                'reference' => "Patient/{$patientId}",
            ],
            'dosage' => [
                [
                    'text' => $dosageText,
                    'timing' => [
                        'repeat' => [
                            'frequency' => $dosageFrequency,
                            'period' => $dosagePeriod,
                            'periodMax' => $dosagePeriod,
                            'periodUnit' => $dosagePeriodUnit,
                        ]
                    ]
                ]
            ]
        ];

        if ($identifier) {
            $payload['identifier'] = [
                [
                    'system' => FhirDictionary::medicationStatementSystem($this->getOrganizationId()),
                    'use' => 'official',
                    'value' => $identifier,
                ],
            ];
        }

        return $this->create($payload);
    }
}
