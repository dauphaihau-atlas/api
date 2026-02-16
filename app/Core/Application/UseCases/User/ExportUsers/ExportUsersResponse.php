<?php

namespace App\Core\Application\UseCases\User\ExportUsers;

class ExportUsersResponse
{
    public function __construct(
        public readonly string $path,
        public readonly ?string $url,
        public readonly ?\DateTimeInterface $expiresAt
    ) {
    }
}
