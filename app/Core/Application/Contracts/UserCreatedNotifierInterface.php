<?php

namespace App\Core\Application\Contracts;

interface UserCreatedNotifierInterface
{
    /**
     * Send "user created" notification to the given user (e.g. welcome mail + in-app).
     */
    public function notifyUserCreated(int $userId, string $userName, string $userEmail): void;
}
