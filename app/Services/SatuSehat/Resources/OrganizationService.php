<?php

namespace App\Services\SatuSehat\Resources;

use App\Services\SatuSehat\DTO\FhirResponse;
use App\Services\SatuSehat\FhirDictionary;
use App\Services\SatuSehat\SatuSehatBaseService;

class OrganizationService extends SatuSehatBaseService
{
    protected function getResourceType(): string
    {
        return 'Organization';
    }

    public function searchByName(string $name): FhirResponse
    {
        return $this->search([
            'name' => $name,
        ]);
    }

    public function searchByPartOf(string $organizationId): FhirResponse
    {
        return $this->search([
            'partof' => $organizationId,
        ]);
    }

    public function createOrganization(
        string $name,
        string $identifier,
        string $typeCode = 'dept',
        string $typeDisplay = 'Hospital Department',
    ): FhirResponse {
        $payload = [
            'active' => true,
            'identifier' => [
                [
                    'use' => 'official',
                    'system' => FhirDictionary::organizationSystem($this->getOrganizationId()),
                    'value' => $identifier,
                ],
            ],
            'type' => [
                [
                    'coding' => [
                        [
                            'system' => FhirDictionary::HL7_CS_ORG_TYPE,
                            'code' => $typeCode,
                            'display' => $typeDisplay,
                        ]
                    ]
                ]
            ],
            'name' => $name,
        ];

        $payload['telecom'] = [
            [
                'system' => 'phone',
                'value' => config('hospital.phone', ''),
                'use' => 'work'
            ],
            [
                'system' => 'email',
                'value' => config('hospital.email', ''),
                'use' => 'work'
            ],
            [
                'system' => 'url',
                'value' => config('hospital.website', ''),
                'use' => 'work'
            ],
        ];

        $payload['address'] = [
            [
                'use' => 'work',
                'type' => 'both',
                'line' => [
                    config('hospital.address', '')
                ],
                'city' => config('hospital.city', ''),
                'postalCode' => config('hospital.postal_code', ''),
                'country' => 'ID',
                'extension' => [
                    [
                        'url' => FhirDictionary::KEMKES_SD_ADM_CODE,
                        'extension' => [
                            [
                                'url' => 'province',
                                'valueCode' => config('hospital.propinsi', '')
                            ],
                            [
                                'url' => 'city',
                                'valueCode' => config('hospital.kabupaten', '')
                            ],
                            [
                                'url' => 'district',
                                'valueCode' => config('hospital.kecamatan', '')
                            ],
                            [
                                'url' => 'village',
                                'valueCode' => config('hospital.kelurahan', '')
                            ],
                        ]
                    ]
                ]
            ]
        ];

        $payload['partOf'] = [
            'reference' => "Organization/" . $this->getOrganizationId(),
        ];

        return $this->create($payload);
    }

    public function updateOrganization(
        string $id,
        string $name,
        string $identifier,
        bool $active = true,
        string $typeCode = 'dept',
        string $typeDisplay = 'Hospital Department',
    ): FhirResponse {
        $payload = [
            'active' => $active,
            'identifier' => [
                [
                    'use' => 'official',
                    'system' => FhirDictionary::organizationSystem($this->getOrganizationId()),
                    'value' => $identifier,
                ],
            ],
            'type' => [
                [
                    'coding' => [
                        [
                            'system' => FhirDictionary::HL7_CS_ORG_TYPE,
                            'code' => $typeCode,
                            'display' => $typeDisplay,
                        ]
                    ]
                ]
            ],
            'name' => $name,
        ];

        $payload['telecom'] = [
            [
                'system' => 'phone',
                'value' => config('hospital.phone', ''),
                'use' => 'work'
            ],
            [
                'system' => 'email',
                'value' => config('hospital.email', ''),
                'use' => 'work'
            ],
            [
                'system' => 'url',
                'value' => config('hospital.website', ''),
                'use' => 'work'
            ],
        ];

        $payload['address'] = [
            [
                'use' => 'work',
                'type' => 'both',
                'line' => [
                    config('hospital.address', '')
                ],
                'city' => config('hospital.city', ''),
                'postalCode' => config('hospital.postal_code', ''),
                'country' => 'ID',
                'extension' => [
                    [
                        'url' => FhirDictionary::KEMKES_SD_ADM_CODE,
                        'extension' => [
                            [
                                'url' => 'province',
                                'valueCode' => config('hospital.propinsi', '')
                            ],
                            [
                                'url' => 'city',
                                'valueCode' => config('hospital.kabupaten', '')
                            ],
                            [
                                'url' => 'district',
                                'valueCode' => config('hospital.kecamatan', '')
                            ],
                            [
                                'url' => 'village',
                                'valueCode' => config('hospital.kelurahan', '')
                            ],
                        ]
                    ]
                ]
            ]
        ];

        $payload['partOf'] = [
            'reference' => "Organization/" . $this->getOrganizationId(),
        ];

        return $this->update($id, $payload);
    }
}
