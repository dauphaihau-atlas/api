<?php

declare(strict_types=1);

namespace App\Core\Application\UseCases\ActivityLog\GetActivityLog;

use App\Core\Application\DTOs\ActivityLogEntry;

final readonly class GetActivityLogResponse
{
    public function __construct(
        public ActivityLogEntry $entry,
    ) {}
}
