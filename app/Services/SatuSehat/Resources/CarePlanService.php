<?php

namespace App\Services\SatuSehat\Resources;

use App\Services\SatuSehat\DTO\FhirResponse;
use App\Services\SatuSehat\FhirDictionary;
use App\Services\SatuSehat\SatuSehatBaseService;

class CarePlanService extends SatuSehatBaseService
{
    protected function getResourceType(): string
    {
        return 'CarePlan';
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

    public function searchByCategory(string $category): FhirResponse
    {
        return $this->search([
            'category' => $category,
        ]);
    }

    public function createCarePlan(
        string $patientId,
        string $encounterId,
        string $authorId,
        string $title,
        string $status = 'active',
        string $intent = 'plan',
        ?string $identifier = null,
        ?string $categoryCode = null,
        ?string $categoryDisplay = null,
        ?string $description = null,
        ?string $periodStart = null,
        ?string $periodEnd = null,
        ?array $activities = null,
        ?array $goals = null,
        ?array $addresses = null,
        ?string $note = null,
    ): FhirResponse {
        $payload = [
            'status' => $status,
            'intent' => $intent,
            'title' => $title,
            'subject' => [
                'reference' => "Patient/{$patientId}",
            ],
            'encounter' => [
                'reference' => "Encounter/{$encounterId}",
            ],
            'author' => [
                'reference' => "Practitioner/{$authorId}",
            ],
            'created' => now()->toIso8601String(),
        ];

        if ($identifier) {
            $payload['identifier'] = [
                [
                    'system' => FhirDictionary::KEMKES_SYS_CAREPLAN . '/' . $this->getOrganizationId(),
                    'use' => 'official',
                    'value' => $identifier,
                ],
            ];
        }

        if ($categoryCode) {
            $payload['category'] = [
                [
                    'coding' => [
                        [
                            'system' => FhirDictionary::SNOMED,
                            'code' => $categoryCode,
                            'display' => $categoryDisplay ?? $categoryCode,
                        ],
                    ],
                ],
            ];
        }

        if ($description) {
            $payload['description'] = $description;
        }

        if ($periodStart) {
            $payload['period'] = ['start' => $periodStart];
            if ($periodEnd) {
                $payload['period']['end'] = $periodEnd;
            }
        }

        if ($activities) {
            $payload['activity'] = $activities;
        }

        if ($goals) {
            $payload['goal'] = array_map(fn ($g) => ['reference' => $g], $goals);
        }

        if ($addresses) {
            $payload['addresses'] = array_map(fn ($a) => ['reference' => $a], $addresses);
        }

        if ($note) {
            $payload['note'] = [
                ['text' => $note],
            ];
        }

        return $this->create($payload);
    }

    public function createTreatmentPlan(
        string $patientId,
        string $encounterId,
        string $authorId,
        string $title,
        string $description,
        array $conditionIds,
        ?string $periodStart = null,
        ?string $periodEnd = null,
    ): FhirResponse {
        $addresses = array_map(fn ($id) => "Condition/{$id}", $conditionIds);

        return $this->createCarePlan(
            patientId: $patientId,
            encounterId: $encounterId,
            authorId: $authorId,
            title: $title,
            categoryCode: '736271009',
            categoryDisplay: 'Outpatient care plan',
            description: $description,
            periodStart: $periodStart ?? now()->toIso8601String(),
            periodEnd: $periodEnd,
            addresses: $addresses,
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

    public function completePlan(string $id): FhirResponse
    {
        return $this->updateStatus($id, 'completed');
    }

    public function buildActivity(
        string $description,
        string $status = 'not-started',
        ?string $scheduledDateTime = null,
        ?string $performerReference = null,
    ): array {
        $activity = [
            'detail' => [
                'status' => $status,
                'description' => $description,
            ],
        ];

        if ($scheduledDateTime) {
            $activity['detail']['scheduledString'] = $scheduledDateTime;
        }

        if ($performerReference) {
            $activity['detail']['performer'] = [
                ['reference' => $performerReference],
            ];
        }

        return $activity;
    }
}
