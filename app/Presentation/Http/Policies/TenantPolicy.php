<?php

declare(strict_types=1);

namespace App\Presentation\Http\Policies;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

class TenantPolicy
{
    public function viewAny(UserModel $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function view(UserModel $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function create(UserModel $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function update(UserModel $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function delete(UserModel $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    private function isSuperAdmin(UserModel $user): bool
    {
        return $user->hasRole('super_admin');
    }
}
