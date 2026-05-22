<?php

namespace App\Services\SatuSehat\Resources;

use App\Services\SatuSehat\DTO\FhirResponse;
use App\Services\SatuSehat\FhirDictionary;
use App\Services\SatuSehat\SatuSehatBaseService;

class ClinicalImpressionService extends SatuSehatBaseService
{
    protected function getResourceType(): string
    {
        return 'ClinicalImpression';
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

    public function createClinicalImpression(
        string $patientId,
        string $encounterId,
        string $assessorId,
        string $status = 'completed',
        ?string $identifier = null,
        ?string $effectiveDateTime = null,
        ?string $description = null,
        ?array $findings = null,
        ?array $problems = null,
        ?string $summary = null,
        ?string $prognosisDescription = null,
        ?array $prognosisCodeableConcept = null,
    ): FhirResponse {
        $payload = [
            'status' => $status,
            'subject' => [
                'reference' => "Patient/{$patientId}",
            ],
            'encounter' => [
                'reference' => "Encounter/{$encounterId}",
            ],
            'assessor' => [
                'reference' => "Practitioner/{$assessorId}",
            ],
        ];

        if ($identifier) {
            $payload['identifier'] = [
                [
                    'system' => FhirDictionary::KEMKES_SYS_CLINICAL_IMP . '/' . $this->getOrganizationId(),
                    'use' => 'official',
                    'value' => $identifier,
                ],
            ];
        }

        if ($effectiveDateTime) {
            $payload['effectiveDateTime'] = $effectiveDateTime;
        } else {
            $payload['effectiveDateTime'] = now()->toIso8601String();
        }

        if ($description) {
            $payload['description'] = $description;
        }

        if ($findings) {
            $payload['finding'] = $findings;
        }

        if ($problems) {
            $payload['problem'] = array_map(fn ($p) => ['reference' => $p], $problems);
        }

        if ($summary) {
            $payload['summary'] = $summary;
        }

        if ($prognosisDescription) {
            $payload['prognosisCodeableConcept'] = [
                [
                    'text' => $prognosisDescription,
                ],
            ];
        }

        if ($prognosisCodeableConcept) {
            $payload['prognosisCodeableConcept'] = $prognosisCodeableConcept;
        }

        return $this->create($payload);
    }

    public function createAssessment(
        string $patientId,
        string $encounterId,
        string $assessorId,
        string $summary,
        ?array $conditionIds = null,
        ?string $prognosisDescription = null,
    ): FhirResponse {
        $findings = null;
        if ($conditionIds) {
            $findings = array_map(fn ($id) => [
                'itemReference' => [
                    'reference' => "Condition/{$id}",
                ],
            ], $conditionIds);
        }

        return $this->createClinicalImpression(
            patientId: $patientId,
            encounterId: $encounterId,
            assessorId: $assessorId,
            findings: $findings,
            summary: $summary,
            prognosisDescription: $prognosisDescription,
        );
    }

    public function buildFinding(
        string $code,
        string $display,
        string $system = FhirDictionary::SNOMED,
    ): array {
        return [
            'itemCodeableConcept' => [
                'coding' => [
                    [
                        'system' => $system,
                        'code' => $code,
                        'display' => $display,
                    ],
                ],
            ],
        ];
    }
}
