<?php

declare(strict_types=1);

namespace App\Core\Application\UseCases\Tenant\CreateTenant;

class CreateTenantRequest
{
    public function __construct(
        public readonly string $name,
        public readonly string $slug,
        public readonly ?array $settings = null,
        public readonly bool $isActive = true,
    ) {}
}
