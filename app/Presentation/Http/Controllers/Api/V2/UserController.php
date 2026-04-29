<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api\V2;

use App\Core\Application\DTOs\UserFilters;
use App\Core\Application\UseCases\User\CreateUser\CreateUserRequest;
use App\Core\Application\UseCases\User\CreateUser\CreateUserUseCase;
use App\Core\Application\UseCases\User\ListUsers\ListUsersRequest;
use App\Core\Application\UseCases\User\ListUsers\ListUsersUseCase;
use App\Exceptions\ValidationException;
use App\Presentation\Http\Controllers\Controller;
use App\Presentation\Http\Requests\CreateUserRequest as HttpCreateUserRequest;
use App\Presentation\Http\Resources\V2\UserResource;
use App\Presentation\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private readonly ListUsersUseCase $listUsersUseCase,
        private readonly CreateUserUseCase $createUserUseCase,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(max(1, (int) $request->input('per_page', 15)), 100);

        $trashedFilter = $request->input('trashed');
        if ($trashedFilter !== null && ! in_array($trashedFilter, ['with', 'only'], true)) {
            throw new ValidationException('Invalid trashed filter. Must be "with" or "only".');
        }

        $filters = new UserFilters(
            search: $request->input('search') ?: null,
            trashed: $trashedFilter,
        );

        $response = $this->listUsersUseCase->execute(new ListUsersRequest(
            page: $page,
            perPage: $perPage,
            filters: $filters,
        ));

        return ApiResponse::ok(UserResource::collection($response->users), meta: [
            'total' => $response->total,
            'per_page' => $response->perPage,
            'current_page' => $response->currentPage,
        ]);
    }

    public function store(HttpCreateUserRequest $request): JsonResponse
    {
        $validated = $request->validated();
        if ($validated === null || $validated === []) {
            return response()->json(['message' => 'Validation failed'], 422);
        }

        $response = $this->createUserUseCase->execute(new CreateUserRequest(
            name: $validated['name'],
            email: $validated['email'],
            password: $validated['password'] ?? null,
            role: $validated['role'] ?? 'user',
            sendInvite: (bool) ($validated['send_invite'] ?? false),
        ));

        return ApiResponse::created(new UserResource($response));
    }
}
