<?php

namespace App\Core\Application\UseCases\User\CreateUser;

class CreateUserRequest
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password
    ) {
    }
}
