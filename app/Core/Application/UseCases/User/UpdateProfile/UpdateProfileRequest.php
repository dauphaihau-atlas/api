<?php

declare(strict_types=1);

namespace App\Core\Application\UseCases\User\UpdateProfile;

class UpdateProfileRequest
{
    public function __construct(
        public readonly int $userId,
        public readonly int $version,
        public readonly ?string $name = null,
        public readonly ?string $email = null,
        public readonly ?string $password = null,
    ) {}
}
