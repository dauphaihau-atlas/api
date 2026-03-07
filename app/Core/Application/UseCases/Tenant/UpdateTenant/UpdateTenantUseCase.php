<?php

declare(strict_types=1);

namespace App\Core\Application\UseCases\Tenant\UpdateTenant;

use App\Core\Application\Contracts\TenantRepositoryInterface;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;

class UpdateTenantUseCase
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
    ) {}

    public function execute(UpdateTenantRequest $request): UpdateTenantResponse
    {
        $tenant = $this->tenantRepository->findById($request->id);
        if ($tenant === null) {
            throw new NotFoundException('Tenant not found');
        }

        if ($request->name !== null) {
            $tenant->updateName($request->name);
        }

        if ($request->slug !== null) {
            if ($this->tenantRepository->slugExists($request->slug, $request->id)) {
                throw new ConflictException('Tenant with this slug already exists');
            }
            $tenant->updateSlug($request->slug);
        }

        if ($request->settingsProvided) {
            $tenant->updateSettings($request->settings);
        }

        if ($request->isActive !== null) {
            $request->isActive ? $tenant->activate() : $tenant->deactivate();
        }

        $saved = $this->tenantRepository->save($tenant);

        return new UpdateTenantResponse(
            id: $saved->getId(),
            name: $saved->getName(),
            slug: $saved->getSlug(),
            settings: $saved->getSettings(),
            isActive: $saved->isActive(),
            updatedAt: $saved->getUpdatedAt(),
        );
    }
}
