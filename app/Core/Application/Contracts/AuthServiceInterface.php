<?php

namespace App\Core\Application\Contracts;

use App\Core\Application\DTOs\AuthUserDTO;

/**
 * Port for authentication and token operations (API-only, e.g. Sanctum).
 */
interface AuthServiceInterface
{
    /**
     * Attempt to authenticate by email and password.
     * Returns a simple DTO with user id, name, email on success; null on failure.
     */
    public function attempt(string $email, string $password): ?AuthUserDTO;

    /**
     * Create an API token for the given user id. Returns the plain-text token.
     */
    public function createToken(int $userId, string $name = 'api'): string;

    /**
     * Revoke the current request's API token (e.g. logout).
     */
    public function revokeCurrentToken(): void;

    /**
     * Revoke an API token by its plain-text value (e.g. cookie-based logout).
     */
    public function revokeTokenByPlaintext(string $token): void;

    /**
     * Revoke all API tokens for the given user id (e.g. before issuing a new web session token).
     */
    public function revokeAllTokensForUser(int $userId): void;

    /**
     * Log the user into the web session guard (for SPA stateful requests).
     */
    public function loginSession(int $userId): void;
}
