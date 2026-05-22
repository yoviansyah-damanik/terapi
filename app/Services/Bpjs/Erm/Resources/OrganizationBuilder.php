<?php

namespace App\Services\Bpjs\Erm\Resources;

use App\Constants\BpjsErmCodes;
use App\Helpers\ErmHelper;

class OrganizationBuilder extends BaseResourceBuilder
{
    public function build(): array
    {
        return $this->buildEntry($this->ctx->hospitalOrganization);
    }

    public function buildHospital(): array
    {
        return $this->buildEntry($this->ctx->hospitalOrganization);
    }

    public function buildForOrganization(array $organizationData): array
    {
        return $this->buildEntry($organizationData);
    }

    private function buildEntry(array $organizationData): array
    {
        return [
            'resource' => [
                'resourceType' => 'Organization',
                'id' => $organizationData['id'],
                'identifier' => [
                    [
                        'use' => 'official',
                        'system' => 'urn:oid:bpjs',
                        'value' => ErmHelper::getPpkRsBpjs()['code'],
                    ],
                    [
                        'use' => 'official',
                        'system' => 'urn:oid:kemkes',
                        'value' => ErmHelper::getPpkRsKemenkes()['code'],
                    ],
                ],
                'type' => [
                    [
                        'coding' => [
                            $organizationData['map'] ? [
                                'system' => $organizationData['map']['org_type_display'],
                                'code' => $organizationData['map']['org_type_code'],
                                'display' => $organizationData['map']['org_type_term'],
                            ] : BpjsErmCodes::CODING_ORG_TYPE_PROVIDER,
                        ],
                        'text' => $organizationData['map']
                            ? $organizationData['map']['org_type_term']
                            : BpjsErmCodes::CODING_ORG_TYPE_PROVIDER['display'],
                    ],
                ],
                'name' => $organizationData['display'],
                'alias' => [$organizationData['code']],
                'telecom' => [
                    ['system' => 'phone', 'value' => config('hospital.phone', '-'), 'use' => 'work'],
                ],
                'address' => [
                    [
                        'use' => 'work',
                        'text' => config('hospital.address', ''),
                        'line' => [config('hospital.address', '')],
                        'city' => config('hospital.city', ''),
                        'state' => config('hospital.province', ''),
                        'postalCode' => config('hospital.postal_code', ''),
                        'country' => config('hospital.country', 'ID'),
                    ],
                ],
                'contact' => [
                    [
                        'purpose' => ['coding' => [BpjsErmCodes::CODING_CONTACT_ADMIN]],
                        'telecom' => [
                            ['system' => 'phone', 'value' => config('hospital.phone', '-'), 'use' => 'work'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
