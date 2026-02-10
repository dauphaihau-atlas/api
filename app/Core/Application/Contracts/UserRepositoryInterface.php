<?php

namespace App\Core\Application\Contracts;

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
}
