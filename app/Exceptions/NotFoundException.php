<?php

namespace App\Exceptions;

class NotFoundException extends ApiException
{
    public function __construct(string $message = 'Resource not found', ?string $errorCode = 'NOT_FOUND', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, 404, $errorCode, $code, $previous);
    }
}
