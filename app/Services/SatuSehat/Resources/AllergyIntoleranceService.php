<?php

namespace App\Services\SatuSehat\Resources;

use App\Services\SatuSehat\DTO\FhirResponse;
use App\Services\SatuSehat\FhirDictionary;
use App\Services\SatuSehat\SatuSehatBaseService;

class AllergyIntoleranceService extends SatuSehatBaseService
{
    protected function getResourceType(): string
    {
        return 'AllergyIntolerance';
    }

    public function searchByPatient(string $patientId): FhirResponse
    {
        return $this->search([
            'patient' => $patientId,
        ]);
    }

    public function searchByCode(string $code): FhirResponse
    {
        return $this->search([
            'code' => $code,
        ]);
    }

    public function createAllergyIntolerance(
        string $patientId,
        string $code,
        string $display,
        string $clinicalStatus = 'active',
        string $verificationStatus = 'confirmed',
        string $type = 'allergy',
        string $category = 'medication',
        string $criticality = 'low',
        ?string $encounterId = null,
        ?string $recorderId = null,
        ?string $onsetDateTime = null,
        ?array $reaction = null,
        ?string $note = null,
    ): FhirResponse {
        $payload = [
            'clinicalStatus' => [
                'coding' => [
                    [
                        'system' => FhirDictionary::HL7_CS_ALLERGY_CLINICAL,
                        'code' => $clinicalStatus,
                        'display' => ucfirst($clinicalStatus),
                    ],
                ],
            ],
            'verificationStatus' => [
                'coding' => [
                    [
                        'system' => FhirDictionary::HL7_CS_ALLERGY_VERIFY,
                        'code' => $verificationStatus,
                        'display' => ucfirst($verificationStatus),
                    ],
                ],
            ],
            'type' => $type,
            'category' => [$category],
            'criticality' => $criticality,
            'code' => [
                'coding' => [
                    [
                                'system' => FhirDictionary::SNOMED,
                        'code' => $code,
                        'display' => $display,
                    ],
                ],
            ],
            'patient' => [
                'reference' => "Patient/{$patientId}",
            ],
        ];

        if ($encounterId) {
            $payload['encounter'] = [
                'reference' => "Encounter/{$encounterId}",
            ];
        }

        if ($recorderId) {
            $payload['recorder'] = [
                'reference' => "Practitioner/{$recorderId}",
            ];
        }

        if ($onsetDateTime) {
            $payload['onsetDateTime'] = $onsetDateTime;
        }

        if ($reaction) {
            $payload['reaction'] = $reaction;
        }

        if ($note) {
            $payload['note'] = [
                ['text' => $note],
            ];
        }

        return $this->create($payload);
    }

    public function createMedicationAllergy(
        string $patientId,
        string $code,
        string $display,
        string $criticality = 'low',
        ?string $encounterId = null,
        ?string $recorderId = null,
        ?array $manifestations = null,
    ): FhirResponse {
        $reaction = null;
        if ($manifestations) {
            $reaction = [
                [
                    'manifestation' => array_map(fn ($m) => [
                        'coding' => [
                            [
                                        'system' => FhirDictionary::SNOMED,
                                'code' => $m['code'],
                                'display' => $m['display'],
                            ],
                        ],
                    ], $manifestations),
                ],
            ];
        }

        return $this->createAllergyIntolerance(
            patientId: $patientId,
            code: $code,
            display: $display,
            category: 'medication',
            criticality: $criticality,
            encounterId: $encounterId,
            recorderId: $recorderId,
            reaction: $reaction,
        );
    }

    public function createFoodAllergy(
        string $patientId,
        string $code,
        string $display,
        string $criticality = 'low',
        ?string $encounterId = null,
        ?string $recorderId = null,
    ): FhirResponse {
        return $this->createAllergyIntolerance(
            patientId: $patientId,
            code: $code,
            display: $display,
            category: 'food',
            criticality: $criticality,
            encounterId: $encounterId,
            recorderId: $recorderId,
        );
    }

    public function resolveAllergy(string $id): FhirResponse
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
}
