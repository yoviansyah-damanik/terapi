<?php

namespace App\Services\SatuSehat\Resources;

use App\Services\SatuSehat\DTO\FhirResponse;
use App\Services\SatuSehat\FhirDictionary;
use App\Services\SatuSehat\SatuSehatBaseService;

class MedicationRequestService extends SatuSehatBaseService
{
    protected function getResourceType(): string
    {
        return 'MedicationRequest';
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

    public function createMedicationRequest(
        string $patientId,
        string $encounterId,
        string $requesterId,
        string $medicationReference,
        string $status = 'active',
        string $intent = 'order',
        ?string $identifier = null,
        ?array $dosageInstruction = null,
        ?array $dispenseRequest = null,
        ?string $authoredOn = null,
        ?string $reasonCode = null,
        ?string $note = null,
    ): FhirResponse {
        $payload = [
            'status' => $status,
            'intent' => $intent,
            'medicationReference' => [
                'reference' => $medicationReference,
            ],
            'subject' => [
                'reference' => "Patient/{$patientId}",
            ],
            'encounter' => [
                'reference' => "Encounter/{$encounterId}",
            ],
            'requester' => [
                'reference' => "Practitioner/{$requesterId}",
            ],
        ];

        if ($identifier) {
            $payload['identifier'] = [
                [
                    'system' => FhirDictionary::prescriptionSystem($this->getOrganizationId()),
                    'use' => 'official',
                    'value' => $identifier,
                ],
            ];
        }

        if ($authoredOn) {
            $payload['authoredOn'] = $authoredOn;
        }

        if ($dosageInstruction) {
            $payload['dosageInstruction'] = $dosageInstruction;
        } else {
            $payload['dosageInstruction'] = [
                [
                    'sequence' => 1,
                    'text' => 'Sesuai petunjuk dokter',
                ],
            ];
        }

        if ($dispenseRequest) {
            $payload['dispenseRequest'] = $dispenseRequest;
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

        if ($note) {
            $payload['note'] = [
                ['text' => $note],
            ];
        }

        return $this->create($payload);
    }

    public function createPrescription(
        string $patientId,
        string $encounterId,
        string $requesterId,
        string $medicationId,
        int $quantity,
        string $unitCode,
        string $unitTerm,
        array $dosage,
        ?string $identifier = null,
        ?int $numberOfRepeatsAllowed = null,
    ): FhirResponse {
        $dispenseRequest = [
            'dispenseInterval' => [
                'value' => 1,
                'unit' => 'days',
                'system' => FhirDictionary::UCUM,
                'code' => 'd',
            ],
            'quantity' => [
                'value' => $quantity,
                'unit' => $unitTerm,
                'system' => FhirDictionary::HL7_DRUG_FORM,
                'code' => $unitCode,
            ],
        ];

        if ($numberOfRepeatsAllowed !== null) {
            $dispenseRequest['numberOfRepeatsAllowed'] = $numberOfRepeatsAllowed;
        }

        return $this->createMedicationRequest(
            patientId: $patientId,
            encounterId: $encounterId,
            requesterId: $requesterId,
            medicationReference: "Medication/{$medicationId}",
            identifier: $identifier,
            dosageInstruction: [$dosage],
            dispenseRequest: $dispenseRequest,
            authoredOn: now()->toIso8601String(),
        );
    }

    public function cancelPrescription(string $id): FhirResponse
    {
        return $this->patch($id, [
            [
                'op' => 'replace',
                'path' => '/status',
                'value' => 'cancelled',
            ],
        ]);
    }
}
