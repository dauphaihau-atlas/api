<?php

namespace App\Core\Domain\Exceptions;

class InvalidEmailException extends \DomainException
{
    public function __construct(string $message = 'Invalid email address', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
