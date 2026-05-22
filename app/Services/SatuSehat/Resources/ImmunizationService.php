<?php

namespace App\Services\SatuSehat\Resources;

use App\Services\SatuSehat\DTO\FhirResponse;
use App\Services\SatuSehat\FhirDictionary;
use App\Services\SatuSehat\SatuSehatBaseService;

class ImmunizationService extends SatuSehatBaseService
{
    protected function getResourceType(): string
    {
        return 'Immunization';
    }

    public function searchByPatient(string $patientId): FhirResponse
    {
        return $this->search([
            'patient' => $patientId,
        ]);
    }

    public function searchByVaccineCode(string $code): FhirResponse
    {
        return $this->search([
            'vaccine-code' => $code,
        ]);
    }

    public function createImmunization(
        string $patientId,
        string $vaccineCode,
        string $vaccineDisplay,
        string $performerId,
        string $locationId,
        string $status = 'completed',
        bool $primarySource = true,
        ?string $reasonCode = null,
        ?string $reasonDisplay = null,
        ?string $timingCode = null,
        ?string $timingDisplay = null,
        ?string $encounterId = null,
        ?string $occurrenceDateTime = null,
        ?string $lotNumber = null,
        ?string $expirationDate = null,
        ?string $site = null,
        ?string $route = null,
        ?float $doseQuantity = null,
        ?string $note = null,
    ): FhirResponse {
        $payload = [
            'status' => $status,
            'vaccineCode' => [
                'coding' => [
                    [
                        'system' => FhirDictionary::KEMKES_SYS_KFA,
                        'code' => $vaccineCode,
                        'display' => $vaccineDisplay,
                    ],
                ],
            ],
            'patient' => [
                'reference' => "Patient/{$patientId}",
            ],
            "primarySource" => $primarySource,
            'occurrenceDateTime' => $occurrenceDateTime ?? now()->toIso8601String(),
            'performer' => [
                [
                    'function' => [
                        'coding' => [
                            [
                                'system' => FhirDictionary::HL7_CS_V2_0443,
                                'code' => 'AP',
                                'display' => 'Administering Provider',
                            ],
                        ],
                    ],
                    'actor' => [
                        'reference' => "Practitioner/{$performerId}",
                    ],
                ],
            ],
            'location' => [
                'reference' => "Location/{$locationId}",
            ],
        ];

        if ($encounterId) {
            $payload['encounter'] = [
                'reference' => "Encounter/{$encounterId}",
            ];
        }

        if ($lotNumber) {
            $payload['lotNumber'] = $lotNumber;
        }

        if ($expirationDate) {
            $payload['expirationDate'] = $expirationDate;
        }

        if ($site) {
            $payload['site'] = [
                'coding' => [
                    [
                        'system' => FhirDictionary::HL7_CS_ACT_SITE,
                        'code' => $site,
                    ],
                ],
            ];
        }

        if ($route) {
            $payload['route'] = [
                'coding' => [
                    [
                        'system' => FhirDictionary::HL7_CS_ROUTE_ADMIN,
                        'code' => $route,
                    ],
                ],
            ];
        }

        if ($doseQuantity) {
            $payload['protocolApplied'] = [
                [
                    'doseNumberPositiveInt' => $doseQuantity,
                ],
            ];
        }

        if ($reasonCode && $reasonDisplay) {
            $codings = [
                [
                    'system'  => FhirDictionary::KEMKES_CS_IMMUNIZATION_REASON,
                    'code'    => $reasonCode,
                    'display' => $reasonDisplay,
                ],
            ];
            if ($timingCode && $timingDisplay) {
                $codings[] = [
                    'system'  => FhirDictionary::KEMKES_CS_IMMUNIZATION_TIMING,
                    'code'    => $timingCode,
                    'display' => $timingDisplay,
                ];
            }
            $payload['reasonCode'] = [['coding' => $codings]];
        }

        if ($note) {
            $payload['note'] = [
                ['text' => $note],
            ];
        }

        return $this->create($payload);
    }

    public function recordVaccination(
        string $patientId,
        string $vaccineCode,
        string $vaccineDisplay,
        string $performerId,
        string $locationId,
        int $doseNumber,
        ?string $encounterId = null,
        ?string $lotNumber = null,
        ?string $expirationDate = null,
    ): FhirResponse {
        return $this->createImmunization(
            patientId: $patientId,
            vaccineCode: $vaccineCode,
            vaccineDisplay: $vaccineDisplay,
            performerId: $performerId,
            locationId: $locationId,
            encounterId: $encounterId,
            lotNumber: $lotNumber,
            expirationDate: $expirationDate,
            doseNumber: $doseNumber,
            route: 'IM',
        );
    }
}
