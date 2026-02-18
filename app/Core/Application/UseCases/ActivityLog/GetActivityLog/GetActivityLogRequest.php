<?php

declare(strict_types=1);

namespace App\Core\Application\UseCases\ActivityLog\GetActivityLog;

final readonly class GetActivityLogRequest
{
    public function __construct(
        public int $id,
    ) {}
}
