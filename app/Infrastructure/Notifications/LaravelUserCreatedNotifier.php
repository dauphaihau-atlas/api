<?php

namespace App\Infrastructure\Notifications;

use App\Core\Application\Contracts\UserCreatedNotifierInterface;
use App\Notifications\UserCreatedNotification;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Log;

class LaravelUserCreatedNotifier implements UserCreatedNotifierInterface
{
    public function notifyUserCreated(int $userId, string $userName, string $userEmail): void
    {
        $user = UserModel::find($userId);

        if ($user === null) {
            Log::warning('UserCreatedNotifier: user not found, skipping notification', [
                'user_id' => $userId,
                'email' => $userEmail,
            ]);

            return;
        }

        $user->notify(new UserCreatedNotification($userName));
    }
}
