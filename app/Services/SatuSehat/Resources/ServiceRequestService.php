<?php

namespace App\Services\SatuSehat\Resources;

use App\Services\SatuSehat\DTO\FhirResponse;
use App\Services\SatuSehat\FhirDictionary;
use App\Services\SatuSehat\SatuSehatBaseService;

class ServiceRequestService extends SatuSehatBaseService
{
    protected function getResourceType(): string
    {
        return 'ServiceRequest';
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

    /** Resolve identifier system berdasarkan tipe: radiologi pakai acsn, lab pakai servicerequest */
    private function identifierSystem(string $type): string
    {
        $orgId = $this->getOrganizationId();

        return $type === 'radiology'
            ? FhirDictionary::acsnSystem($orgId)
            : FhirDictionary::serviceRequestSystem($orgId);
    }

    public function createServiceRequest(
        string $patientId,
        string $encounterId,
        string $requesterId,
        string $code,
        string $codeSystem,
        string $display,
        string $identifier,
        string $status = 'active',
        string $intent = 'order',
        string $priority = 'routine',
        string $type = 'lab',
        ?string $codeText = null,
        ?string $category = null,
        ?string $categoryDisplay = null,
        ?string $encounterDisplay = null,
        ?string $occurrenceDateTime = null,
        ?string $authoredOn = null,
        ?string $requesterDisplay = null,
        ?string $performerOrgId = null,
        ?string $performerDisplay = null,
        ?string $reasonText = null,
        ?string $note = null,
    ): FhirResponse {

        $codeBlock = [
            'coding' => [
                [
                    'system' => $codeSystem,
                    'code' => $code,
                    'display' => $display,
                ],
            ],
        ];

        if ($codeText) {
            $codeBlock['text'] = $codeText;
        }

        $encounterBlock = ['reference' => "Encounter/{$encounterId}"];
        if ($encounterDisplay) {
            $encounterBlock['display'] = $encounterDisplay;
        }

        $requesterBlock = ['reference' => "Practitioner/{$requesterId}"];
        if ($requesterDisplay) {
            $requesterBlock['display'] = $requesterDisplay;
        }

        $payload = [
            'status' => $status,
            'intent' => $intent,
            'priority' => $priority,
            'code' => $codeBlock,
            'subject' => ['reference' => "Patient/{$patientId}"],
            'encounter' => $encounterBlock,
            'requester' => $requesterBlock,
        ];

        if ($identifier) {
            $payload['identifier'] = [
                [
                    'system' => $this->identifierSystem($type),
                    'value' => $identifier,
                ],
            ];
        }

        if ($category) {
            $categoryCoding = [
                'system' => FhirDictionary::SNOMED,
                'code' => $category,
            ];
            if ($categoryDisplay) {
                $categoryCoding['display'] = $categoryDisplay;
            }
            $payload['category'] = [['coding' => [$categoryCoding]]];
        }

        if ($occurrenceDateTime) {
            $payload['occurrenceDateTime'] = $occurrenceDateTime;
        }

        if ($authoredOn) {
            $payload['authoredOn'] = $authoredOn;
        }

        if ($performerOrgId) {
            $performerBlock = ['reference' => "Organization/{$performerOrgId}"];
            if ($performerDisplay) {
                $performerBlock['display'] = $performerDisplay;
            }
            $payload['performer'] = [$performerBlock];
        }

        if ($reasonText) {
            $payload['reasonCode'] = [['text' => $reasonText]];
        }

        if ($note) {
            $payload['note'] = [['text' => $note]];
        }

        return $this->create($payload);
    }

    /**
     * Buat ServiceRequest laboratorium.
     *
     * @param string $identifier  Nilai identifier: "{noorder}.{kode_item}" sesuai konvensi SIMRS
     * @param string $codeSystem  System terminologi kode pemeriksaan (dari mapping SIMRS)
     */
    public function createLabRequest(
        string $patientId,
        string $encounterId,
        string $requesterId,
        string $code,
        string $codeSystem,
        string $display,
        string $codeText,
        string $identifier,
        string $encounterDisplay,
        string $requesterDisplay,
        string $performerOrgId,
        ?string $reasonText = null,
    ): FhirResponse {
        return $this->createServiceRequest(
            patientId: $patientId,
            encounterId: $encounterId,
            requesterId: $requesterId,
            code: $code,
            codeSystem: $codeSystem,
            display: $display,
            type: 'lab',
            codeText: $codeText,
            identifier: $identifier,
            category: '108252007',
            categoryDisplay: 'Laboratory procedure',
            encounterDisplay: $encounterDisplay,
            authoredOn: now()->toIso8601String(),
            requesterDisplay: $requesterDisplay,
            performerOrgId: $performerOrgId,
            performerDisplay: 'Ruang Laborat/Petugas Laborat',
            reasonText: $reasonText,
        );
    }

    /**
     * Buat ServiceRequest radiologi.
     *
     * @param string $identifier  Nilai identifier: "{noorder}" (accession number) sesuai konvensi SIMRS
     * @param string $codeSystem  System terminologi kode pemeriksaan (dari mapping SIMRS)
     */
    public function createRadiologyRequest(
        string $patientId,
        string $encounterId,
        string $requesterId,
        string $code,
        string $codeSystem,
        string $display,
        string $codeText,
        string $identifier,
        string $encounterDisplay,
        string $requesterDisplay,
        string $performerOrgId,
        ?string $reasonText = null,
    ): FhirResponse {
        return $this->createServiceRequest(
            patientId: $patientId,
            encounterId: $encounterId,
            requesterId: $requesterId,
            code: $code,
            codeSystem: $codeSystem,
            display: $display,
            type: 'radiology',
            codeText: $codeText,
            identifier: $identifier,
            category: '363679005',
            categoryDisplay: 'Imaging',
            encounterDisplay: $encounterDisplay,
            authoredOn: now()->toIso8601String(),
            requesterDisplay: $requesterDisplay,
            performerOrgId: $performerOrgId,
            performerDisplay: 'Ruang Radiologi/Petugas Radiologi',
            reasonText: $reasonText,
        );
    }

    public function cancelRequest(string $id): FhirResponse
    {
        return $this->patch($id, [
            [
                'op' => 'replace',
                'path' => '/status',
                'value' => 'revoked',
            ],
        ]);
    }

    public function completeRequest(string $id): FhirResponse
    {
        return $this->patch($id, [
            [
                'op' => 'replace',
                'path' => '/status',
                'value' => 'completed',
            ],
        ]);
    }
}
