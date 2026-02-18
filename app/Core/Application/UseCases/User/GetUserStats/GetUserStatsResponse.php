<?php

declare(strict_types=1);

namespace App\Core\Application\UseCases\User\GetUserStats;

class GetUserStatsResponse
{
    public function __construct(
        public readonly int $totalActive,
        public readonly int $totalDeleted,
        public readonly int $createdToday,
    ) {}
}
