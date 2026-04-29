<?php

namespace App\Presentation\Http\Controllers\Api\V1;

use App\Core\Application\DTOs\UserFilters;
use App\Core\Application\UseCases\User\CancelImport\CancelImportRequest;
use App\Core\Application\UseCases\User\CancelImport\CancelImportUseCase;
use App\Core\Application\UseCases\User\CreateUser\CreateUserRequest;
use App\Core\Application\UseCases\User\CreateUser\CreateUserUseCase;
use App\Core\Application\UseCases\User\DeleteUser\DeleteUserRequest;
use App\Core\Application\UseCases\User\DeleteUser\DeleteUserUseCase;
use App\Core\Application\UseCases\User\ExportUsers\ExportUsersRequest as ExportUsersUseCaseRequest;
use App\Core\Application\UseCases\User\ExportUsers\ExportUsersUseCase;
use App\Core\Application\UseCases\User\ForceDeleteUser\ForceDeleteUserRequest;
use App\Core\Application\UseCases\User\ForceDeleteUser\ForceDeleteUserUseCase;
use App\Core\Application\UseCases\User\GetImportStatus\GetImportStatusRequest as GetImportStatusUseCaseRequest;
use App\Core\Application\UseCases\User\GetImportStatus\GetImportStatusUseCase;
use App\Core\Application\UseCases\User\GetUserStats\GetUserStatsUseCase;
use App\Core\Application\UseCases\User\ImportUsers\ImportUsersRequest as ImportUsersUseCaseRequest;
use App\Core\Application\UseCases\User\ImportUsers\ImportUsersUseCase;
use App\Core\Application\UseCases\User\ListUsers\ListUsersRequest;
use App\Core\Application\UseCases\User\ListUsers\ListUsersUseCase;
use App\Core\Application\UseCases\User\RestoreUser\RestoreUserRequest;
use App\Core\Application\UseCases\User\RestoreUser\RestoreUserUseCase;
use App\Core\Application\UseCases\User\UpdateUser\UpdateUserRequest as UpdateUserUseCaseRequest;
use App\Core\Application\UseCases\User\UpdateUser\UpdateUserUseCase;
use App\Exceptions\NotFoundException;
use App\Exceptions\ServiceUnavailableException;
use App\Exceptions\ValidationException;
use App\Presentation\Http\Controllers\Controller;
use App\Presentation\Http\Requests\CreateUserRequest as HttpCreateUserRequest;
use App\Presentation\Http\Requests\ExportUsersRequest as HttpExportUsersRequest;
use App\Presentation\Http\Requests\ImportUsersRequest as HttpImportUsersRequest;
use App\Presentation\Http\Requests\UpdateUserRequest as HttpUpdateUserRequest;
use App\Presentation\Http\Resources\UserResource;
use App\Presentation\Http\Responses\ApiResponse;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class UserController extends Controller
{
    public function __construct(
        private readonly CreateUserUseCase $createUserUseCase,
        private readonly UpdateUserUseCase $updateUserUseCase,
        private readonly ImportUsersUseCase $importUsersUseCase,
        private readonly CancelImportUseCase $cancelImportUseCase,
        private readonly GetImportStatusUseCase $getImportStatusUseCase,
        private readonly ExportUsersUseCase $exportUsersUseCase,
        private readonly ListUsersUseCase $listUsersUseCase,
        private readonly DeleteUserUseCase $deleteUserUseCase,
        private readonly RestoreUserUseCase $restoreUserUseCase,
        private readonly ForceDeleteUserUseCase $forceDeleteUserUseCase,
        private readonly GetUserStatsUseCase $getUserStatsUseCase,
    ) {}

    /**
     * List users
     *
     * Retrieve users with pagination. Requires admin role.
     * Query params: page (default 1), per_page (default 15, max 100).
     *
     * @group Users
     *
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

    /**
     * Create a user
     *
     * Create a new user. Requires admin role.
     *
     * @group Users
     *
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
     *
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
        } catch (Throwable $e) {
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

        $processor = (string) $request->query('processor', 'go');
        if (! in_array($processor, ['laravel', 'go'], true)) {
            throw new ValidationException('Invalid import processor. Supported processors: laravel, go.');
        }

        $response = $this->importUsersUseCase->execute(new ImportUsersUseCaseRequest($path, $processor));

        if ($response->status === 'failed') {
            throw new ValidationException($response->message ?? 'Import failed.');
        }

        return ApiResponse::accepted([
            'id' => $response->importId,
            'status' => $response->status,
            'processor' => $processor,
        ], 'Import started successfully.');
    }

    /**
     * Get import status
     *
     * Retrieve the progress and status of a user import job. Requires admin role.
     *
     * @group Users
     *
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
     * Cancel an import
     *
     * Cancel a pending or in-progress user import. Requires admin role.
     *
     * @group Users
     *
     * @authenticated
     *
     * @urlParam id integer required The import ID. Example: 1
     *
     * @response 200 {"data":{"id":1,"status":"cancelled"},"message":"Import cancelled."}
     * @response 404 {"message":"Import not found."}
     * @response 409 {"message":"Import already completed."}
     */
    public function cancelImport(int $id): JsonResponse
    {
        $response = $this->cancelImportUseCase->execute(new CancelImportRequest($id));

        return ApiResponse::ok(['id' => $response->id, 'status' => $response->status], 'Import cancelled.');
    }

    /**
     * Download import CSV template
     *
     * Returns a CSV file with the required headers and example rows for bulk user import.
     * Requires admin role.
     *
     * @group Users
     *
     * @authenticated
     *
     * @response 200 scenario="CSV file download" {}
     */
    public function downloadImportTemplate(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['name', 'email', 'role']);
            fputcsv($output, ['John Doe', 'john@example.com', 'user']);
            fputcsv($output, ['Jane Admin', 'jane@example.com', 'admin']);
            fclose($output);
        }, 'users-import-template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * User stats
     *
     * Returns total user count and count of users created today. Values are cached for 5 minutes.
     * Requires admin role.
     *
     * @group Users
     *
     * @authenticated
     *
     * @response 200 {"data":{"total_active":42,"total_deleted":5,"created_today":3}}
     */
    public function stats(): JsonResponse
    {
        $response = $this->getUserStatsUseCase->execute();

        return ApiResponse::ok([
            'total_active' => $response->totalActive,
            'total_deleted' => $response->totalDeleted,
            'created_today' => $response->createdToday,
        ]);
    }

    /**
     * Export users
     *
     * Export all users as a CSV file. Returns a signed download URL. Requires admin role.
     *
     * @group Users
     *
     * @authenticated
     *
     * @response 200 {"path":"exports/users-2025-01-01.csv","url":"http://localhost/api/v1/users/export/download?path=exports/users-2025-01-01.csv&signature=abc123","expires_at":"2025-01-01T00:15:00+00:00"}
     */
    public function export(HttpExportUsersRequest $request): JsonResponse
    {
        /** @var \App\Infrastructure\Persistence\Eloquent\Models\UserModel $admin */
        $admin = auth()->user();

        $dateFrom = $request->filled('date_from')
            ? new DateTimeImmutable($request->input('date_from'))
            : null;

        $dateTo = $request->filled('date_to')
            ? new DateTimeImmutable($request->input('date_to'))
            : null;

        $fields = $request->has('fields') ? $request->input('fields') : null;

        try {
            $response = $this->exportUsersUseCase->execute(new ExportUsersUseCaseRequest(
                adminId: $admin->id,
                dateFrom: $dateFrom,
                dateTo: $dateTo,
                fields: $fields,
            ));
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

        $expiresAt = now()->addMinutes(15);
        $url = URL::temporarySignedRoute(
            'export.download',
            $expiresAt,
            ['path' => $response->path]
        );

        Cache::put("exports:users:last:{$admin->id}", [
            'path' => $response->path,
            'url' => $url,
            'expires_at' => $expiresAt->format('c'),
        ], 15 * 60);

        return ApiResponse::ok([
            'path' => $response->path,
            'url' => $url,
            'expires_at' => $expiresAt->format('c'),
        ]);
    }

    /**
     * Download export
     *
     * Download an exported CSV file via a signed URL. Requires admin role.
     *
     * @group Users
     *
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

    /**
     * Last export metadata
     *
     * Returns metadata (path, url, expires_at) for the most recent export generated by the
     * authenticated admin. Returns null if no export has been generated yet or if the cache
     * has expired (15 minutes after the export was created). Requires admin role.
     *
     * @group Users
     *
     * @authenticated
     *
     * @response 200 {"data":{"path":"exports/users-2025-01-01.csv","url":"http://…","expires_at":"2025-01-01T00:15:00+00:00"}}
     * @response 200 scenario="no recent export" {"data":null}
     */
    public function lastExport(): JsonResponse
    {
        /** @var \App\Infrastructure\Persistence\Eloquent\Models\UserModel $admin */
        $admin = auth()->user();
        $cached = Cache::get("exports:users:last:{$admin->id}");

        return ApiResponse::ok($cached);
    }

    /**
     * Delete a user (soft delete)
     *
     * Soft-delete a user by setting deleted_at. Requires admin role.
     *
     * @group Users
     *
     * @authenticated
     *
     * @urlParam id integer required The user ID. Example: 1
     *
     * @response 200 {"data":null,"message":"User deleted successfully"}
     * @response 404 {"message":"User not found"}
     */
    public function update(HttpUpdateUserRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();

        $response = $this->updateUserUseCase->execute(new UpdateUserUseCaseRequest(
            id: $id,
            version: (int) $validated['version'],
            name: $validated['name'] ?? null,
            email: $validated['email'] ?? null,
            password: $validated['password'] ?? null,
        ));

        return ApiResponse::ok([
            'id' => $response->id,
            'name' => $response->name,
            'email' => $response->email,
            'version' => $response->version,
            'updated_at' => $response->updatedAt->format('Y-m-d H:i:s'),
        ], 'User updated successfully');
    }

    /**
     * @response 404 {"message":"User not found"}
     */
    public function destroy(int $id): JsonResponse
    {
        $response = $this->deleteUserUseCase->execute(
            new DeleteUserRequest($id)
        );

        return ApiResponse::ok(null, $response->message);
    }

    /**
     * Restore a soft-deleted user
     *
     * Restore a previously soft-deleted user. Requires admin role.
     *
     * @group Users
     *
     * @authenticated
     *
     * @urlParam id integer required The user ID. Example: 1
     *
     * @response 200 {"data":{"id":1,"name":"John Doe","email":"john@example.com","avatar_url":null,"role":"user","created_at":"2025-01-01 00:00:00"},"message":"User restored successfully"}
     * @response 404 {"message":"Trashed user not found"}
     */
    public function restore(int $id): JsonResponse
    {
        $response = $this->restoreUserUseCase->execute(
            new RestoreUserRequest($id)
        );

        return ApiResponse::ok(new UserResource($response->user), $response->message);
    }

    /**
     * Force delete a user (permanent)
     *
     * Permanently delete a user. Cannot be undone. Requires admin role.
     *
     * @group Users
     *
     * @authenticated
     *
     * @urlParam id integer required The user ID. Example: 1
     *
     * @response 200 {"data":null,"message":"User permanently deleted"}
     * @response 404 {"message":"User not found"}
     */
    public function forceDestroy(int $id): JsonResponse
    {
        $response = $this->forceDeleteUserUseCase->execute(
            new ForceDeleteUserRequest($id)
        );

        return ApiResponse::ok(null, $response->message);
    }
}
