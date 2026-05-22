<?php

namespace App\Exceptions\SatuSehat;

use Exception;

/**
 * Base exception untuk semua error SatuSehat API
 */
class SatuSehatException extends Exception
{
    protected ?array $responseBody;

    public function __construct(
        string $message = 'SatuSehat API Error',
        int $code = 0,
        ?\Throwable $previous = null,
        ?array $responseBody = null,
    ) {
        parent::__construct($message, $code, $previous);
        $this->responseBody = $responseBody;
    }

    public function getResponseBody(): ?array
    {
        return $this->responseBody;
    }

    public static function fromResponse(int $statusCode, array $body): self
    {
        $message = self::extractMessage($body) ?? "SatuSehat API Error (HTTP {$statusCode})";

        return new self($message, $statusCode, null, $body);
    }

    protected static function extractMessage(array $body): ?string
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
}
