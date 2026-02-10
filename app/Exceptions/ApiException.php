<?php

namespace App\Exceptions;

abstract class ApiException extends \Exception
{
    public function __construct(
        string $message = '',
        protected int $httpStatusCode = 500,
        protected ?string $errorCode = null,
        int $code = 0,
        ?\Throwable $previous = null,
        protected ?array $context = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getContext(): ?array
    {
        return $this->context;
    }
}
