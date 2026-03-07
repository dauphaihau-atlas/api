<?php

declare(strict_types=1);

namespace App\Core\Application\UseCases\Tenant\ListTenants;

use App\Core\Application\Contracts\TenantRepositoryInterface;

class ListTenantsUseCase
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
    ) {}

    public function execute(ListTenantsRequest $request): ListTenantsResponse
    {
        $page = max(1, $request->page);
        $perPage = min(max(1, $request->perPage), 100);

        $tenants = $this->tenantRepository->findPaginated($page, $perPage);
        $total = $this->tenantRepository->count();

        return new ListTenantsResponse(
            tenants: $tenants,
            total: $total,
            perPage: $perPage,
            currentPage: $page,
        );
    }
}
