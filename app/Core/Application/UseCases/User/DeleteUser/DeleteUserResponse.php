<?php

namespace App\Core\Application\UseCases\User\DeleteUser;

class DeleteUserResponse
{
    public function __construct(
        public readonly int $userId,
        public readonly string $message = 'User deleted successfully'
    ) {}
}
