<?php

namespace App\Presentation\Http\Controllers\Api\V1;

use App\Core\Application\Contracts\UserRepositoryInterface;
use App\Core\Application\UseCases\User\CreateUser\CreateUserRequest;
use App\Core\Application\UseCases\User\CreateUser\CreateUserUseCase;
use App\Presentation\Http\Controllers\Controller;
use App\Presentation\Http\Requests\CreateUserRequest as HttpCreateUserRequest;
use App\Presentation\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function __construct(
        private readonly CreateUserUseCase $createUserUseCase,
        private readonly UserRepositoryInterface $userRepository
    ) {
    }

    /**
     * List users.
     */
    public function index(): JsonResponse
    {
        $users = $this->userRepository->findAll();

        return response()->json(UserResource::collection($users));
    }

    /**
     * Create a user.
     */
    public function store(HttpCreateUserRequest $request): JsonResponse
    {
        $validated = $request->validated();
        if ($validated === null || $validated === []) {
            return response()->json(['message' => 'Validation failed'], 422);
        }

        $useCaseRequest = new CreateUserRequest(
            name: $validated['name'],
            email: $validated['email'],
            password: $validated['password']
        );

        $response = $this->createUserUseCase->execute($useCaseRequest);

        return response()->json(new UserResource($response), 201);
    }
}
