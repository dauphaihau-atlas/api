<?php

declare(strict_types=1);

namespace App\Core\Application\UseCases\Tenant\GetTenant;

class GetTenantRequest
{
    public function __construct(
        public readonly int $id,
    ) {}
}
