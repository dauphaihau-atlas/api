<?php

declare(strict_types=1);

namespace App\Core\Application\UseCases\Tenant\ListTenants;

class ListTenantsRequest
{
    public function __construct(
        public readonly int $page = 1,
        public readonly int $perPage = 15,
    ) {}
}
