<?php

namespace App\Services\SatuSehat\Resources;

use App\Services\SatuSehat\DTO\FhirResponse;
use App\Services\SatuSehat\FhirDictionary;
use App\Services\SatuSehat\SatuSehatBaseService;

class MedicationService extends SatuSehatBaseService
{
    protected function getResourceType(): string
    {
        return 'Medication';
    }

    public function searchByCode(string $code): FhirResponse
    {
        return $this->search([
            'code' => $code,
        ]);
    }

    public function searchByKfaCode(string $kfaCode): FhirResponse
    {
        return $this->search([
            'code' => FhirDictionary::KEMKES_SYS_KFA . "|{$kfaCode}",
        ]);
    }

    public function searchByIdentifier(string $identifier): FhirResponse
    {
        return $this->search([
            'identifier' => FhirDictionary::medicationSystem($this->getOrganizationId()) . "|{$identifier}",
        ]);
    }

    public function createMedication(
        string $kfaCode,
        string $kfaDisplay,
        ?string $identifier = null,
        ?string $status = 'active',
        ?array $form = null,
        ?array $ingredient = null,
        ?array $extension = null,
    ): FhirResponse {
        $payload = [
            'status' => $status,
            'code' => [
                'coding' => [
                    [
                        'system' => FhirDictionary::KEMKES_SYS_KFA,
                        'code' => $kfaCode,
                        'display' => $kfaDisplay,
                    ],
                ],
            ],
        ];

        if ($identifier) {
            $payload['identifier'] = [
                [
                    'system' => FhirDictionary::medicationSystem($this->getOrganizationId()),
                    'use' => 'official',
                    'value' => $identifier,
                ],
            ];
        }

        if ($form) {
            $payload['form'] = $form;
        }

        if ($ingredient) {
            $payload['ingredient'] = $ingredient;
        }

        if ($extension) {
            $payload['extension'] = $extension;
        }

        return $this->create($payload);
    }

    public function createCompoundMedication(
        string $identifier,
        string $displayName,
        array $ingredients,
        ?array $form = null,
    ): FhirResponse {
        $ingredientData = [];
        foreach ($ingredients as $ing) {
            $ingredientData[] = [
                'itemCodeableConcept' => [
                    'coding' => [
                        [
                            'system' => FhirDictionary::KEMKES_SYS_KFA,
                            'code' => $ing['code'],
                            'display' => $ing['display'],
                        ],
                    ],
                ],
                'isActive' => $ing['isActive'] ?? true,
                'strength' => [
                    'numerator' => [
                        'value' => $ing['strength_value'],
                        'system' => FhirDictionary::UCUM,
                        'code' => $ing['strength_unit'],
                    ],
                    'denominator' => [
                        'value' => $ing['denominator_value'] ?? 1,
                        'system' => FhirDictionary::HL7_DRUG_FORM,
                        'code' => $ing['denominator_unit'] ?? 'TAB',
                    ],
                ],
            ];
        }

        $payload = [
            'status' => 'active',
            'identifier' => [
                [
                    'system' => FhirDictionary::medicationSystem($this->getOrganizationId()),
                    'use' => 'official',
                    'value' => $identifier,
                ],
            ],
            'code' => [
                'coding' => [
                    [
                        'system' => FhirDictionary::KEMKES_SYS_KFA,
                        'code' => '91000330',
                        'display' => 'Compound Medication',
                    ],
                ],
            ],
            'extension' => [
                [
                    'url' => FhirDictionary::KEMKES_SD_MEDICATION_TYPE,
                    'valueCodeableConcept' => [
                        'coding' => [
                            [
                                'system' => FhirDictionary::KEMKES_CS_MEDICATION_TYPE,
                                'code' => 'NC',
                                'display' => 'Non-compound',
                            ],
                        ],
                    ],
                ],
            ],
            'ingredient' => $ingredientData,
        ];

        if ($form) {
            $payload['form'] = $form;
        }

        return $this->create($payload);
    }
}
