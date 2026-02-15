<?php

namespace App\Core\Application\UseCases\User\ImportUsers;

class ImportUsersRequest
{
    public function __construct(
        public readonly string $path
    ) {
    }
}
