<?php

declare(strict_types=1);

namespace App\Core\Application\UseCases\Tenant\ListTenants;

use App\Core\Domain\Entities\Tenant;

final readonly class ListTenantsResponse
{
    /**
     * @param  Tenant[]  $tenants
     */
    public function __construct(
        public array $tenants,
        public int $total,
        public int $perPage,
        public int $currentPage,
    ) {}
}
