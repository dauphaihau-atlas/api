<?php

declare(strict_types=1);

namespace App\Presentation\Http\Policies;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

class UserPolicy
{
    public function viewAny(UserModel $user): bool
    {
        return $this->can($user, 'users.view-any');
    }

    public function view(UserModel $user, UserModel $target): bool
    {
        return $this->can($user, 'users.view') || $user->getKey() === $target->getKey();
    }

    public function create(UserModel $user): bool
    {
        return $this->can($user, 'users.create');
    }

    public function update(UserModel $user, UserModel $target): bool
    {
        return $this->can($user, 'users.update') || $user->getKey() === $target->getKey();
    }

    public function adminUpdate(UserModel $user): bool
    {
        return $this->can($user, 'users.update');
    }

    public function delete(UserModel $user): bool
    {
        return $this->can($user, 'users.delete');
    }

    public function import(UserModel $user): bool
    {
        return $this->can($user, 'users.import');
    }

    public function cancelImport(UserModel $user): bool
    {
        return $this->can($user, 'users.cancel-import');
    }

    public function export(UserModel $user): bool
    {
        return $this->can($user, 'users.export');
    }

    public function restore(UserModel $user): bool
    {
        return $this->can($user, 'users.restore');
    }

    public function forceDelete(UserModel $user): bool
    {
        return $this->can($user, 'users.force-delete');
    }

    private function can(UserModel $user, string $permission): bool
    {
        return $user->hasRole('super_admin') || $user->hasPermission($permission);
    }
}
