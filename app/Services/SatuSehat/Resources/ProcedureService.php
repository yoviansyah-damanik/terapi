<?php

namespace App\Services\SatuSehat\Resources;

use App\Services\SatuSehat\DTO\FhirResponse;
use App\Services\SatuSehat\FhirDictionary;
use App\Services\SatuSehat\SatuSehatBaseService;

class ProcedureService extends SatuSehatBaseService
{
    protected function getResourceType(): string
    {
        return 'Procedure';
    }

    public function searchBySubject(string $patientId): FhirResponse
    {
        return $this->search([
            'subject' => $patientId,
        ]);
    }

    public function searchByEncounter(string $encounterId): FhirResponse
    {
        return $this->search([
            'encounter' => $encounterId,
        ]);
    }

    public function searchByCode(string $code): FhirResponse
    {
        return $this->search([
            'code' => $code,
        ]);
    }

    public function createProcedure(
        string $patientId,
        string $encounterId,
        string $performerId,
        string $codeSystem,
        string $codeTerm,
        string $codeDisplay,
        string $categoryCode,
        string $categoryDisplay,
        string $categorySystem,
        string $status = 'completed',
        ?string $performedDateTime = null,
        ?string $performedPeriodStart = null,
        ?string $performedPeriodEnd = null,
        ?string $reasonCode = null,
        ?string $bodySite = null,
        ?string $outcome = null,
        ?string $note = null,
    ): FhirResponse {
        $payload = [
            'status' => $status,
            'code' => [
                'coding' => [
                    [
                        'system' => $codeSystem ?? FhirDictionary::HL7_ICD9CM,
                        'code' => $codeTerm,
                        'display' => $codeDisplay,
                    ],
                ],
                'text' => $codeDisplay,
            ],
            'subject' => [
                'reference' => "Patient/{$patientId}",
            ],
            'encounter' => [
                'reference' => "Encounter/{$encounterId}",
            ],
            'performer' => [
                [
                    'actor' => [
                        'reference' => "Practitioner/{$performerId}",
                    ],
                ],
            ],
        ];

        if ($categoryCode || $categoryDisplay) {
            $payload['category'] = [
                'coding' => [
                    [
                        'system' => $categorySystem ?? FhirDictionary::SNOMED,
                        'code' => $categoryCode,
                        'display' => $categoryDisplay ?? '',
                    ],
                ],
                'text' => $categoryDisplay ?? '',
            ];
        }

        if ($performedDateTime) {
            $payload['performedDateTime'] = $performedDateTime;
        } elseif ($performedPeriodStart) {
            $payload['performedPeriod'] = ['start' => $performedPeriodStart];
            if ($performedPeriodEnd) {
                $payload['performedPeriod']['end'] = $performedPeriodEnd;
            }
        }

        if ($reasonCode) {
            $payload['reasonCode'] = [
                [
                    'coding' => [
                        [
                            'system' => FhirDictionary::HL7_ICD10,
                            'code' => $reasonCode,
                        ],
                    ],
                ],
            ];
        }

        if ($bodySite) {
            $payload['bodySite'] = [
                [
                    'coding' => [
                        [
                            'system' => FhirDictionary::SNOMED,
                            'code' => $bodySite,
                        ],
                    ],
                ],
            ];
        }

        if ($outcome) {
            $payload['outcome'] = [
                'coding' => [
                    [
                        'system' => FhirDictionary::SNOMED,
                        'code' => $outcome,
                    ],
                ],
            ];
        }

        if ($note) {
            $payload['note'] = [
                ['text' => $note],
            ];
        }

        return $this->create($payload);
    }

    public function createSurgicalProcedure(
        string $patientId,
        string $encounterId,
        string $performerId,
        string $icd9Code,
        string $icd9Display,
        string $performedDateTime,
        ?string $reasonCode = null,
        ?string $outcome = null,
    ): FhirResponse {
        return $this->createProcedure(
            patientId: $patientId,
            encounterId: $encounterId,
            performerId: $performerId,
            codeSystem: FhirDictionary::HL7_ICD9CM,
            codeTerm: $icd9Code,
            codeDisplay: $icd9Display,
            status: 'completed',
            categoryCode: '387713003', // Surgical procedure
            categoryDisplay: 'Surgical procedure',
            categorySystem: FhirDictionary::SNOMED,
            performedDateTime: $performedDateTime,
            reasonCode: $reasonCode,
            outcome: $outcome,
        );
    }

    public function createGenericProcedure(
        string $patientId,
        string $encounterId,
        array $performers,
        string $system,
        string $code,
        string $display,
        string $status = 'completed',
        ?string $categoryCode = null,
        ?string $categoryDisplay = null,
        ?string $categorySystem = null,
        ?string $performedDateTime = null,
        ?string $note = null,
    ): FhirResponse {
        $payload = [
            'resourceType' => 'Procedure',
            'status' => $status,
            'category' => [
                'coding' => [
                    [
                        'system' => $categorySystem ?? FhirDictionary::SNOMED,
                        'code' => $categoryCode ?? '387713003', // Default: Surgical procedure
                        'display' => $categoryDisplay ?? 'Surgical procedure',
                    ],
                ],
            ],
            'code' => [
                'coding' => [
                    [
                        'system' => $system,
                        'code' => $code,
                        'display' => $display,
                    ],
                ],
            ],
            'subject' => [
                'reference' => "Patient/{$patientId}",
            ],
            'encounter' => [
                'reference' => "Encounter/{$encounterId}",
            ],
            'performer' => $performers,
        ];

        if ($performedDateTime) {
            $payload['performedDateTime'] = $performedDateTime;
        }

        if ($note) {
            $payload['note'] = [['text' => $note]];
        }

        return $this->create($payload);
    }
}
