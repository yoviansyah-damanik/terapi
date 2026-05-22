<?php

namespace App\Services\SatuSehat\Resources;

use App\Services\SatuSehat\DTO\FhirResponse;
use App\Services\SatuSehat\FhirDictionary;
use App\Services\SatuSehat\SatuSehatBaseService;

class ConditionService extends SatuSehatBaseService
{
    protected function getResourceType(): string
    {
        return 'Condition';
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

    public function createCondition(
        string $patientId,
        string $encounterId,
        string $icdCode,
        string $icdDisplay,
        string $clinicalStatus = 'active',
        string $category = 'encounter-diagnosis',
        ?string $onsetDateTime = null,
        ?string $abatementDateTime = null,
        ?string $recorderId = null,
        ?string $note = null,
    ): FhirResponse {
        $payload = [
            'clinicalStatus' => [
                'coding' => [
                    [
                        'system' => FhirDictionary::HL7_CS_CONDITION_CLINICAL,
                        'code' => $clinicalStatus,
                        'display' => ucfirst($clinicalStatus),
                    ],
                ],
            ],
            'category' => [
                [
                    'coding' => [
                        [
                            'system' => FhirDictionary::HL7_CS_CONDITION_CATEGORY,
                            'code' => $category,
                            'display' => $this->getCategoryDisplay($category),
                        ],
                    ],
                ],
            ],
            'code' => [
                'coding' => [
                    [
                        'system' => FhirDictionary::HL7_ICD10,
                        'code' => $icdCode,
                        'display' => $icdDisplay,
                    ],
                ],
            ],
            'subject' => [
                'reference' => "Patient/{$patientId}",
            ],
            'encounter' => [
                'reference' => "Encounter/{$encounterId}",
            ],
        ];

        if ($onsetDateTime) {
            $payload['onsetDateTime'] = $onsetDateTime;
        }

        if ($abatementDateTime) {
            $payload['abatementDateTime'] = $abatementDateTime;
        }

        if ($recorderId) {
            $payload['recorder'] = [
                'reference' => "Practitioner/{$recorderId}",
            ];
        }

        if ($note) {
            $payload['note'] = [
                ['text' => $note],
            ];
        }

        return $this->create($payload);
    }

    public function createDiagnosis(
        string $patientId,
        string $encounterId,
        string $icdCode,
        string $icdDisplay,
        ?string $practitionerId = null,
    ): FhirResponse {
        return $this->createCondition(
            patientId: $patientId,
            encounterId: $encounterId,
            icdCode: $icdCode,
            icdDisplay: $icdDisplay,
            clinicalStatus: 'active',
            category: 'encounter-diagnosis',
            recorderId: $practitionerId,
        );
    }

    public function resolveCondition(string $id): FhirResponse
    {
        return $this->patch($id, [
            [
                'op' => 'replace',
                'path' => '/clinicalStatus/coding/0/code',
                'value' => 'resolved',
            ],
            [
                'op' => 'replace',
                'path' => '/clinicalStatus/coding/0/display',
                'value' => 'Resolved',
            ],
        ]);
    }

    protected function getCategoryDisplay(string $code): string
    {
        $displays = [
            'problem-list-item' => 'Problem List Item',
            'encounter-diagnosis' => 'Encounter Diagnosis',
        ];

        return $displays[$code] ?? $code;
    }
}
