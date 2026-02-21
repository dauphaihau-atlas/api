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

    public function restore(int $id): bool;

    public function forceDelete(int $id): bool;

    public function findTrashedById(int $id): ?User;

    /**
     * @return User[]
     */
    public function findAll(): array;

    /**
     * @return User[]
     */
    public function findPaginated(UserFilters $filters, int $page, int $perPage): array;

    public function count(UserFilters $filters): int;

    public function countActive(): int;

    public function countTrashed(): int;

    public function countCreatedToday(): int;

    /**
     * Bulk upsert users (insert new, update existing by email).
     *
     * @param  array<int, array{name: string, email: string, password: string, created_at: string, updated_at: string}>  $usersData
     * @return array{created: int, updated: int}
     */
    public function upsertBatch(array $usersData): array;
}
