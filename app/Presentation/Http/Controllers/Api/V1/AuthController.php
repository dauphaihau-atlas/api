<?php

namespace App\Presentation\Http\Controllers\Api\V1;

use App\Core\Application\Contracts\AuthServiceInterface;
use App\Core\Application\Contracts\UserRepositoryInterface;
use App\Core\Application\UseCases\Auth\LoginUser\LoginUserRequest;
use App\Core\Application\UseCases\Auth\LoginUser\LoginUserUseCase;
use App\Core\Application\UseCases\Auth\LogoutUser\LogoutUserUseCase;
use App\Core\Application\UseCases\User\CreateUser\CreateUserRequest;
use App\Core\Application\UseCases\User\CreateUser\CreateUserUseCase;
use App\Exceptions\NotFoundException;
use App\Exceptions\UnauthorizedException;
use App\Exceptions\ValidationException;
use App\Presentation\Http\Controllers\Controller;
use App\Presentation\Http\Requests\CreateUserRequest as HttpCreateUserRequest;
use App\Presentation\Http\Requests\LoginRequest;
use App\Presentation\Http\Resources\UserResource;
use App\Presentation\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    public function __construct(
        private readonly LoginUserUseCase $loginUserUseCase,
        private readonly LogoutUserUseCase $logoutUserUseCase,
        private readonly CreateUserUseCase $createUserUseCase,
        private readonly AuthServiceInterface $authService,
        private readonly UserRepositoryInterface $userRepository
    ) {}

    /**
     * Login
     *
     * Authenticate with email and password. Returns a bearer token and user details.
     *
     * @group Authentication
     *
     * @unauthenticated
     *
     * @response 200 {"token":"1|abc123...","user":{"id":1,"name":"Admin","email":"admin@example.com","avatar_url":null,"created_at":"2025-01-01 00:00:00"}}
     * @response 401 {"message":"Invalid credentials"}
     * @response 422 {"message":"Validation failed","errors":{"email":["The email field is required."]}}
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        if ($validated === null || $validated === []) {
            throw new ValidationException('Validation failed');
        }

        $useCaseRequest = new LoginUserRequest(
            email: $validated['email'],
            password: $validated['password']
        );
        $response = $this->loginUserUseCase->execute($useCaseRequest);

        if ($response === null) {
            throw new UnauthorizedException('Invalid credentials');
        }

        $user = $this->userRepository->findById($response->id);
        $avatarUrl = null;
        if ($user !== null) {
            $avatarPath = $user->getAvatarPath();
            if ($avatarPath !== null && $avatarPath !== '') {
                $avatarUrl = Storage::disk(config('filesystems.avatars_disk', 'public'))->url($avatarPath);
            }
        }

        return ApiResponse::ok([
            'token' => $response->token,
            'user' => [
                'id' => $response->id,
                'name' => $response->name,
                'email' => $response->email,
                'avatar_url' => $avatarUrl,
                'created_at' => $response->createdAt->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    /**
     * Register
     *
     * Create a new user account and return a bearer token with user details.
     *
     * @group Authentication
     *
     * @unauthenticated
     *
     * @response 201 {"token":"2|xyz789...","user":{"id":2,"name":"John Doe","email":"john@example.com","avatar_url":null,"created_at":"2025-01-01 00:00:00"}}
     * @response 422 {"message":"Validation failed","errors":{"email":["The email has already been taken."]}}
     */
    public function register(HttpCreateUserRequest $request): JsonResponse
    {
        $validated = $request->validated();
        if ($validated === null || $validated === []) {
            throw new ValidationException('Validation failed');
        }

        $useCaseRequest = new CreateUserRequest(
            name: $validated['name'],
            email: $validated['email'],
            password: $validated['password']
        );
        $createResponse = $this->createUserUseCase->execute($useCaseRequest);

        $token = $this->authService->createToken($createResponse->id, 'api');

        return ApiResponse::created([
            'token' => $token,
            'user' => new UserResource($createResponse),
        ]);
    }

    /**
     * Logout
     *
     * Revoke the current API token.
     *
     * @group Authentication
     *
     * @authenticated
     *
     * @response 204 scenario="Success" {}
     * @response 401 {"message":"Unauthenticated."}
     */
    public function logout(): JsonResponse
    {
        $this->logoutUserUseCase->execute();

        return ApiResponse::noContent();
    }

    /**
     * Get current user
     *
     * Return the currently authenticated user's details.
     *
     * @group Authentication
     *
     * @authenticated
     *
     * @response 200 {"id":1,"name":"Admin","email":"admin@example.com","avatar_url":null,"created_at":"2025-01-01 00:00:00"}
     * @response 401 {"message":"Unauthenticated."}
     */
    public function me(Request $request): JsonResponse
    {
        $userId = $request->user()?->getAuthIdentifier();
        if ($userId === null) {
            throw new UnauthorizedException('Unauthenticated');
        }

        $user = $this->userRepository->findById((int) $userId);
        if ($user === null) {
            throw new NotFoundException('User not found');
        }

        return ApiResponse::ok(new UserResource($user));
    }
}
