<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserCreated;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Notifications\UserCreatedNotification;
use Illuminate\Support\Facades\Log;

class SendUserCreatedNotification
{
    public function handle(UserCreated $event): void
    {
        $user = UserModel::find($event->user->getId());

        if ($user === null) {
            Log::warning('SendUserCreatedNotification: user not found, skipping notification', [
                'user_id' => $event->user->getId(),
            ]);

            return;
        }

        $user->notify(new UserCreatedNotification($event->user->getName()));
    }
}
