<?php

namespace App\Core\Application\UseCases\User\ForceDeleteUser;

class ForceDeleteUserResponse
{
    public function __construct(
        public readonly int $userId,
        public readonly string $message = 'User permanently deleted'
    ) {}
}
