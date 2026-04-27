<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Observers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Cache;

class UserCacheObserver
{
    public function created(UserModel $model): void
    {
        Cache::tags(['users'])->flush();
        Cache::increment('version:users');
    }

    public function updated(UserModel $model): void
    {
        Cache::increment('version:users');
    }

    public function deleted(UserModel $model): void
    {
        Cache::tags(['users'])->flush();
        Cache::increment('version:users');
    }

    public function restored(UserModel $model): void
    {
        Cache::tags(['users'])->flush();
        Cache::increment('version:users');
    }

    public function forceDeleted(UserModel $model): void
    {
        Cache::tags(['users'])->flush();
        Cache::increment('version:users');
    }
}
