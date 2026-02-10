<?php

namespace App\Exceptions;

class ValidationException extends ApiException
{
    public function __construct(string $message = 'Validation failed', ?string $errorCode = 'VALIDATION_ERROR', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, 422, $errorCode, $code, $previous);
    }
}
