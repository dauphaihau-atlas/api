<?php

declare(strict_types=1);

namespace App\Presentation\Http\Policies;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

class UserPolicy
{
    public function viewAny(UserModel $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(UserModel $user, UserModel $target): bool
    {
        return $this->isAdmin($user) || $user->getKey() === $target->getKey();
    }

    public function create(UserModel $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(UserModel $user, UserModel $target): bool
    {
        return $this->isAdmin($user) || $user->getKey() === $target->getKey();
    }

    public function delete(UserModel $user, UserModel $target): bool
    {
        return $this->isAdmin($user);
    }

    public function import(UserModel $user): bool
    {
        return $this->isAdmin($user);
    }

    public function export(UserModel $user): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(UserModel $user): bool
    {
        return ($user->role ?? null) === 'admin';
    }
}
