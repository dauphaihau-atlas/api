<?php

namespace App\Core\Application\UseCases\User\RestoreUser;

use App\Core\Domain\Entities\User;

class RestoreUserResponse
{
    public function __construct(
        public readonly User $user,
        public readonly string $message = 'User restored successfully'
    ) {}
}
