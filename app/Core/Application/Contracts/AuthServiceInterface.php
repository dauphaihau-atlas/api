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
}
