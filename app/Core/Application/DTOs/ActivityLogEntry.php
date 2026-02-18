<?php

declare(strict_types=1);

namespace App\Core\Application\DTOs;

use DateTimeInterface;

final readonly class ActivityLogEntry
{
    public function __construct(
        public int $id,
        public ?string $logName,
        public string $event,
        public string $subjectType,
        public int $subjectId,
        public ?string $causerType,
        public ?int $causerId,
        /** @var array<string, mixed>|null */
        public ?array $properties,
        public DateTimeInterface $createdAt,
        public ?string $causerName = null,
        public ?string $causerEmail = null,
    ) {}
}
