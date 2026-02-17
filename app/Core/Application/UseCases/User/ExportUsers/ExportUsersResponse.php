<?php

namespace App\Core\Application\UseCases\User\ExportUsers;

use DateTimeInterface;

class ExportUsersResponse
{
    public function __construct(
        public readonly string $path,
        public readonly ?string $url,
        public readonly ?DateTimeInterface $expiresAt
    ) {}
}
