<?php

declare(strict_types=1);

namespace App\Core\Application\DTOs;

final readonly class ActivityLogFilters
{
    public function __construct(
        public ?string $event = null,
        public ?string $subjectType = null,
        public ?int $subjectId = null,
        public ?int $causerId = null,
        public ?string $fromDate = null,
        public ?string $toDate = null,
        public string $sort = '-created_at',
    ) {}
}
