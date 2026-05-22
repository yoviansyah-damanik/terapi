<?php

namespace App\Services\SatuSehat\Resources;

use App\Services\SatuSehat\DTO\FhirResponse;
use App\Services\SatuSehat\FhirDictionary;
use App\Services\SatuSehat\SatuSehatBaseService;

class ImagingStudyService extends SatuSehatBaseService
{
    protected function getResourceType(): string
    {
        return 'ImagingStudy';
    }

    public function searchBySubject(string $patientId): FhirResponse
    {
        return $this->search(['subject' => $patientId]);
    }

    public function searchByEncounter(string $encounterId): FhirResponse
    {
        return $this->search(['encounter' => $encounterId]);
    }

    public function searchByIdentifier(string $identifierValue): FhirResponse
    {
        $system = FhirDictionary::imagingSystem($this->getOrganizationId());
        return $this->search(['identifier' => $system . '|' . $identifierValue]);
    }

    /**
     * Buat ImagingStudy — representasi satu permintaan radiologi.
     *
     * @param  string       $patientId      IHS Patient
     * @param  string       $encounterId    IHS Encounter
     * @param  string       $performerId    IHS Practitioner
     * @param  string       $modalityCode   Kode DICOM Modality (CR, DX, CT, MR, dll.)
     * @param  string       $modalityDisplay Label modality
     * @param  string       $identifier     Identifier lokal unik
     * @param  string|null  $serviceRequestId IHS ServiceRequest
     * @param  string|null  $startedAt      effectiveDateTime (ISO 8601)
     * @param  string|null  $description    Deskripsi singkat pemeriksaan
     * @param  string|null  $bodySiteCode   SNOMED kode lokasi tubuh (opsional)
     * @param  string|null  $bodySiteDisplay Label lokasi tubuh
     */
    public function createImagingStudy(
        string $patientId,
        string $encounterId,
        string $performerId,
        string $modalityCode,
        string $modalityDisplay,
        string $identifier,
        ?string $serviceRequestId = null,
        ?string $startedAt = null,
        ?string $description = null,
        ?string $bodySiteCode = null,
        ?string $bodySiteDisplay = null,
    ): FhirResponse {
        $payload = [
            'status' => 'available',
            'subject' => ['reference' => "Patient/{$patientId}"],
            'encounter' => ['reference' => "Encounter/{$encounterId}"],
            'identifier' => [
                [
                    'system' => FhirDictionary::imagingSystem($this->getOrganizationId()),
                    'use' => 'official',
                    'value' => $identifier,
                ],
            ],
            'modality' => [
                [
                    'system' => FhirDictionary::DICOM_DCM,
                    'code' => $modalityCode,
                    'display' => $modalityDisplay,
                ],
            ],
            'referrer' => ['reference' => "Practitioner/{$performerId}"],
            'interpreter' => [['reference' => "Practitioner/{$performerId}"]],
            'series' => [
                [
                    'uid' => '1',
                    'modality' => [
                        'system' => FhirDictionary::DICOM_DCM,
                        'code' => $modalityCode,
                        'display' => $modalityDisplay,
                    ],
                    'performer' => [['actor' => ['reference' => "Practitioner/{$performerId}"]]],
                    'instance' => [
                        [
                            'uid' => '1.1',
                            'sopClass' => [
                                'system' => FhirDictionary::RFC_3986,
                                'code' => "urn:oid:1.2.840.10008.5.1.4.1.1.2",
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if ($startedAt) {
            $payload['started'] = $startedAt;
        }

        if ($description) {
            $payload['description'] = $description;
        }

        if ($serviceRequestId) {
            $payload['basedOn'] = [['reference' => "ServiceRequest/{$serviceRequestId}"]];
        }

        if ($bodySiteCode && $bodySiteDisplay) {
            $payload['series'][0]['bodySite'] = [
                'coding' => [
                    [
                        'system' => FhirDictionary::SNOMED,
                        'code' => $bodySiteCode,
                        'display' => $bodySiteDisplay,
                    ],
                ],
            ];
        }

        return $this->create($payload);
    }
}
