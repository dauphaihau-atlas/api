<?php

namespace App\Core\Application\UseCases\User\CreateUser;

use DateTimeImmutable;

class CreateUserResponse
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly DateTimeImmutable $createdAt
    ) {}
}
