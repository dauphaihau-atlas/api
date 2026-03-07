<?php

declare(strict_types=1);

namespace App\Core\Application\UseCases\Tenant\DeleteTenant;

use App\Core\Application\Contracts\TenantRepositoryInterface;
use App\Exceptions\NotFoundException;

class DeleteTenantUseCase
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
    ) {}

    public function execute(DeleteTenantRequest $request): DeleteTenantResponse
    {
        $tenant = $this->tenantRepository->findById($request->id);
        if ($tenant === null) {
            throw new NotFoundException('Tenant not found');
        }

        $deleted = $this->tenantRepository->delete($request->id);

        return new DeleteTenantResponse(deleted: $deleted);
    }
}
