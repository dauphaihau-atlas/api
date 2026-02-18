<?php

namespace App\Core\Application\UseCases\User\DeleteUser;

class DeleteUserRequest
{
    public function __construct(
        public readonly int $userId
    ) {}
}
