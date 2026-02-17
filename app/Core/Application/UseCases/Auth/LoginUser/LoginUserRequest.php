<?php

namespace App\Core\Application\UseCases\Auth\LoginUser;

class LoginUserRequest
{
    public function __construct(
        public readonly string $email,
        public readonly string $password
    ) {}
}
