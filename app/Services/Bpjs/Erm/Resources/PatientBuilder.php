<?php

namespace App\Services\Bpjs\Erm\Resources;

use App\Constants\BpjsErmCodes;

class PatientBuilder extends BaseResourceBuilder
{
    public function build(): array
    {
        $maritalCode = BpjsErmCodes::MAP_MARITAL_STATUS[$this->ctx->patientData['data']['stts_nikah']]['code'] ?? 'U';
        $maritalText = BpjsErmCodes::MAP_MARITAL_STATUS[$this->ctx->patientData['data']['stts_nikah']]['text'] ?? 'Unmarried';

        return [
            'resource' => [
                'resourceType' => 'Patient',
                'id' => $this->ctx->patientData['id'],
                'identifier' => [
                    [
                        'use' => 'usual',
                        'type' => [
                            'coding' => [BpjsErmCodes::CODING_ID_MEDICAL_RECORD],
                            'text' => 'Medical record number',
                        ],
                        'value' => $this->ctx->patientData['data']['no_rkm_medis'],
                        'assigner' => ['display' => config('hospital.name')],
                    ],
                    [
                        'use' => 'official',
                        'type' => [
                            'coding' => [BpjsErmCodes::CODING_ID_MEMBER_NUMBER],
                            'text' => 'Nomor Peserta BPJS',
                        ],
                        'value' => $this->ctx->patientData['data']['no_peserta'],
                        'assigner' => ['display' => 'BPJS KESEHATAN'],
                    ],
                    [
                        'use' => 'official',
                        'type' => [
                            'coding' => [BpjsErmCodes::CODING_ID_NIK],
                            'text' => 'Nomor KTP/NIK',
                        ],
                        'system' => BpjsErmCodes::SYSTEM_NIK,
                        'value' => $this->ctx->patientData['data']['no_ktp'],
                        'assigner' => ['display' => 'KEMENDAGRI'],
                    ],
                ],
                'active' => true,
                'name' => [
                    ['use' => 'official', 'text' => $this->ctx->patientData['data']['nm_pasien'] ?? ''],
                ],
                'maritalStatus' => [
                    'coding' => [
                        [
                            'system' => BpjsErmCodes::SYSTEM_V3_MARITAL_STATUS,
                            'code' => $maritalCode,
                            'display' => $maritalText,
                        ],
                    ],
                    'text' => $maritalText,
                ],
                'telecom' => [
                    ['system' => 'phone', 'value' => '', 'use' => 'work'],
                    ['system' => 'phone', 'value' => $this->ctx->patientData['data']['no_tlp'] ?? '-', 'use' => 'mobile'],
                    ['system' => 'phone', 'value' => '', 'use' => 'home'],
                ],
                'gender' => $this->ctx->patientData['data']['jk'] === 'L' ? 'male' : 'female',
                'birthDate' => \Carbon\Carbon::parse($this->ctx->patientData['data']['tgl_lahir'])->format('Y-m-d') ?? '',
                'deceasedBoolean' => false,
                'address' => [
                    [
                        'use' => 'home',
                        'type' => 'both',
                        'text' => $this->ctx->patientData['data']['alamat'] ?? '-',
                        'line' => [$this->ctx->patientData['data']['alamat'] ?? '-'],
                        'city' => $this->ctx->patientData['data']['nm_kec'] ?? '-',
                        'district' => $this->ctx->patientData['data']['nm_kec'] ?? '-',
                        'state' => $this->ctx->patientData['data']['nm_prop'] ?? '-',
                        'postalCode' => $this->ctx->patientData['data']['kd_pos'] ?? '-',
                    ],
                ],
                'managingOrganization' => [
                    'reference' => "Organization/{$this->ctx->unitOrganization['id']}",
                    'display' => $this->ctx->unitOrganization['display'],
                ],
            ],
        ];
    }
}
