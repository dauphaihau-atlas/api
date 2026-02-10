<?php

namespace App\Core\Application\UseCases\Auth\LoginUser;

class LoginUserResponse
{
    public function __construct(
        public readonly string $token,
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly \DateTimeImmutable $createdAt
    ) {
    }
}
