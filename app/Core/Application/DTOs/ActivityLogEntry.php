<?php

declare(strict_types=1);

namespace App\Core\Application\DTOs;

use DateTimeInterface;

final readonly class ActivityLogEntry
{
    /**
     * @param  array<string, mixed>|null  $properties
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function __construct(
        public int $id,
        public ?string $logName,
        public string $event,
        public string $subjectType,
        public int $subjectId,
        public ?string $causerType,
        public ?int $causerId,
        public ?array $properties,
        public DateTimeInterface $createdAt,
        public ?string $causerName = null,
        public ?string $causerEmail = null,
        public ?array $oldValues = null,
        public ?array $newValues = null,
    ) {}
}
