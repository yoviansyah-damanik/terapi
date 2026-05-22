<?php

namespace App\Services\SatuSehat\Concerns;

use App\Exceptions\SatuSehat\ResourceNotFoundException;
use App\Exceptions\SatuSehat\SatuSehatException;
use App\Models\SatuSehat\SatuSehatLog;
use App\Services\SatuSehat\DTO\FhirResponse;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Trait untuk operasi CRUD FHIR
 */
trait HasFhirOperations
{
    protected ?array $lastPayload = null;
    protected ?FhirResponse $lastResponse = null;

    abstract protected function getResourceType(): string;

    abstract protected function getAccessToken(): string;

    abstract protected function getBaseUrl(): string;

    abstract protected function getTimeout(): int;

    protected function client(): PendingRequest
    {
        return Http::withToken($this->getAccessToken())
            ->acceptJson()
            ->contentType('application/json')
            ->timeout($this->getTimeout());
    }

    protected function buildUrl(string $path = ''): string
    {
        $base = $this->getFhirBaseUrl() . '/' . $this->getResourceType();

        return $path ? "{$base}/{$path}" : $base;
    }

    public function find(string $id): FhirResponse
    {
        $startTime = microtime(true);
        $endpoint = $this->buildUrl($id);

        try {
            $response = $this->client()->get($endpoint);
            $result = FhirResponse::fromHttpResponse($response);
            $responseTime = (microtime(true) - $startTime) * 1000;

            if ($response->status() === 404) {
                $this->saveLog('find', 'GET', $endpoint, null, null, $result, $responseTime);
                throw new ResourceNotFoundException(
                    $this->getResourceType(),
                    $id,
                    $response->json(),
                );
            }

            $this->saveLog('find', 'GET', $endpoint, null, null, $result, $responseTime);
            $this->logRequest('GET', $endpoint, $result);

            return $result;
        } catch (ResourceNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            $responseTime = (microtime(true) - $startTime) * 1000;
            $this->saveLogError('find', 'GET', $endpoint, null, null, $e, $responseTime);
            $this->logError('find', $e);

            return FhirResponse::fromException($e);
        }
    }

    public function search(array $params = []): FhirResponse
    {
        $startTime = microtime(true);
        $endpoint = $this->buildUrl();

        try {
            $response = $this->client()->get($endpoint, $params);
            $result = FhirResponse::fromHttpResponse($response);
            $responseTime = (microtime(true) - $startTime) * 1000;

            $this->saveLog('search', 'GET', $endpoint, $params, null, $result, $responseTime);
            $this->logRequest('GET', $endpoint . '?' . http_build_query($params), $result);

            return $result;
        } catch (\Exception $e) {
            $responseTime = (microtime(true) - $startTime) * 1000;
            $this->saveLogError('search', 'GET', $endpoint, $params, null, $e, $responseTime);
            $this->logError('search', $e);

            return FhirResponse::fromException($e);
        }
    }

    public function create(array $payload): FhirResponse
    {
        $startTime = microtime(true);
        $endpoint = $this->buildUrl();

        try {
            $payload['resourceType'] = $this->getResourceType();

            $response = $this->client()->post($endpoint, $payload);
            $result = FhirResponse::fromHttpResponse($response);
            $responseTime = (microtime(true) - $startTime) * 1000;

            $this->saveLog('create', 'POST', $endpoint, null, $payload, $result, $responseTime);
            $this->logRequest('POST', $endpoint, $result);

            $this->lastPayload = $payload;
            $this->lastResponse = $result;

            if (!$result->success) {
                throw SatuSehatException::fromResponse($result->statusCode, $result->data ?? []);
            }

            return $result;
        } catch (SatuSehatException $e) {
            $this->lastResponse = FhirResponse::fromException($e, $e->getCode() ?: 500);
            throw $e;
        } catch (\Exception $e) {
            $responseTime = (microtime(true) - $startTime) * 1000;
            $this->saveLogError('create', 'POST', $endpoint, null, $payload, $e, $responseTime);
            $this->logError('create', $e);

            $this->lastResponse = FhirResponse::fromException($e);
            return $this->lastResponse;
        }
    }

    public function getLastPayload(): ?array
    {
        return $this->lastPayload;
    }

    public function getLastResponse(): ?FhirResponse
    {
        return $this->lastResponse;
    }

    public function update(string $id, array $payload): FhirResponse
    {
        $startTime = microtime(true);
        $endpoint = $this->buildUrl($id);

        try {
            $payload['resourceType'] = $this->getResourceType();
            $payload['id'] = $id;

            $response = $this->client()->put($endpoint, $payload);
            $result = FhirResponse::fromHttpResponse($response);
            $responseTime = (microtime(true) - $startTime) * 1000;

            $this->saveLog('update', 'PUT', $endpoint, null, $payload, $result, $responseTime);
            $this->logRequest('PUT', $endpoint, $result);

            if ($response->status() === 404) {
                throw new ResourceNotFoundException(
                    $this->getResourceType(),
                    $id,
                    $response->json(),
                );
            }

            return $result;
        } catch (ResourceNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            $responseTime = (microtime(true) - $startTime) * 1000;
            $this->saveLogError('update', 'PUT', $endpoint, null, $payload, $e, $responseTime);
            $this->logError('update', $e);

            return FhirResponse::fromException($e);
        }
    }

    public function patch(string $id, array $operations): FhirResponse
    {
        $startTime = microtime(true);
        $endpoint = $this->buildUrl($id);

        try {
            $response = $this->client()
                ->contentType('application/json-patch+json')
                ->patch($endpoint, $operations);

            $result = FhirResponse::fromHttpResponse($response);
            $responseTime = (microtime(true) - $startTime) * 1000;

            $this->saveLog('patch', 'PATCH', $endpoint, null, $operations, $result, $responseTime);
            $this->logRequest('PATCH', $endpoint, $result);

            if ($response->status() === 404) {
                throw new ResourceNotFoundException(
                    $this->getResourceType(),
                    $id,
                    $response->json(),
                );
            }

            return $result;
        } catch (ResourceNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            $responseTime = (microtime(true) - $startTime) * 1000;
            $this->saveLogError('patch', 'PATCH', $endpoint, null, $operations, $e, $responseTime);
            $this->logError('patch', $e);

            return FhirResponse::fromException($e);
        }
    }

    public function delete(string $id): FhirResponse
    {
        $startTime = microtime(true);
        $endpoint = $this->buildUrl($id);

        try {
            $response = $this->client()->delete($endpoint);
            $result = FhirResponse::fromHttpResponse($response);
            $responseTime = (microtime(true) - $startTime) * 1000;

            $this->saveLog('delete', 'DELETE', $endpoint, null, null, $result, $responseTime);
            $this->logRequest('DELETE', $endpoint, $result);

            return $result;
        } catch (\Exception $e) {
            $responseTime = (microtime(true) - $startTime) * 1000;
            $this->saveLogError('delete', 'DELETE', $endpoint, null, null, $e, $responseTime);
            $this->logError('delete', $e);

            return FhirResponse::fromException($e);
        }
    }

    protected function saveLog(
        string $action,
        string $method,
        string $endpoint,
        ?array $requestParams,
        ?array $requestBody,
        FhirResponse $result,
        float $responseTime,
    ): void {
        try {
            // Ekstrak NIK dari parameter jika ada
            $patientNik = null;
            if ($requestParams) {
                $identifier = $requestParams['identifier'] ?? '';
                if (str_contains($identifier, 'nik|')) {
                    $patientNik = str_replace('https://fhir.kemkes.go.id/id/nik|', '', $identifier);
                }
            }

            SatuSehatLog::log(
                resourceType: $this->getResourceType(),
                action: $action,
                method: $method,
                endpoint: $endpoint,
                requestParams: $requestParams,
                requestBody: $requestBody,
                responseStatus: $result->statusCode,
                responseBody: $result->data,
                ihsNumber: $result->resourceId,
                patientNik: $patientNik,
                responseTime: $responseTime,
                isSuccess: $result->success,
                errorMessage: $result->error,
            );
        } catch (\Exception $e) {
            Log::warning('Failed to save SatuSehat log: ' . $e->getMessage());
        }
    }

    protected function saveLogError(
        string $action,
        string $method,
        string $endpoint,
        ?array $requestParams,
        ?array $requestBody,
        \Throwable $exception,
        float $responseTime,
    ): void {
        try {
            SatuSehatLog::log(
                resourceType: $this->getResourceType(),
                action: $action,
                method: $method,
                endpoint: $endpoint,
                requestParams: $requestParams,
                requestBody: $requestBody,
                responseStatus: null,
                responseBody: null,
                ihsNumber: null,
                patientNik: null,
                responseTime: $responseTime,
                isSuccess: false,
                errorMessage: $exception->getMessage(),
            );
        } catch (\Exception $e) {
            Log::warning('Failed to save SatuSehat error log: ' . $e->getMessage());
        }
    }

    protected function logRequest(string $method, string $url, FhirResponse $result): void
    {
        if (!config('satusehat.debug')) {
            return;
        }

        $context = [
            'method' => $method,
            'url' => $url,
            'status' => $result->statusCode,
            'success' => $result->success,
        ];

        if ($result->resourceId) {
            $context['resource_id'] = $result->resourceId;
        }

        if ($result->error) {
            $context['error'] = $result->error;
        }

        Log::debug("SatuSehat API: {$method} {$this->getResourceType()}", $context);
    }

    protected function logError(string $operation, \Throwable $e): void
    {
        Log::error("SatuSehat {$this->getResourceType()}::{$operation} Error: {$e->getMessage()}", [
            'resource_type' => $this->getResourceType(),
            'operation' => $operation,
            'exception' => get_class($e),
        ]);
    }
}
