<?php

declare(strict_types=1);

namespace App\Core\Application\DTOs;

final readonly class UserFilters
{
    public function __construct(
        public ?string $search = null,
        public string $sort = 'id',
        public ?string $trashed = null,
    ) {}
}
