<?php

namespace App\Services\SatuSehat;

use App\Helpers\ConfigurationHelper;
use App\Models\SatuSehat\SatuSehatOrganization;
use App\Models\SatuSehat\SatuSehatLocation;
use App\Services\SatuSehat\Concerns\HasAuthentication;
use App\Services\SatuSehat\Concerns\HasFhirOperations;

/**
 * Base class untuk semua FHIR resource services SatuSehat
 */
abstract class SatuSehatBaseService
{
    use HasAuthentication;
    use HasFhirOperations;

    abstract protected function getResourceType(): string;

    protected function getOrganizationId(): string
    {
        return ConfigurationHelper::get('satusehat.organization_id') ?? config('satusehat.organization_id', '');
    }

    protected function buildPharmacyOrganizationReference(): array
    {
        return [
            'reference' => 'Organization/' . SatuSehatOrganization::where('identifier', 'FAR')->first()->ihs_number,
        ];
    }

    protected function buildLaboratoryOrganizationReference(): array
    {
        return [
            'reference' => 'Organization/' . SatuSehatOrganization::where('identifier', 'LAB')->first()->ihs_number,
        ];
    }

    protected function buildRadiologyOrganizationReference(): array
    {
        return [
            'reference' => 'Organization/' . SatuSehatOrganization::where('identifier', 'RAD')->first()->ihs_number,
        ];
    }

    protected function buildOrganizationReference(): array
    {
        return [
            'reference' => 'Organization/' . $this->getOrganizationId(),
        ];
    }

    protected function buildPharmacyLocationReference(): array
    {
        $ihs = SatuSehatLocation::where('type', 'apotek')->first()?->ihs_number;
        return $ihs ? ['reference' => "Location/{$ihs}"] : [];
    }

    protected function buildLaboratoryLocationReference(): array
    {
        $ihs = SatuSehatLocation::where('type', 'lab')->first()?->ihs_number;
        return $ihs ? ['reference' => "Location/{$ihs}"] : [];
    }

    protected function buildRadiologyLocationReference(): array
    {
        $ihs = SatuSehatLocation::where('type', 'rad')->first()?->ihs_number;
        return $ihs ? ['reference' => "Location/{$ihs}"] : [];
    }
}
