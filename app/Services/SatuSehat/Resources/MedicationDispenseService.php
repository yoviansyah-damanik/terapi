<?php

namespace App\Services\SatuSehat\Resources;

use App\Services\SatuSehat\DTO\FhirResponse;
use App\Services\SatuSehat\FhirDictionary;
use App\Services\SatuSehat\SatuSehatBaseService;

class MedicationDispenseService extends SatuSehatBaseService
{
    protected function getResourceType(): string
    {
        return 'MedicationDispense';
    }

    public function searchBySubject(string $patientId): FhirResponse
    {
        return $this->search([
            'subject' => $patientId,
        ]);
    }

    public function searchByPrescription(string $prescriptionId): FhirResponse
    {
        return $this->search([
            'prescription' => $prescriptionId,
        ]);
    }

    public function searchByContext(string $encounterId): FhirResponse
    {
        return $this->search([
            'context' => $encounterId,
        ]);
    }

    public function createMedicationDispense(
        string $patientId,
        string $encounterId,
        string $medicationRequestId,
        string $medicationReference,
        string $performerId,
        int $quantity,
        string $unitCode,
        string $unitTerm,
        string $status = 'completed',
        ?string $identifier = null,
        ?string $whenPrepared = null,
        ?string $whenHandedOver = null,
        ?array $dosageInstruction = null,
    ): FhirResponse {
        $payload = [
            'status' => $status,
            'medicationReference' => [
                'reference' => $medicationReference,
            ],
            'subject' => [
                'reference' => "Patient/{$patientId}",
            ],
            'context' => [
                'reference' => "Encounter/{$encounterId}",
            ],
            'authorizingPrescription' => [
                [
                    'reference' => "MedicationRequest/{$medicationRequestId}",
                ],
            ],
            'performer' => [
                [
                    'actor' => [
                        'reference' => "Practitioner/{$performerId}",
                    ],
                ],
            ],
            'location' => $this->buildPharmacyLocationReference(),
            'quantity' => [
                'value' => $quantity,
                'unit' => $unitTerm,
                'system' => FhirDictionary::HL7_DRUG_FORM,
                'code' => $unitCode,
            ],
        ];

        if ($identifier) {
            $payload['identifier'] = [
                [
                    'system' => FhirDictionary::prescriptionItemSystem($this->getOrganizationId()),
                    'use' => 'official',
                    'value' => $identifier,
                ],
            ];
        }

        if ($whenPrepared) {
            $payload['whenPrepared'] = $whenPrepared;
        }

        if ($whenHandedOver) {
            $payload['whenHandedOver'] = $whenHandedOver;
        }

        if ($dosageInstruction) {
            $payload['dosageInstruction'] = $dosageInstruction;
        }

        return $this->create($payload);
    }

    public function dispense(
        string $patientId,
        string $encounterId,
        string $medicationRequestId,
        string $medicationId,
        string $performerId,
        int $quantity,
        string $unitCode,
        string $unitTerm,
        ?string $identifier = null,
    ): FhirResponse {
        $now = now()->toIso8601String();

        return $this->createMedicationDispense(
            patientId: $patientId,
            encounterId: $encounterId,
            medicationRequestId: $medicationRequestId,
            medicationReference: "Medication/{$medicationId}",
            performerId: $performerId,
            quantity: $quantity,
            unitCode: $unitCode,
            unitTerm: $unitTerm,
            status: 'completed',
            identifier: $identifier,
            whenPrepared: $now,
            whenHandedOver: $now,
        );
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
}
