<?php

declare(strict_types=1);

namespace App\Core\Application\UseCases\Tenant\UpdateTenant;

use DateTimeImmutable;

final readonly class UpdateTenantResponse
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public ?array $settings,
        public bool $isActive,
        public DateTimeImmutable $updatedAt,
    ) {}
}
