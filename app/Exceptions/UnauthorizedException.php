<?php

namespace App\Exceptions;

class UnauthorizedException extends ApiException
{
    public function __construct(string $message = 'Unauthorized', ?string $errorCode = 'UNAUTHORIZED', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, 401, $errorCode, $code, $previous);
    }
}
