<?php

declare(strict_types=1);

namespace App\Core\Application\UseCases\Tenant\UpdateTenant;

class UpdateTenantRequest
{
    public function __construct(
        public readonly int $id,
        public readonly int $version,
        public readonly ?string $name = null,
        public readonly ?string $slug = null,
        public readonly ?array $settings = null,
        public readonly ?bool $isActive = null,
        public readonly bool $settingsProvided = false,
    ) {}
}
