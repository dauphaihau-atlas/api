<?php

declare(strict_types=1);

namespace App\Presentation\Http\Policies;

use App\Infrastructure\Persistence\Eloquent\Models\ActivityLogModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

class ActivityLogPolicy
{
    public function viewAny(UserModel $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(UserModel $user, ActivityLogModel $activityLog): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(UserModel $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('super_admin');
    }
}
