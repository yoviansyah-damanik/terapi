<?php

namespace App\Services\SatuSehat\Resources;

use App\Services\SatuSehat\DTO\FhirResponse;
use App\Services\SatuSehat\SatuSehatBaseService;

class QuestionnaireResponseService extends SatuSehatBaseService
{
    protected function getResourceType(): string
    {
        return 'QuestionnaireResponse';
    }

    public function searchByPatient(string $patientId): FhirResponse
    {
        return $this->search(['patient' => $patientId]);
    }

    /**
     * Kirim QuestionnaireResponse untuk Telaah Farmasi (Q0007).
     *
     * @param array $items Array of FHIR item groups (linkId, text, item[])
     */
    public function createResponse(
        string $questionnaire,
        string $patientId,
        string $patientDisplay,
        string $encounterId,
        string $authored,
        string $authorId,
        string $authorDisplay,
        array $items,
        string $status = 'completed',
    ): FhirResponse {
        $payload = [
            'questionnaire' => $questionnaire,
            'status' => $status,
            'subject' => [
                'reference' => "Patient/{$patientId}",
                'display' => $patientDisplay,
            ],
            'encounter' => [
                'reference' => "Encounter/{$encounterId}",
            ],
            'authored' => $authored,
            'author' => [
                'reference' => "Practitioner/{$authorId}",
                'display' => $authorDisplay,
            ],
            'source' => [
                'reference' => "Patient/{$patientId}",
            ],
            'item' => $items,
        ];

        return $this->create($payload);
    }
}
