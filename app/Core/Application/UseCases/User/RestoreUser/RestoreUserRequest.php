<?php

namespace App\Core\Application\UseCases\User\RestoreUser;

class RestoreUserRequest
{
    public function __construct(
        public readonly int $userId
    ) {}
}
