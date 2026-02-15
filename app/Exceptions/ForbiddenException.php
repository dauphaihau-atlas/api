<?php

namespace App\Exceptions;

class ForbiddenException extends ApiException
{
    public function __construct(string $message = 'Forbidden', ?string $errorCode = 'FORBIDDEN', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, 403, $errorCode, $code, $previous);
    }
}
