<?php

namespace App\Presentation\Http\Controllers\Api\V1;

use App\Core\Application\Contracts\UserRepositoryInterface;
use App\Core\Application\UseCases\User\CreateUser\CreateUserRequest;
use App\Core\Application\UseCases\User\CreateUser\CreateUserUseCase;
use App\Core\Application\UseCases\User\ExportUsers\ExportUsersRequest as ExportUsersUseCaseRequest;
use App\Core\Application\UseCases\User\ExportUsers\ExportUsersUseCase;
use App\Core\Application\UseCases\User\GetImportStatus\GetImportStatusRequest as GetImportStatusUseCaseRequest;
use App\Core\Application\UseCases\User\GetImportStatus\GetImportStatusUseCase;
use App\Core\Application\UseCases\User\ImportUsers\ImportUsersRequest as ImportUsersUseCaseRequest;
use App\Core\Application\UseCases\User\ImportUsers\ImportUsersUseCase;
use App\Exceptions\NotFoundException;
use App\Exceptions\ServiceUnavailableException;
use App\Exceptions\ValidationException;
use App\Presentation\Http\Controllers\Controller;
use App\Presentation\Http\Requests\CreateUserRequest as HttpCreateUserRequest;
use App\Presentation\Http\Requests\ImportUsersRequest as HttpImportUsersRequest;
use App\Presentation\Http\Resources\UserResource;
use App\Presentation\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    public function __construct(
        private readonly CreateUserUseCase $createUserUseCase,
        private readonly ImportUsersUseCase $importUsersUseCase,
        private readonly GetImportStatusUseCase $getImportStatusUseCase,
        private readonly ExportUsersUseCase $exportUsersUseCase,
        private readonly UserRepositoryInterface $userRepository
    ) {
    }

    /**
     * List users
     *
     * Retrieve users with pagination. Requires admin role.
     * Query params: page (default 1), per_page (default 15, max 100).
     *
     * @group Users
     * @authenticated
     *
     * @response 200 {"data":[{"id":1,"name":"Admin","email":"admin@example.com","avatar_url":null,"created_at":"2025-01-01 00:00:00"}],"meta":{"total":100,"per_page":15,"current_page":1}}
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Forbidden."}
     */
    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(max(1, (int) $request->input('per_page', 15)), 100);
        $users = $this->userRepository->findPaginated($page, $perPage);
        $total = $this->userRepository->countAll();

        return ApiResponse::ok(UserResource::collection($users), meta: [
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
        ]);
    }

    /**
     * Create a user
     *
     * Create a new user. Requires admin role.
     *
     * @group Users
     * @authenticated
     *
     * @response 201 {"id":2,"name":"John Doe","email":"john@example.com","avatar_url":null,"created_at":"2025-01-01 00:00:00"}
     * @response 422 {"message":"Validation failed","errors":{"email":["The email has already been taken."]}}
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

        return ApiResponse::created(new UserResource($response));
    }

    /**
     * Import users
     *
     * Bulk import users from a CSV file. Processed asynchronously. Requires admin role.
     *
     * @group Users
     * @authenticated
     *
     * @response 202 {"id":1,"status":"processing","message":"Import started successfully."}
     * @response 422 {"message":"Validation failed","errors":{"file":["The file field is required."]}}
     */
    public function import(HttpImportUsersRequest $request): JsonResponse
    {
        set_time_limit(300); // Large CSV files need time for streaming + job dispatch

        $file = $request->file('file');
        if ($file === null || ! $file->isValid()) {
            throw new ValidationException('Valid CSV file is required');
        }

        $diskName = config('filesystems.imports_disk', 'local');
        $storage = Storage::disk($diskName);
        $filename = date('Y-m-d_His').'_'.Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'.csv';

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

        if ($response->status === 'failed') {
            throw new ValidationException($response->message ?? 'Import failed.');
        }

        return ApiResponse::accepted([
            'id' => $response->importId,
            'status' => $response->status,
        ], 'Import started successfully.');
    }

    /**
     * Get import status
     *
     * Retrieve the progress and status of a user import job. Requires admin role.
     *
     * @group Users
     * @authenticated
     *
     * @urlParam id integer required The import ID. Example: 1
     *
     * @response 200 {"id":1,"status":"completed","total_rows":100,"processed_rows":100,"created":95,"updated":5,"errors":[],"progress_percentage":100,"started_at":"2025-01-01 00:00:00","completed_at":"2025-01-01 00:01:00"}
     * @response 404 {"message":"Import not found."}
     */
    public function importStatus(int $id): JsonResponse
    {
        $response = $this->getImportStatusUseCase->execute(
            new GetImportStatusUseCaseRequest($id)
        );

        if ($response === null) {
            throw new NotFoundException('Import not found.');
        }

        return ApiResponse::ok([
            'id' => $response->id,
            'status' => $response->status,
            'total_rows' => $response->totalRows,
            'processed_rows' => $response->processedRows,
            'created' => $response->createdCount,
            'updated' => $response->updatedCount,
            'errors' => $response->errors,
            'progress_percentage' => $response->totalRows > 0
                ? round(($response->processedRows / $response->totalRows) * 100, 2)
                : 0,
            'started_at' => $response->startedAt,
            'completed_at' => $response->completedAt,
        ]);
    }

    /**
     * Export users
     *
     * Export all users as a CSV file. Returns a signed download URL. Requires admin role.
     *
     * @group Users
     * @authenticated
     *
     * @response 200 {"path":"exports/users-2025-01-01.csv","url":"http://localhost/api/v1/users/export/download?path=exports/users-2025-01-01.csv&signature=abc123","expires_at":"2025-01-01T00:15:00+00:00"}
     */
    public function export(): JsonResponse
    {
        try {
            $response = $this->exportUsersUseCase->execute(new ExportUsersUseCaseRequest());
        } catch (RuntimeException $e) {
            Log::error('User export failed', ['error' => $e->getMessage()]);
            throw new ServiceUnavailableException(
                'Failed to generate export',
                'EXPORT_GENERATION_FAILED',
                config('app.debug') ? ['error' => $e->getMessage()] : null,
                0,
                $e
            );
        }

        $url = $response->url;
        $expiresAt = $response->expiresAt;

        if ($url === null) {
            $expiresAt = now()->addMinutes(15);
            $url = URL::temporarySignedRoute(
                'export.download',
                $expiresAt,
                ['path' => $response->path]
            );
        }

        $payload = [
            'path' => $response->path,
            'url' => $url,
        ];
        if ($expiresAt !== null) {
            $payload['expires_at'] = $expiresAt instanceof \DateTimeInterface
                ? $expiresAt->format('c')
                : $expiresAt->format('c');
        }

        return ApiResponse::ok($payload);
    }

    /**
     * Download export
     *
     * Download an exported CSV file via a signed URL. Requires admin role.
     *
     * @group Users
     * @authenticated
     *
     * @queryParam path string required The export file path. Example: exports/users-2025-01-01.csv
     *
     * @response 200 scenario="CSV file download" {}
     * @response 404 {"message":"Export file not found."}
     * @response 422 {"message":"Invalid or missing path."}
     */
    public function downloadExport(Request $request): JsonResponse|StreamedResponse
    {
        $path = $request->query('path');
        if (! is_string($path) || $path === '') {
            throw new ValidationException('Invalid or missing path.');
        }

        $path = str_replace('\\', '/', $path);
        if (str_starts_with($path, 'exports/') === false || str_contains($path, '..') !== false) {
            throw new ValidationException('Invalid path.');
        }

        $disk = Storage::disk(config('filesystems.exports_disk', 'local'));
        if ($disk->exists($path) === false) {
            throw new NotFoundException('Export file not found.');
        }

        $filename = basename($path);

        /** @var \Illuminate\Filesystem\FilesystemAdapter $adapter */
        $adapter = $disk;

        return $adapter->download($path, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
