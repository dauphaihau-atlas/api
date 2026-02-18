<?php

declare(strict_types=1);

namespace App\Core\Application\UseCases\User\ListUsers;

use App\Core\Domain\Entities\User;

final readonly class ListUsersResponse
{
    /**
     * @param  User[]  $users
     */
    public function __construct(
        public array $users,
        public int $total,
        public int $perPage,
        public int $currentPage,
    ) {}
}
