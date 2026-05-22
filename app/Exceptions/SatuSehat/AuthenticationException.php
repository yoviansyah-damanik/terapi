<?php

namespace App\Exceptions\SatuSehat;

/**
 * Exception untuk error autentikasi OAuth SatuSehat
 */
class AuthenticationException extends SatuSehatException
{
    public function __construct(
        string $message = 'Gagal autentikasi ke SatuSehat API',
        int $code = 401,
        ?\Throwable $previous = null,
        ?array $responseBody = null,
    ) {
        parent::__construct($message, $code, $previous, $responseBody);
    }

    public static function invalidCredentials(): self
    {
        return new self('Client ID atau Client Secret tidak valid');
    }

    public static function tokenExpired(): self
    {
        return new self('Token sudah kadaluarsa');
    }

    public static function missingCredentials(): self
    {
        return new self('Client ID atau Client Secret belum dikonfigurasi');
    }
}
