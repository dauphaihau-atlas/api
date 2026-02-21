<?php

namespace App\Core\Application\UseCases\Auth\LoginUser;

use App\Core\Application\Contracts\AuthServiceInterface;

class LoginUserUseCase
{
    public function __construct(
        private readonly AuthServiceInterface $authService
    ) {}

    public function execute(LoginUserRequest $request, bool $revokeExistingTokens = false): ?LoginUserResponse
    {
        $user = $this->authService->attempt($request->email, $request->password);
        if ($user === null) {
            return null;
        }

        if ($revokeExistingTokens) {
            $this->authService->revokeAllTokensForUser($user->id);
        }

        $token = $this->authService->createToken($user->id, 'api');

        return new LoginUserResponse(
            token: $token,
            id: $user->id,
            name: $user->name,
            email: $user->email,
            createdAt: $user->createdAt
        );
    }
}
