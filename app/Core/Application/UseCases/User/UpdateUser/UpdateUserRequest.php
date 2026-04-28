<?php

declare(strict_types=1);

namespace App\Core\Application\UseCases\User\UpdateUser;

class UpdateUserRequest
{
    public function __construct(
        public readonly int $id,
        public readonly int $version,
        public readonly ?string $name = null,
        public readonly ?string $email = null,
        public readonly ?string $password = null,
    ) {}
}
