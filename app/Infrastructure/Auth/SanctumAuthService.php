<?php

namespace App\Infrastructure\Auth;

use App\Core\Application\Contracts\AuthServiceInterface;
use App\Core\Application\DTOs\AuthUserDTO;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SanctumAuthService implements AuthServiceInterface
{
    public function __construct(
        private readonly Request $request
    ) {
    }

    public function attempt(string $email, string $password): ?AuthUserDTO
    {
        $user = UserModel::where('email', $email)->first();
        if ($user === null || ! Hash::check($password, $user->password)) {
            return null;
        }

        $createdAt = $user->created_at !== null
            ? \DateTimeImmutable::createFromMutable($user->created_at)
            : new \DateTimeImmutable();

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
            if ($token !== null) {
                $token->delete();
            }
        }
    }
}
