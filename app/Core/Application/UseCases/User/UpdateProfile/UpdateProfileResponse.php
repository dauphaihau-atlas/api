<?php

declare(strict_types=1);

namespace App\Core\Application\UseCases\User\UpdateProfile;

use DateTimeImmutable;

final readonly class UpdateProfileResponse
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public int $version,
        public DateTimeImmutable $updatedAt,
    ) {}
}
