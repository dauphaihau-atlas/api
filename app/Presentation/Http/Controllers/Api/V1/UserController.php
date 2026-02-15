<?php

namespace App\Presentation\Http\Controllers\Api\V1;

use App\Core\Application\Contracts\UserRepositoryInterface;
use App\Core\Application\UseCases\User\CreateUser\CreateUserRequest;
use App\Core\Application\UseCases\User\CreateUser\CreateUserUseCase;
use App\Core\Application\UseCases\User\ImportUsers\ImportUsersRequest as ImportUsersUseCaseRequest;
use App\Core\Application\UseCases\User\ImportUsers\ImportUsersUseCase;
use App\Exceptions\ServiceUnavailableException;
use App\Presentation\Http\Controllers\Controller;
use App\Presentation\Http\Requests\CreateUserRequest as HttpCreateUserRequest;
use App\Presentation\Http\Requests\ImportUsersRequest as HttpImportUsersRequest;
use App\Presentation\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function __construct(
        private readonly CreateUserUseCase $createUserUseCase,
        private readonly ImportUsersUseCase $importUsersUseCase,
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

    /**
     * Bulk import users from CSV (admin only).
     */
    public function import(HttpImportUsersRequest $request): JsonResponse
    {
        $file = $request->file('file');
        if ($file === null || ! $file->isValid()) {
            return response()->json(['message' => 'Valid CSV file is required'], 422);
        }

        $diskName = config('filesystems.imports_disk', 'local');
        $storage = Storage::disk($diskName);
        $filename = date('Y-m-d_His') . '_' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.csv';

        try {
            $path = $storage->putFileAs('imports', $file, $filename);
        } catch (\Throwable $e) {
            Log::error('User import storage write failed', [
                'error' => $e->getMessage(),
            ]);
            throw new ServiceUnavailableException(
                'Failed to store import file',
                'IMPORT_STORE_FAILED',
                config('app.debug') ? ['error' => $e->getMessage()] : null,
                0,
                $e
            );
        }

        if ($path === false || $path === '') {
            throw new ServiceUnavailableException('Failed to store import file', 'IMPORT_STORE_FAILED');
        }

        $response = $this->importUsersUseCase->execute(new ImportUsersUseCaseRequest($path));

        return response()->json([
            'created' => $response->created,
            'updated' => $response->updated,
            'errors' => $response->errors,
        ], 200);
    }
}
