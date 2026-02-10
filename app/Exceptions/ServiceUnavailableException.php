<?php

namespace App\Exceptions;

class ServiceUnavailableException extends ApiException
{
    public function __construct(string $message = 'Service temporarily unavailable', ?string $errorCode = 'SERVICE_UNAVAILABLE', ?array $context = null, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, 503, $errorCode, $code, $previous, $context);
    }
}
