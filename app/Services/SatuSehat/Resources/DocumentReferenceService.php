<?php

namespace App\Services\SatuSehat\Resources;

use App\Services\SatuSehat\DTO\FhirResponse;
use App\Services\SatuSehat\FhirDictionary;
use App\Services\SatuSehat\SatuSehatBaseService;

class DocumentReferenceService extends SatuSehatBaseService
{
    protected function getResourceType(): string
    {
        return 'DocumentReference';
    }

    public function searchByEncounter(string $encounterId): FhirResponse
    {
        return $this->search(['encounter' => $encounterId]);
    }

    /**
     * Kirim DocumentReference untuk satu resep farmasi.
     *
     * @param array       $identifiers    [['system'=>..., 'use'=>...(opsional), 'value'=>...], ...]
     * @param array       $contents       [['title'=>..., 'url'=>..., 'format_code'=>..., 'format_display'=>...], ...]
     * @param array       $relatedRefs    ["MedicationRequest/{ihs}", ...]
     */
    public function createPrescriptionDocumentReference(
        array $identifiers,
        string $patientId,
        string $patientDisplay,
        string $encounterId,
        string $authorId,
        string $authorDisplay,
        array $contents,
        array $relatedRefs,
        ?string $description = null,
        ?string $date = null,
    ): FhirResponse {
        $payload = [
            'status'    => 'current',
            'docStatus' => 'final',
            'type'      => ['coding' => [[
                'system'  => FhirDictionary::LOINC,
                'code'    => '57833-6',
                'display' => 'Prescription for medication',
            ]]],
            'category'  => [['coding' => [[
                'system'  => FhirDictionary::LOINC,
                'code'    => '18776-5',
                'display' => 'Plan of care note',
            ]]]],
            'subject'   => ['reference' => "Patient/{$patientId}", 'display' => $patientDisplay],
            'date'      => $date ?? now()->utc()->toIso8601String(),
            'author'    => [['reference' => "Practitioner/{$authorId}", 'display' => $authorDisplay]],
            'custodian' => $this->buildOrganizationReference(),
            'content'   => array_map(fn($c) => [
                'attachment' => ['title' => $c['title'], 'url' => $c['url']],
                'format'     => [
                    'system'  => FhirDictionary::KEMKES_CS_DOCUMENT_FORMAT,
                    'code'    => $c['format_code'],
                    'display' => $c['format_display'],
                ],
            ], $contents),
            'context'   => [
                'encounter' => [['reference' => "Encounter/{$encounterId}"]],
                'related'   => array_map(fn($ref) => ['reference' => $ref], $relatedRefs),
            ],
        ];

        if (!empty($identifiers)) {
            $payload['identifier'] = $identifiers;
        }

        if ($description) {
            $payload['description'] = $description;
        }

        return $this->create($payload);
    }
}
