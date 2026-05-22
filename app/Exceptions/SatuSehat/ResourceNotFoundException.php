<?php

namespace App\Exceptions\SatuSehat;

/**
 * Exception untuk resource FHIR yang tidak ditemukan
 */
class ResourceNotFoundException extends SatuSehatException
{
    protected string $resourceType;

    protected string $resourceId;

    public function __construct(
        string $resourceType,
        string $resourceId,
        ?array $responseBody = null,
    ) {
        $this->resourceType = $resourceType;
        $this->resourceId = $resourceId;

        parent::__construct(
            message: "{$resourceType} dengan ID {$resourceId} tidak ditemukan",
            code: 404,
            responseBody: $responseBody,
        );
    }

    public function getResourceType(): string
    {
        return $this->resourceType;
    }

    public function getResourceId(): string
    {
        return $this->resourceId;
    }
}
