<?php

namespace App\Core\Application\UseCases\User\ImportUsers;

class ImportUsersResponse
{
    /**
     * @param  array<int, array{row: int, message: string}>  $errors
     */
    public function __construct(
        public readonly int $created,
        public readonly int $updated,
        public readonly array $errors
    ) {
    }
}
