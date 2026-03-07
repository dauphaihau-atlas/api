<?php

declare(strict_types=1);

namespace App\Core\Application\UseCases\Tenant\DeleteTenant;

class DeleteTenantRequest
{
    public function __construct(
        public readonly int $id,
    ) {}
}
