<?php

namespace App\Core\Application\UseCases\Auth\LogoutUser;

use App\Core\Application\Contracts\AuthServiceInterface;

class LogoutUserUseCase
{
    public function __construct(
        private readonly AuthServiceInterface $authService
    ) {
    }

    public function execute(): void
    {
        $this->authService->revokeCurrentToken();
    }
}
