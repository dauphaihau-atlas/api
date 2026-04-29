<?php

declare(strict_types=1);

namespace App\Presentation\Http\Policies;

use App\Infrastructure\Persistence\Eloquent\Models\ActivityLogModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

class ActivityLogPolicy
{
    public function viewAny(UserModel $user): bool
    {
        return $this->can($user, 'activity-logs.view-any');
    }

    public function view(UserModel $user, ActivityLogModel $activityLog): bool
    {
        return $this->can($user, 'activity-logs.view');
    }

    private function can(UserModel $user, string $permission): bool
    {
        return $user->hasRole('super_admin') || $user->hasPermission($permission);
    }
}
