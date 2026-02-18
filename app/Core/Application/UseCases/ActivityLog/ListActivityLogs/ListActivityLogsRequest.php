<?php

declare(strict_types=1);

namespace App\Core\Application\UseCases\ActivityLog\ListActivityLogs;

use App\Core\Application\DTOs\ActivityLogFilters;

final readonly class ListActivityLogsRequest
{
    public function __construct(
        public int $page,
        public int $perPage,
        public ActivityLogFilters $filters,
    ) {}
}
