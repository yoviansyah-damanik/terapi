<?php

namespace App\Services\SatuSehat\Resources;

use App\Models\Mapping\HealthcareServiceMap;
use App\Models\Mapping\HsServiceItem;
use App\Models\SatuSehat\SatuSehatLocation;
use App\Services\SatuSehat\DTO\FhirResponse;
use App\Services\SatuSehat\FhirDictionary;
use App\Services\SatuSehat\SatuSehatBaseService;

class HealthcareServiceService extends SatuSehatBaseService
{
    protected function getResourceType(): string
    {
        return 'HealthcareService';
    }

    public function searchByName(string $name): FhirResponse
    {
        return $this->search(['name' => $name]);
    }

    public function searchByOrganization(?string $organizationId = null): FhirResponse
    {
        return $this->search([
            'organization' => $organizationId ?? $this->getOrganizationId(),
        ]);
    }

    public function createHealthcareService(string $name, string $identifier, string $resourceType = 'polyclinic'): FhirResponse
    {
        return $this->create($this->buildPayload($name, $identifier, true, $resourceType));
    }

    public function updateHealthcareService(
        string $id,
        string $name,
        string $identifier,
        bool $active = true,
        string $resourceType = 'polyclinic',
    ): FhirResponse {
        return $this->update($id, $this->buildPayload($name, $identifier, $active, $resourceType));
    }

    private function buildPayload(string $name, string $identifier, bool $active, string $resourceType = 'polyclinic'): array
    {
        $label        = $resourceType === 'ward' ? 'Bangsal' : 'Poliklinik';
        $locationType = $resourceType === 'ward' ? 'ranap' : 'ralan';

        $serviceMap = HealthcareServiceMap::where('type', $resourceType)->where('local_code', $identifier)->first();

        $items       = HsServiceItem::where('type', $resourceType)->where('local_code', $identifier)->get()->groupBy('item_type');
        $categories  = $items->get('service-category', collect());
        $types       = $items->get('service-type', collect());
        $specialties = $items->get('clinical-speciality', collect());
        $programs    = $items->get('program', collect());

        if ($categories->isEmpty()) {
            throw new \RuntimeException("{$label} '{$identifier}' belum memiliki mapping Service Category. Lakukan mapping di Local Terminology → Healthcare Service terlebih dahulu.");
        }
        if ($types->isEmpty()) {
            throw new \RuntimeException("{$label} '{$identifier}' belum memiliki mapping Service Type. Lakukan mapping di Local Terminology → Healthcare Service terlebih dahulu.");
        }
        if ($specialties->isEmpty()) {
            throw new \RuntimeException("{$label} '{$identifier}' belum memiliki mapping Clinical Speciality. Lakukan mapping di Local Terminology → Healthcare Service terlebih dahulu.");
        }
        if (!$serviceMap?->physical_type_code) {
            throw new \RuntimeException("{$label} '{$identifier}' belum memiliki mapping Physical Type. Lakukan mapping di Local Terminology → Healthcare Service terlebih dahulu.");
        }

        $location = SatuSehatLocation::where('type', $locationType)->where('identifier', $identifier)->first();
        if (!$location || !$location->ihs_number) {
            throw new \RuntimeException("{$label} '{$identifier}' belum memiliki Location di Satu Sehat. Kirim Location terlebih dahulu.");
        }

        $payload = [
            'active' => $active,
            'identifier' => [
                [
                    'use'    => 'official',
                    'system' => FhirDictionary::KEMKES_SYS_HEALTHCARE_SVC . '/' . $this->getOrganizationId(),
                    'value'  => $identifier,
                ],
            ],
            'providedBy' => [
                'reference' => 'Organization/' . $this->getOrganizationId(),
            ],
            'name' => $name,
        ];

        // Untuk polyclinic tambahkan kode BPJS poli; untuk ward skip entry ini
        $payload['type'] = $resourceType === 'polyclinic' ? [
            [
                'coding' => [
                    [
                        'system'  => FhirDictionary::KEMKES_SYS_BPJS_POLI,
                        'code'    => $identifier,
                        'display' => $name,
                    ],
                ],
            ],
        ] : [];

        foreach ($categories as $cat) {
            $payload['type'][] = [
                'coding' => [
                    [
                        'system'  => $cat->system_display ?? FhirDictionary::HL7_CS_SERVICE_CATEGORY,
                        'code'    => $cat->system_code,
                        'display' => $cat->system_term,
                    ],
                ],
            ];
        }
        foreach ($types as $type) {
            $payload['type'][] = [
                'coding' => [
                    [
                        'system'  => $type->system_display ?? FhirDictionary::HL7_CS_SERVICE_TYPE,
                        'code'    => $type->system_code,
                        'display' => $type->system_term,
                    ],
                ],
            ];
        }

        $payload['specialty'] = $specialties->map(fn($s) => [
            'coding' => [
                [
                    'system'  => $s->system_display ?? FhirDictionary::KEMKES_CS_SPECIALITY,
                    'code'    => $s->system_code,
                    'display' => $s->system_term,
                ],
            ],
        ])->values()->toArray();

        if ($programs->isNotEmpty()) {
            $payload['program'] = $programs->map(fn($p) => [
                'coding' => [
                    [
                        'system'  => $p->system_display ?? FhirDictionary::KEMKES_CS_PROGRAM,
                        'code'    => $p->system_code,
                        'display' => $p->system_term,
                    ],
                ],
            ])->values()->toArray();
        }

        $payload['location'] = [
            [
                'reference' => 'Location/' . $location->ihs_number,
                'display'   => $location->name,
            ],
        ];

        $payload['telecom'] = [
            ['system' => 'phone', 'value' => config('hospital.phone', ''), 'use' => 'work'],
            ['system' => 'email', 'value' => config('hospital.email', ''), 'use' => 'work'],
            ['system' => 'url',   'value' => config('hospital.website', ''), 'use' => 'work'],
        ];

        return $payload;
    }
}
