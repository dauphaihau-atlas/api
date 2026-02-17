<?php

namespace App\Core\Application\DTOs;

use DateTimeImmutable;

readonly class AuthUserDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public DateTimeImmutable $createdAt
    ) {}
}
