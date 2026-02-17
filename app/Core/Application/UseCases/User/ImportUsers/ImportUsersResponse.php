<?php

namespace App\Core\Application\UseCases\User\ImportUsers;

class ImportUsersResponse
{
    public function __construct(
        public readonly ?int $importId,
        public readonly string $status,
        public readonly ?string $message = null
    ) {}
}
