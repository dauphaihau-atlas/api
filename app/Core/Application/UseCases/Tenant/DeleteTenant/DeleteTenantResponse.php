<?php

declare(strict_types=1);

namespace App\Core\Application\UseCases\Tenant\DeleteTenant;

final readonly class DeleteTenantResponse
{
    public function __construct(
        public bool $deleted,
    ) {}
}
