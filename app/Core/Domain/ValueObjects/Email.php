<?php

namespace App\Core\Domain\ValueObjects;

use App\Core\Domain\Exceptions\InvalidEmailException;

class Email
{
    public function __construct(
        private readonly string $value
    ) {
        if ($value === '' || ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidEmailException("Invalid email address: {$value}");
        }
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(Email $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
