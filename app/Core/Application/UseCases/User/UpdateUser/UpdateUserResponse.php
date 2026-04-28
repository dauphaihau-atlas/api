<?php

declare(strict_types=1);

namespace App\Core\Application\UseCases\User\UpdateUser;

use DateTimeImmutable;

final readonly class UpdateUserResponse
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public int $version,
        public DateTimeImmutable $updatedAt,
    ) {}
}
