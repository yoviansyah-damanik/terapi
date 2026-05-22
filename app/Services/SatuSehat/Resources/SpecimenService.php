<?php

namespace App\Services\SatuSehat\Resources;

use App\Services\SatuSehat\DTO\FhirResponse;
use App\Services\SatuSehat\FhirDictionary;
use App\Services\SatuSehat\SatuSehatBaseService;

class SpecimenService extends SatuSehatBaseService
{
    protected function getResourceType(): string
    {
        return 'Specimen';
    }

    public function searchBySubject(string $patientId): FhirResponse
    {
        return $this->search([
            'subject' => $patientId,
        ]);
    }

    public function searchByIdentifier(string $identifier): FhirResponse
    {
        return $this->search([
            'identifier' => FhirDictionary::specimenSystem($this->getOrganizationId()) . "|{$identifier}",
        ]);
    }

    public function searchByType(string $type): FhirResponse
    {
        return $this->search([
            'type' => $type,
        ]);
    }

    public function createSpecimen(
        string $patientId,
        string $typeCode,
        string $typeDisplay,
        string $status = 'available',
        ?string $identifier = null,
        ?string $collectedDateTime = null,
        ?string $collectorId = null,
        ?string $receivedTime = null,
        ?array $container = null,
        ?string $note = null,
        ?string $serviceRequestId = null,
    ): FhirResponse {
        $payload = [
            'status' => $status,
            'type' => [
                'coding' => [
                    [
                        'system' => FhirDictionary::SNOMED,
                        'code' => $typeCode,
                        'display' => $typeDisplay,
                    ],
                ],
            ],
            'subject' => [
                'reference' => "Patient/{$patientId}",
            ],
        ];

        if ($identifier) {
            $payload['identifier'] = [
                [
                    'system' => FhirDictionary::specimenSystem($this->getOrganizationId()),
                    'use' => 'official',
                    'value' => $identifier,
                ],
            ];
        }

        $collection = [];
        if ($collectedDateTime) {
            $collection['collectedDateTime'] = $collectedDateTime;
        }
        if ($collectorId) {
            $collection['collector'] = [
                'reference' => "Practitioner/{$collectorId}",
            ];
        }
        if (! empty($collection)) {
            $payload['collection'] = $collection;
        }

        if ($receivedTime) {
            $payload['receivedTime'] = $receivedTime;
        }

        if ($container) {
            $payload['container'] = $container;
        }

        if ($note) {
            $payload['note'] = [
                ['text' => $note],
            ];
        }

        if ($serviceRequestId) {
            $payload['request'] = [
                ['reference' => "ServiceRequest/{$serviceRequestId}"],
            ];
        }

        return $this->create($payload);
    }

    public function createBloodSpecimen(
        string $patientId,
        ?string $identifier = null,
        ?string $collectorId = null,
        ?string $serviceRequestId = null,
    ): FhirResponse {
        $now = now()->toIso8601String();

        return $this->createSpecimen(
            patientId: $patientId,
            typeCode: '119297000',
            typeDisplay: 'Blood specimen',
            identifier: $identifier,
            collectedDateTime: $now,
            collectorId: $collectorId,
            receivedTime: $now,
            serviceRequestId: $serviceRequestId,
        );
    }

    public function createUrineSpecimen(
        string $patientId,
        ?string $identifier = null,
        ?string $collectorId = null,
        ?string $serviceRequestId = null,
    ): FhirResponse {
        $now = now()->toIso8601String();

        return $this->createSpecimen(
            patientId: $patientId,
            typeCode: '122575003',
            typeDisplay: 'Urine specimen',
            identifier: $identifier,
            collectedDateTime: $now,
            collectorId: $collectorId,
            receivedTime: $now,
            serviceRequestId: $serviceRequestId,
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
