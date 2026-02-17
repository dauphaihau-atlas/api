<?php

namespace App\Exceptions;

use Throwable;

class ConflictException extends ApiException
{
    public function __construct(string $message = 'Resource already exists', ?string $errorCode = 'CONFLICT', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, 409, $errorCode, $code, $previous);
    }
}
