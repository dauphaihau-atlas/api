<?php

namespace App\Presentation\Http\Controllers\Api\V1;

use App\Core\Application\Contracts\AuthServiceInterface;
use App\Core\Application\Contracts\UserRepositoryInterface;
use App\Core\Application\UseCases\Auth\LoginUser\LoginUserRequest;
use App\Core\Application\UseCases\Auth\LoginUser\LoginUserUseCase;
use App\Core\Application\UseCases\Auth\LogoutUser\LogoutUserUseCase;
use App\Core\Application\UseCases\User\CreateUser\CreateUserRequest;
use App\Core\Application\UseCases\User\CreateUser\CreateUserUseCase;
use App\Core\Domain\Exceptions\InvalidEmailException;
use App\Presentation\Http\Controllers\Controller;
use App\Presentation\Http\Requests\CreateUserRequest as HttpCreateUserRequest;
use App\Presentation\Http\Requests\LoginRequest;
use App\Presentation\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly LoginUserUseCase $loginUserUseCase,
        private readonly LogoutUserUseCase $logoutUserUseCase,
        private readonly CreateUserUseCase $createUserUseCase,
        private readonly AuthServiceInterface $authService,
        private readonly UserRepositoryInterface $userRepository
    ) {
    }

    /**
     * Login: email + password, returns token and user.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        if ($validated === null || $validated === []) {
            return response()->json(['message' => 'Validation failed'], 422);
        }

        $useCaseRequest = new LoginUserRequest(
            email: $validated['email'],
            password: $validated['password']
        );
        $response = $this->loginUserUseCase->execute($useCaseRequest);

        if ($response === null) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        return response()->json([
            'token' => $response->token,
            'user' => [
                'id' => $response->id,
                'name' => $response->name,
                'email' => $response->email,
                'created_at' => $response->createdAt->format('Y-m-d H:i:s'),
            ],
        ], 200);
    }

    /**
     * Register: create user and return token + user.
     */
    public function register(HttpCreateUserRequest $request): JsonResponse
    {
        $validated = $request->validated();
        if ($validated === null || $validated === []) {
            return response()->json(['message' => 'Validation failed'], 422);
        }

        try {
            $useCaseRequest = new CreateUserRequest(
                name: $validated['name'],
                email: $validated['email'],
                password: $validated['password']
            );
            $createResponse = $this->createUserUseCase->execute($useCaseRequest);
        } catch (InvalidEmailException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $token = $this->authService->createToken($createResponse->id, 'api');

        return response()->json([
            'token' => $token,
            'user' => new UserResource($createResponse),
        ], 201);
    }

    /**
     * Logout: revoke current API token.
     */
    public function logout(): JsonResponse
    {
        $this->logoutUserUseCase->execute();

        return response()->json(null, 204);
    }

    /**
     * Me: return the currently authenticated user (requires auth:sanctum).
     */
    public function me(Request $request): JsonResponse
    {
        $userId = $request->user()?->getAuthIdentifier();
        if ($userId === null) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $user = $this->userRepository->findById((int) $userId);
        if ($user === null) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json(new UserResource($user), 200);
    }
}
