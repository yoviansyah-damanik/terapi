<?php

namespace App\Services\SatuSehat\DTO;

use Illuminate\Http\Client\Response;

/**
 * DTO untuk menyimpan response dari FHIR API SatuSehat
 */
class FhirResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly int $statusCode,
        public readonly ?array $data = null,
        public readonly ?string $error = null,
        public readonly ?string $resourceType = null,
        public readonly ?string $resourceId = null,
    ) {}

    public static function fromHttpResponse(Response $response): self
    {
        $body = $response->json() ?? [];
        $resourceType = $body['resourceType'] ?? null;
        $resourceId = $body['id'] ?? null;

        $error = null;
        if (! $response->successful()) {
            $error = self::extractError($body);
        }

        return new self(
            success: $response->successful(),
            statusCode: $response->status(),
            data: $body,
            error: $error,
            resourceType: $resourceType,
            resourceId: $resourceId,
        );
    }

    public static function fromException(\Throwable $e, int $statusCode = 500): self
    {
        return new self(
            success: false,
            statusCode: $statusCode,
            data: null,
            error: $e->getMessage(),
        );
    }

    private static function extractError(array $body): ?string
    {
        if (isset($body['issue'][0]['diagnostics'])) {
            return $body['issue'][0]['diagnostics'];
        }

        if (isset($body['issue'][0]['details']['text'])) {
            return $body['issue'][0]['details']['text'];
        }

        if (isset($body['error_description'])) {
            return $body['error_description'];
        }

        if (isset($body['error'])) {
            return $body['error'];
        }

        return null;
    }

    public function isBundle(): bool
    {
        return $this->resourceType === 'Bundle';
    }

    public function isOperationOutcome(): bool
    {
        return $this->resourceType === 'OperationOutcome';
    }

    public function getTotal(): int
    {
        if (! $this->isBundle()) {
            return 0;
        }

        return $this->data['total'] ?? count($this->getEntries());
    }

    public function getEntries(): array
    {
        if (! $this->isBundle()) {
            return [];
        }

        return $this->data['entry'] ?? [];
    }

    public function getFirstEntry(): ?array
    {
        $entries = $this->getEntries();

        return $entries[0]['resource'] ?? null;
    }

    public function getResources(): array
    {
        return array_map(
            fn ($entry) => $entry['resource'] ?? $entry,
            $this->getEntries()
        );
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'status_code' => $this->statusCode,
            'data' => $this->data,
            'error' => $this->error,
            'resource_type' => $this->resourceType,
            'resource_id' => $this->resourceId,
        ];
    }
}
