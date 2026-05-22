<?php

namespace App\Services\Bpjs\Erm\Resources;

use App\Constants\BpjsErmCodes;
use App\Helpers\ErmHelper;

class PractitionerBuilder extends BaseResourceBuilder
{
    public function build(): array
    {
        $dokter = $this->ctx->reg->dokter;
        $practitionerId = ErmHelper::getPractitionerId($dokter);

        $identifiers = [];

        if (!empty($dokter?->no_ijn_praktek)) {
            $identifiers[] = [
                'use' => 'official',
                'type' => [
                    'coding' => [BpjsErmCodes::CODING_ID_LICENSE],
                    'text' => 'Nomor SIP',
                ],
                'system' => BpjsErmCodes::SYSTEM_SIP,
                'value' => $dokter->no_ijn_praktek,
                'assigner' => ['display' => 'Kemenkes'],
            ];
        }

        $identifiers[] = [
            'use' => 'official',
            'type' => [
                'coding' => [BpjsErmCodes::CODING_ID_NIK],
                'text' => 'Nomor KTP/NIK',
            ],
            'system' => BpjsErmCodes::SYSTEM_NIK,
            'value' => $practitionerId,
            'assigner' => ['display' => 'KEMENDAGRI'],
        ];

        return [
            'resource' => [
                'resourceType' => 'Practitioner',
                'id' => $this->ctx->idPractitioner,
                'identifier' => $identifiers,
                'name' => [
                    ['use' => 'official', 'text' => $dokter?->nm_dokter ?? ''],
                ],
                'telecom' => [
                    ['system' => 'phone', 'value' => $dokter?->no_telp ?? '-', 'use' => 'work'],
                    ['system' => 'email', 'value' => '-', 'use' => 'work'],
                    ['system' => 'fax', 'value' => '-', 'use' => 'work'],
                ],
                'address' => [
                    [
                        'use' => 'home',
                        'type' => 'physical',
                        'text' => $dokter?->almt_tgl ?? '',
                        'line' => [$dokter?->almt_tgl ?? ''],
                        'city' => $dokter?->pegawai?->kota ?? '',
                        'district' => '-',
                        'state' => '-',
                        'postalCode' => '-',
                        'country' => 'INDONESIA',
                    ],
                ],
                'gender' => $dokter?->jk === 'L' ? 'male' : 'female',
                'birthDate' => $dokter?->tgl_lahir?->format('Y-m-d') ?? '',
            ],
        ];
    }
}
