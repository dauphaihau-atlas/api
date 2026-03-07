<?php

namespace App\Core\Application\UseCases\Auth\LoginUser;

use App\Core\Application\Contracts\AuthServiceInterface;
use App\Infrastructure\Tenant\TenantContext;

class LoginUserUseCase
{
    public function __construct(
        private readonly AuthServiceInterface $authService,
        private readonly TenantContext $tenantContext,
    ) {}

    public function execute(
        LoginUserRequest $request,
        bool $revokeExistingTokens = false,
    ): ?LoginUserResponse {
        $user = $this->authService->attempt($request->email, $request->password);
        if ($user === null) {
            return null;
        }

        // If a tenant is resolved, verify this user belongs to it.
        // Super admins may log in even if a tenant header is present (cross-tenant access is handled by authorization elsewhere).
        $resolvedTenantId = $this->tenantContext->getTenantId();
        if (
            $resolvedTenantId !== null &&
            $user->tenantId !== $resolvedTenantId &&
            $user->tenantId !== null
        ) {
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
            createdAt: $user->createdAt,
        );
    }
}
