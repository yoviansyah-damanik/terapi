<?php

namespace App\Services\SatuSehat\Resources;

use App\Services\SatuSehat\DTO\FhirResponse;
use App\Services\SatuSehat\FhirDictionary;
use App\Services\SatuSehat\SatuSehatBaseService;

class DiagnosticReportService extends SatuSehatBaseService
{
    protected function getResourceType(): string
    {
        return 'DiagnosticReport';
    }

    public function searchBySubject(string $patientId): FhirResponse
    {
        return $this->search(['subject' => $patientId]);
    }

    public function searchByEncounter(string $encounterId): FhirResponse
    {
        return $this->search(['encounter' => $encounterId]);
    }

    public function searchByCode(string $code): FhirResponse
    {
        return $this->search(['code' => $code]);
    }

    public function searchByBasedOn(string $serviceRequestId): FhirResponse
    {
        return $this->search(['based-on' => $serviceRequestId]);
    }

    /**
     * Buat DiagnosticReport generik.
     * Semua parameter required diletakkan di atas, optional di bawah.
     */
    public function createDiagnosticReport(
        string $patientId,
        string $encounterId,
        string $performerId,
        string $code,
        string $display,
        string $category,
        array $observationIds = [],
        string $status = 'final',
        ?string $identifier = null,
        ?string $serviceRequestId = null,
        ?string $effectiveDateTime = null,
        ?string $issued = null,
        ?string $conclusion = null,
        ?array $conclusionCode = null,
        ?array $specimenIds = null,
        ?string $categoryDisplay = null,
    ): FhirResponse {
        $payload = [
            'status' => $status,
            'category' => [
                [
                    'coding' => [
                        [
                            'system' => FhirDictionary::HL7_CS_V2_0074,
                            'code' => $category,
                            'display' => $categoryDisplay ?? $this->getCategoryDisplay($category),
                        ]
                    ],
                ]
            ],
            'code' => [
                'coding' => [
                    [
                        'system' => FhirDictionary::LOINC,
                        'code' => $code,
                        'display' => $display,
                    ]
                ],
            ],
            'subject' => ['reference' => "Patient/{$patientId}"],
            'encounter' => ['reference' => "Encounter/{$encounterId}"],
            'performer' => [
                ['reference' => "Practitioner/{$performerId}"],
                ['reference' => 'Organization/' . $this->getOrganizationId()],
            ],
        ];

        // result hanya dimasukkan jika ada — array kosong tidak valid di FHIR
        if (!empty($observationIds)) {
            $payload['result'] = array_map(fn($id) => ['reference' => "Observation/{$id}"], $observationIds);
        }

        if ($identifier) {
            $system = $category === 'RAD'
                ? FhirDictionary::KEMKES_SYS_DIAGNOSTIC_LAB . '/' . $this->getOrganizationId() . '/rad'
                : FhirDictionary::KEMKES_SYS_DIAGNOSTIC_LAB . '/' . $this->getOrganizationId() . '/lab';

            $payload['identifier'] = [
                [
                    'system' => $system,
                    'use' => 'official',
                    'value' => $identifier,
                ]
            ];
        }

        if ($serviceRequestId) {
            $payload['basedOn'] = [['reference' => "ServiceRequest/{$serviceRequestId}"]];
        }

        if ($effectiveDateTime) {
            $payload['effectiveDateTime'] = $effectiveDateTime;
        }

        if ($issued) {
            $payload['issued'] = $issued;
        }

        if ($conclusion) {
            $payload['conclusion'] = $conclusion;
        }

        if ($conclusionCode) {
            $payload['conclusionCode'] = $conclusionCode;
        }

        if ($specimenIds) {
            $payload['specimen'] = array_map(fn($id) => ['reference' => "Specimen/{$id}"], $specimenIds);
        }

        return $this->create($payload);
    }

    public function createLabReport(
        string $patientId,
        string $encounterId,
        string $performerId,
        string $loincCode,
        string $loincDisplay,
        array $observationIds = [],
        ?string $identifier = null,
        ?string $serviceRequestId = null,
        ?string $effectiveDateTime = null,
        ?string $conclusion = null,
        ?array $specimenIds = null,
        string $category = 'LAB',
        ?string $categoryDisplay = null,
    ): FhirResponse {
        $now = $effectiveDateTime ?? now()->toIso8601String();

        return $this->createDiagnosticReport(
            patientId: $patientId,
            encounterId: $encounterId,
            performerId: $performerId,
            code: $loincCode,
            display: $loincDisplay,
            category: $category,
            observationIds: $observationIds,
            identifier: $identifier,
            serviceRequestId: $serviceRequestId,
            effectiveDateTime: $now,
            issued: $now,
            conclusion: $conclusion,
            specimenIds: $specimenIds,
            categoryDisplay: $categoryDisplay,
        );
    }

    public function createRadiologyReport(
        string $patientId,
        string $encounterId,
        string $performerId,
        string $code,
        string $display,
        array $observationIds = [],
        ?string $identifier = null,
        ?string $serviceRequestId = null,
        ?string $conclusion = null,
        array $imagingStudyIds = [],
        ?string $effectiveDateTime = null,
        ?string $issued = null,
        string $category = 'RAD',
        ?string $categoryDisplay = null,
    ): FhirResponse {
        $now = $effectiveDateTime ?? now()->toIso8601String();

        $payload = [
            'status' => 'final',
            'category' => [
                [
                    'coding' => [
                        [
                            'system' => FhirDictionary::HL7_CS_V2_0074,
                            'code' => $category,
                            'display' => $categoryDisplay ?? $this->getCategoryDisplay($category),
                        ]
                    ],
                ]
            ],
            'code' => [
                'coding' => [
                    [
                        'system' => FhirDictionary::LOINC,
                        'code' => $code,
                        'display' => $display,
                    ]
                ],
            ],
            'subject' => ['reference' => "Patient/{$patientId}"],
            'encounter' => ['reference' => "Encounter/{$encounterId}"],
            'effectiveDateTime' => $now,
            'issued' => $issued ?? $now,
            'performer' => [
                ['reference' => "Practitioner/{$performerId}"],
                ['reference' => 'Organization/' . $this->getOrganizationId()],
            ],
        ];

        if ($identifier) {
            $payload['identifier'] = [
                [
                    'system' => FhirDictionary::KEMKES_SYS_DIAGNOSTIC_LAB . '/' . $this->getOrganizationId() . '/rad',
                    'use' => 'official',
                    'value' => $identifier,
                ]
            ];
        }

        if ($serviceRequestId) {
            $payload['basedOn'] = [['reference' => "ServiceRequest/{$serviceRequestId}"]];
        }


        if (!empty($imagingStudyIds)) {
            $payload['imagingStudy'] = array_map(fn($id) => ['reference' => "ImagingStudy/{$id}"], $imagingStudyIds);
        }

        if (!empty($observationIds)) {
            $payload['result'] = array_map(fn($id) => ['reference' => "Observation/{$id}"], $observationIds);
        }

        if ($conclusion) {
            $payload['conclusion'] = $conclusion;
        }

        return $this->create($payload);
    }

    protected function getCategoryDisplay(string $code): string
    {
        return match ($code) {
            'LAB' => 'Laboratory',
            'RAD' => 'Radiology',
            'PAT' => 'Pathology',
            'MB' => 'Microbiology',
            'CH' => 'Chemistry',
            'HM' => 'Hematology',
            default => $code,
        };
    }
}
