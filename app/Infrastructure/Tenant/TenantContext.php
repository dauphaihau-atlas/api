<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant;

use App\Core\Domain\Entities\Tenant;

class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function isResolved(): bool
    {
        return $this->tenant !== null;
    }

    public function getTenantId(): ?int
    {
        return $this->tenant?->getId();
    }
}
