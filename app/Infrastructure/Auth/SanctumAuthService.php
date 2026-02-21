<?php

namespace App\Infrastructure\Auth;

use App\Core\Application\Contracts\AuthServiceInterface;
use App\Core\Application\DTOs\AuthUserDTO;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class SanctumAuthService implements AuthServiceInterface
{
    public function __construct(
        private readonly Request $request
    ) {}

    public function attempt(string $email, string $password): ?AuthUserDTO
    {
        $user = UserModel::where('email', $email)->first();
        if ($user === null || ! Hash::check($password, $user->password)) {
            return null;
        }

        $createdAt = $user->created_at !== null
            ? DateTimeImmutable::createFromMutable($user->created_at)
            : new DateTimeImmutable;

        return new AuthUserDTO(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            createdAt: $createdAt
        );
    }

    public function createToken(int $userId, string $name = 'api'): string
    {
        $user = UserModel::findOrFail($userId);

        return $user->createToken($name)->plainTextToken;
    }

    public function revokeCurrentToken(): void
    {
        $user = $this->request->user();
        if ($user !== null && method_exists($user, 'currentAccessToken')) {
            $token = $user->currentAccessToken();
            // TransientToken is used for session-based (SPA) auth and has no DB record to delete.
            if ($token instanceof PersonalAccessToken) {
                $token->delete();
            }
        }
    }

    public function revokeTokenByPlaintext(string $token): void
    {
        PersonalAccessToken::findToken($token)?->delete();
    }

    public function revokeAllTokensForUser(int $userId): void
    {
        UserModel::findOrFail($userId)->tokens()->delete();
    }

    public function loginSession(int $userId): void
    {
        Auth::login(UserModel::findOrFail($userId));
    }
}
