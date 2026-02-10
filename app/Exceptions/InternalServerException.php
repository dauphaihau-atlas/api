<?php

namespace App\Exceptions;

class InternalServerException extends ApiException
{
    public function __construct(string $message = 'An internal server error occurred', ?string $errorCode = 'INTERNAL_ERROR', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, 500, $errorCode, $code, $previous);
    }
}
