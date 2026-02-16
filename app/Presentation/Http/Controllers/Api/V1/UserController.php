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
     * Returns 202 Accepted with import ID for async processing.
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

        return response()->json([
            'id' => $response->importId,
            'status' => $response->status,
            'message' => 'Import started successfully.',
        ], 202);
    }

    /**
     * Get import progress/status (admin only).
     */
    public function importStatus(int $id): JsonResponse
    {
        $response = $this->getImportStatusUseCase->execute(
            new GetImportStatusUseCaseRequest($id)
        );

        if ($response === null) {
            throw new NotFoundException('Import not found.');
        }

        return response()->json([
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
        ], 200);
    }

    /**
     * Export users as CSV (admin only). Writes file to storage and returns download URL.
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

        return response()->json($payload, 200);
    }

    /**
     * Download an export file by path (signed URL; admin only).
     *
     * @return JsonResponse|StreamedResponse
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
