<?php

namespace App\Core\Application\UseCases\User\ForceDeleteUser;

class ForceDeleteUserRequest
{
    public function __construct(
        public readonly int $userId
    ) {}
}
