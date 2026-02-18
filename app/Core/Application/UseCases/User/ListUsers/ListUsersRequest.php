<?php

declare(strict_types=1);

namespace App\Core\Application\UseCases\User\ListUsers;

use App\Core\Application\DTOs\UserFilters;

final readonly class ListUsersRequest
{
    public function __construct(
        public int $page,
        public int $perPage,
        public UserFilters $filters,
    ) {}
}
