<?php

declare(strict_types=1);

namespace App\Core\Application\UseCases\Tenant\GetTenant;

use App\Core\Application\Contracts\TenantRepositoryInterface;
use App\Exceptions\NotFoundException;

class GetTenantUseCase
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
    ) {}

    public function execute(GetTenantRequest $request): GetTenantResponse
    {
        $tenant = $this->tenantRepository->findById($request->id);
        if ($tenant === null) {
            throw new NotFoundException('Tenant not found');
        }

        return new GetTenantResponse(
            id: $tenant->getId(),
            name: $tenant->getName(),
            slug: $tenant->getSlug(),
            settings: $tenant->getSettings(),
            isActive: $tenant->isActive(),
            createdAt: $tenant->getCreatedAt(),
            updatedAt: $tenant->getUpdatedAt(),
        );
    }
}
