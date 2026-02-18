<?php

declare(strict_types=1);

namespace App\Core\Application\UseCases\ActivityLog\ListActivityLogs;

use App\Core\Application\DTOs\ActivityLogEntry;

final readonly class ListActivityLogsResponse
{
    /**
     * @param  ActivityLogEntry[]  $entries
     */
    public function __construct(
        public array $entries,
        public int $total,
        public int $perPage,
        public int $currentPage,
    ) {}
}
