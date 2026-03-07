<?php

declare(strict_types=1);

namespace App\Core\Application\Contracts;

use App\Core\Domain\Entities\Tenant;

interface TenantRepositoryInterface
{
    public function findBySlug(string $slug): ?Tenant;

    public function findById(int $id): ?Tenant;

    public function save(Tenant $tenant): Tenant;

    public function delete(int $id): bool;

    /**
     * @return Tenant[]
     */
    public function findAll(): array;

    /**
     * @return Tenant[]
     */
    public function findPaginated(int $page, int $perPage): array;

    public function count(): int;

    public function slugExists(string $slug, ?int $excludeId = null): bool;
}
