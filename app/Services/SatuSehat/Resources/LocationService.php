<?php

namespace App\Services\SatuSehat\Resources;

use App\Services\SatuSehat\DTO\FhirResponse;
use App\Services\SatuSehat\FhirDictionary;
use App\Services\SatuSehat\SatuSehatBaseService;

class LocationService extends SatuSehatBaseService
{
    protected function getResourceType(): string
    {
        return 'Location';
    }

    public function searchByOrganization(?string $organizationId = null): FhirResponse
    {
        return $this->search([
            'organization' => $organizationId ?? $this->getOrganizationId(),
        ]);
    }

    public function searchByName(string $name): FhirResponse
    {
        return $this->search([
            'name' => $name,
        ]);
    }

    public function searchByIdentifier(string $identifier): FhirResponse
    {
        return $this->search([
            'identifier' => FhirDictionary::locationSystem($this->getOrganizationId()) . "|{$identifier}",
        ]);
    }

    public function createLocation(
        string $name,
        string $identifier,
        string $longitude,
        string $latitude,
        string $altitude,
        ?string $managingOrganizationId = null,
        string $physicalTypeCode = 'ro',
        string $physicalTypeDisplay = 'Room',
    ): FhirResponse {
        $payload = [
            'status' => 'active',
            'mode' => 'instance',
            'name' => $name,
            'description' => $name,
            'identifier' => [
                [
                    'system' => FhirDictionary::locationSystem($this->getOrganizationId()),
                    'value' => $identifier,
                ],
            ],
            'managingOrganization' => [
                'reference' => 'Organization/' . ($managingOrganizationId ?? $this->getOrganizationId()),
            ],
        ];

        $payload['physicalType'] = [
            'coding' => [
                [
                    'system' => FhirDictionary::HL7_CS_LOC_PHYSICAL_TYPE,
                    'code' => $physicalTypeCode,
                    'display' => $physicalTypeDisplay,
                ]
            ]
        ];

        $payload['position'] = [
            'longitude' => (float) $longitude,
            'latitude' => (float) $latitude,
            'altitude' => (float) $altitude,
        ];

        $payload['telecom'] = [
            ['system' => 'phone', 'value' => config('hospital.phone', ''), 'use' => 'work'],
            ['system' => 'email', 'value' => config('hospital.email', ''), 'use' => 'work'],
            ['system' => 'url', 'value' => config('hospital.website', ''), 'use' => 'work'],
        ];

        $payload['address'] = [
            'use' => 'work',
            'type' => 'both',
            'line' => [config('hospital.address', '')],
            'city' => config('hospital.city', ''),
            'postalCode' => config('hospital.postal_code', ''),
            'country' => 'ID',
            'extension' => [[
                'url' => FhirDictionary::KEMKES_SD_ADM_CODE,
                'extension' => [
                    ['url' => 'province', 'valueCode' => config('hospital.propinsi', '')],
                    ['url' => 'city', 'valueCode' => config('hospital.kabupaten', '')],
                    ['url' => 'district', 'valueCode' => config('hospital.kecamatan', '')],
                    ['url' => 'village', 'valueCode' => config('hospital.kelurahan', '')],
                ],
            ]],
        ];

        return $this->create($payload);
    }

    public function updateLocation(
        string $id,
        string $name,
        string $identifier,
        string $longitude,
        string $latitude,
        string $altitude,
        string $status = 'active',
        ?string $managingOrganizationId = null,
        string $physicalTypeCode = 'ro',
        string $physicalTypeDisplay = 'Room',
    ): FhirResponse {
        $payload = [
            'status' => $status,
            'mode' => 'instance',
            'name' => $name,
            'description' => $name,
            'identifier' => [
                [
                    'system' => FhirDictionary::locationSystem($this->getOrganizationId()),
                    'value' => $identifier,
                ],
            ],
            'managingOrganization' => [
                'reference' => 'Organization/' . ($managingOrganizationId ?? $this->getOrganizationId()),
            ],
        ];

        $payload['physicalType'] = [
            'coding' => [
                [
                    'system' => FhirDictionary::HL7_CS_LOC_PHYSICAL_TYPE,
                    'code' => $physicalTypeCode,
                    'display' => $physicalTypeDisplay,
                ]
            ]
        ];

        $payload['position'] = [
            'longitude' => (float) $longitude,
            'latitude' => (float) $latitude,
            'altitude' => (float) $altitude,
        ];

        $payload['telecom'] = [
            ['system' => 'phone', 'value' => config('hospital.phone', ''), 'use' => 'work'],
            ['system' => 'email', 'value' => config('hospital.email', ''), 'use' => 'work'],
            ['system' => 'url', 'value' => config('hospital.website', ''), 'use' => 'work'],
        ];

        $payload['address'] = [
            'use' => 'work',
            'type' => 'both',
            'line' => [config('hospital.address', '')],
            'city' => config('hospital.city', ''),
            'postalCode' => config('hospital.postal_code', ''),
            'country' => 'ID',
            'extension' => [[
                'url' => FhirDictionary::KEMKES_SD_ADM_CODE,
                'extension' => [
                    ['url' => 'province', 'valueCode' => config('hospital.propinsi', '')],
                    ['url' => 'city', 'valueCode' => config('hospital.kabupaten', '')],
                    ['url' => 'district', 'valueCode' => config('hospital.kecamatan', '')],
                    ['url' => 'village', 'valueCode' => config('hospital.kelurahan', '')],
                ],
            ]],
        ];

        return $this->update($id, $payload);
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
