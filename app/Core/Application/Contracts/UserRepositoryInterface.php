<?php

namespace App\Core\Application\Contracts;

use App\Core\Application\DTOs\UserFilters;
use App\Core\Domain\Entities\User;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByEmail(string $email): ?User;

    public function save(User $user): User;

    public function delete(int $id): bool;

    /**
     * @return User[]
     */
    public function findAll(): array;

    /**
     * @return User[]
     */
    public function findPaginated(UserFilters $filters, int $page, int $perPage): array;

    public function count(UserFilters $filters): int;

    /**
     * Bulk upsert users (insert new, update existing by email).
     *
     * @param  array<int, array{name: string, email: string, password: string, role: string, created_at: string, updated_at: string}>  $usersData
     * @return array{created: int, updated: int}
     */
    public function upsertBatch(array $usersData): array;
}
