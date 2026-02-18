<?php

declare(strict_types=1);

namespace App\Core\Application\Contracts;

use App\Core\Application\DTOs\ActivityLogEntry;
use App\Core\Application\DTOs\ActivityLogFilters;

interface ActivityLogRepositoryInterface
{
    /**
     * @return ActivityLogEntry[]
     */
    public function findPaginated(ActivityLogFilters $filters, int $page, int $perPage): array;

    public function count(ActivityLogFilters $filters): int;

    public function findById(int $id): ?ActivityLogEntry;
}
