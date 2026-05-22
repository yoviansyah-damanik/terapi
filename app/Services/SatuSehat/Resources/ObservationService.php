<?php

namespace App\Services\SatuSehat\Resources;

use App\Services\SatuSehat\DTO\FhirResponse;
use App\Services\SatuSehat\FhirDictionary;
use App\Services\SatuSehat\SatuSehatBaseService;

class ObservationService extends SatuSehatBaseService
{
    protected function getResourceType(): string
    {
        return 'Observation';
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

    public function createObservation(
        string $patientId,
        string $encounterId,
        string $code,
        string $display,
        mixed $value,
        string $status = 'final',
        ?string $system = FhirDictionary::LOINC,
        ?string $effectiveDateTime = null,
        ?string $performerId = null,
        ?string $unit = null,
        ?string $unitCode = null,
        ?array $interpretation = null,
        ?array $referenceRange = null,
        string $category = 'vital-signs',
    ): FhirResponse {
        $categoryLabels = [
            'vital-signs' => 'Vital Signs',
            'imaging' => 'Imaging',
            'laboratory' => 'Laboratory',
            'procedure' => 'Procedure',
            'survey' => 'Survey',
            'exam' => 'Exam',
            'therapy' => 'Therapy',
            'activity' => 'Activity',
        ];

        $payload = [
            'status' => $status,
            'category' => [
                [
                    'coding' => [
                        [
                            'system' => FhirDictionary::HL7_CS_OBS_CATEGORY,
                            'code' => $category,
                            'display' => $categoryLabels[$category] ?? ucfirst($category),
                        ],
                    ],
                ],
            ],
            'code' => [
                'coding' => [
                    [
                        'system' => $system,
                        'code' => $code,
                        'display' => $display,
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

        if ($effectiveDateTime) {
            $payload['effectiveDateTime'] = $effectiveDateTime;
        }

        if ($performerId) {
            $payload['performer'] = [
                ['reference' => "Practitioner/{$performerId}"],
            ];
        }

        if (is_numeric($value) && $unit) {
            $payload['valueQuantity'] = [
                'value' => (float) $value,
                'unit' => $unit,
                'system' => FhirDictionary::UCUM,
                'code' => $unitCode ?? $unit,
            ];
        } elseif (is_string($value)) {
            $payload['valueString'] = $value;
        } elseif (is_array($value) && isset($value['coding'])) {
            $payload['valueCodeableConcept'] = $value;
        }

        if ($interpretation) {
            $payload['interpretation'] = $interpretation;
        }

        if ($referenceRange) {
            $payload['referenceRange'] = $referenceRange;
        }

        return $this->create($payload);
    }

    /**
     * Buat Observation hasil lab PK dengan category 'laboratory' dan valueString.
     */
    public function createLabObservation(
        string $patientId,
        string $encounterId,
        string $performerId,
        string $organizationId,
        string $code,
        string $display,
        string $codeSystem = FhirDictionary::LOINC,
        string $valueString = '',
        ?string $specimenId = null,
        ?string $effectiveDateTime = null,
        ?string $identifier = null,
        ?string $encounterDisplay = null,
    ): FhirResponse {
        $payload = [
            'status' => 'final',
            'category' => [
                [
                    'coding' => [
                        [
                            'system' => FhirDictionary::HL7_CS_OBS_CATEGORY,
                            'code' => 'laboratory',
                            'display' => 'Laboratory',
                        ],
                    ],
                ],
            ],
            'code' => [
                'coding' => [
                    [
                        'system' => $codeSystem,
                        'code' => $code,
                        'display' => $display,
                    ],
                ],
            ],
            'subject' => ['reference' => "Patient/{$patientId}"],
            'encounter' => ['reference' => "Encounter/{$encounterId}"],
            'performer' => [
                ['reference' => "Practitioner/{$performerId}"],
                ['reference' => "Organization/{$organizationId}"],
            ],
            'valueString' => $valueString,
        ];

        if ($encounterDisplay) {
            $payload['encounter']['display'] = $encounterDisplay;
        }

        if ($effectiveDateTime) {
            $payload['effectiveDateTime'] = $effectiveDateTime;
        }

        if ($specimenId) {
            $payload['specimen'] = ['reference' => "Specimen/{$specimenId}"];
        }

        if ($identifier) {
            $payload['identifier'] = [
                [
                    'system' => FhirDictionary::observationSystem($this->getOrganizationId()),
                    'value' => $identifier,
                ],
            ];
        }

        return $this->create($payload);
    }

    /**
     * Buat Observation hasil radiologi dengan category 'imaging' dan valueString.
     */
    public function createRadiologyObservation(
        string $patientId,
        string $encounterId,
        string $performerId,
        string $code,
        string $display,
        string $valueString,
        string $codeSystem = FhirDictionary::LOINC,
        ?string $bodySiteCode = null,
        ?string $bodySiteDisplay = null,
        ?string $bodySiteSystem = FhirDictionary::SNOMED,
        ?string $derivedFromId = null,
        ?string $basedOnId = null,
        ?string $effectiveDateTime = null,
        ?string $issued = null,
        ?string $identifier = null,
        ?string $encounterDisplay = null,
        ?string $specimenId = null,
    ): FhirResponse {
        $payload = [
            'status' => 'final',
            'category' => [
                [
                    'coding' => [
                        [
                            'system' => FhirDictionary::HL7_CS_OBS_CATEGORY,
                            'code' => 'imaging',
                            'display' => 'Imaging',
                        ],
                    ],
                ],
            ],
            'code' => [
                'coding' => [
                    [
                        'system' => $codeSystem,
                        'code' => $code,
                        'display' => $display,
                    ],
                ],
            ],
            'subject' => ['reference' => "Patient/{$patientId}"],
            'encounter' => ['reference' => "Encounter/{$encounterId}"],
            'performer' => [
                ['reference' => "Practitioner/{$performerId}"],
                ['reference' => 'Organization/' . $this->getOrganizationId()],
            ],
            'valueString' => $valueString,
        ];

        if ($encounterDisplay) {
            $payload['encounter']['display'] = $encounterDisplay;
        }

        if ($bodySiteCode && $bodySiteDisplay) {
            $payload['bodySite'] = [
                'coding' => [
                    [
                        'system' => $bodySiteSystem,
                        'code' => $bodySiteCode,
                        'display' => $bodySiteDisplay,
                    ],
                ],
            ];
        }

        if ($derivedFromId) {
            $payload['derivedFrom'] = [
                ['reference' => "ImagingStudy/{$derivedFromId}"],
            ];
        }

        if ($basedOnId) {
            $payload['basedOn'] = [
                ['reference' => "ServiceRequest/{$basedOnId}"],
            ];
        }

        if ($effectiveDateTime) {
            $payload['effectiveDateTime'] = $effectiveDateTime;
            $payload['issued'] = $issued ?? $effectiveDateTime;
        }

        if ($specimenId) {
            $payload['specimen'] = ['reference' => "Specimen/{$specimenId}"];
        }

        if ($identifier) {
            $payload['identifier'] = [
                [
                    'system' => FhirDictionary::observationSystem($this->getOrganizationId()),
                    'value' => $identifier,
                ],
            ];
        }

        return $this->create($payload);
    }

    public function createVitalSign(
        string $patientId,
        string $encounterId,
        string $type,
        float $value,
        ?string $effectiveDateTime = null,
        ?string $performerId = null,
    ): FhirResponse {
        $vitalSigns = $this->getVitalSignConfig($type);

        if (! $vitalSigns) {
            throw new \InvalidArgumentException("Unknown vital sign type: {$type}");
        }

        return $this->createObservation(
            patientId: $patientId,
            encounterId: $encounterId,
            code: $vitalSigns['code'],
            display: $vitalSigns['display'],
            value: $value,
            effectiveDateTime: $effectiveDateTime,
            performerId: $performerId,
            unit: $vitalSigns['unit'],
            unitCode: $vitalSigns['unitCode'],
        );
    }

    protected function getVitalSignConfig(string $type): ?array
    {
        $configs = [
            'systolic' => [
                'code' => '8480-6',
                'display' => 'Systolic blood pressure',
                'unit' => 'mm[Hg]',
                'unitCode' => 'mm[Hg]',
            ],
            'diastolic' => [
                'code' => '8462-4',
                'display' => 'Diastolic blood pressure',
                'unit' => 'mm[Hg]',
                'unitCode' => 'mm[Hg]',
            ],
            'heart_rate' => [
                'code' => '8867-4',
                'display' => 'Heart rate',
                'unit' => 'beats/minute',
                'unitCode' => '/min',
            ],
            'respiratory_rate' => [
                'code' => '9279-1',
                'display' => 'Respiratory rate',
                'unit' => 'breaths/minute',
                'unitCode' => '/min',
            ],
            'temperature' => [
                'code' => '8310-5',
                'display' => 'Body temperature',
                'unit' => 'C',
                'unitCode' => 'Cel',
            ],
            'oxygen_saturation' => [
                'code' => '2708-6',
                'display' => 'Oxygen saturation in Arterial blood',
                'unit' => '%',
                'unitCode' => '%',
            ],
            'height' => [
                'code' => '8302-2',
                'display' => 'Body height',
                'unit' => 'cm',
                'unitCode' => 'cm',
            ],
            'weight' => [
                'code' => '29463-7',
                'display' => 'Body weight',
                'unit' => 'kg',
                'unitCode' => 'kg',
            ],
            'bmi' => [
                'code' => '39156-5',
                'display' => 'Body mass index (BMI)',
                'unit' => 'kg/m2',
                'unitCode' => 'kg/m2',
            ],
        ];

        return $configs[$type] ?? null;
    }
}
