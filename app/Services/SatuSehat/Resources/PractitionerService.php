<?php

namespace App\Services\SatuSehat\Resources;

use App\Services\SatuSehat\DTO\FhirResponse;
use App\Services\SatuSehat\FhirDictionary;
use App\Services\SatuSehat\SatuSehatBaseService;

class PractitionerService extends SatuSehatBaseService
{
    protected function getResourceType(): string
    {
        return 'Practitioner';
    }

    public function searchByNik(string $nik): FhirResponse
    {
        return $this->search([
            'identifier' => FhirDictionary::KEMKES_NIK . "|{$nik}",
        ]);
    }

    public function findByNik(string $nik): ?array
    {
        $response = $this->searchByNik($nik);

        if (! $response->success || $response->getTotal() === 0) {
            return null;
        }

        return $response->getFirstEntry();
    }

    public function searchByName(string $name): FhirResponse
    {
        return $this->search([
            'name' => $name,
        ]);
    }

    public function searchByGender(string $gender): FhirResponse
    {
        return $this->search([
            'gender' => $gender,
        ]);
    }

    public function getIhsNumber(string $nik): ?string
    {
        $practitioner = $this->findByNik($nik);

        return $practitioner['id'] ?? null;
    }
}
