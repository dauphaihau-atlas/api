<?php

declare(strict_types=1);

namespace App\Core\Application\UseCases\Tenant\CreateTenant;

use App\Core\Application\Contracts\TenantRepositoryInterface;
use App\Core\Domain\Entities\Tenant;
use App\Exceptions\ConflictException;
use App\Exceptions\InternalServerException;

class CreateTenantUseCase
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
    ) {}

    public function execute(CreateTenantRequest $request): CreateTenantResponse
    {
        if ($this->tenantRepository->slugExists($request->slug)) {
            throw new ConflictException('Tenant with this slug already exists');
        }

        $tenant = new Tenant(
            id: null,
            name: $request->name,
            slug: $request->slug,
            settings: $request->settings,
            isActive: $request->isActive,
        );

        $saved = $this->tenantRepository->save($tenant);

        $id = $saved->getId();
        if ($id === null) {
            throw new InternalServerException('Tenant was saved but ID was not returned');
        }

        return new CreateTenantResponse(
            id: $id,
            name: $saved->getName(),
            slug: $saved->getSlug(),
            settings: $saved->getSettings(),
            isActive: $saved->isActive(),
            createdAt: $saved->getCreatedAt(),
        );
    }
}
