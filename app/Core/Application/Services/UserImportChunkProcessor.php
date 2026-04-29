<?php

namespace App\Core\Application\Services;

use App\Core\Application\Contracts\UserImportRepositoryInterface;
use App\Core\Application\Contracts\UserRepositoryInterface;
use App\Events\ImportChunkProcessed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class UserImportChunkProcessor
{
    private const ALLOWED_ROLES = ['admin', 'user'];

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserImportRepositoryInterface $importRepository,
    ) {}

    /**
     * @param  array<int, array{name: string, email: string, role: string}>  $rows
     */
    public function process(int $importId, array $rows, int $startRowIndex, ?int $tenantId): void
    {
        $errors = [];
        $validRows = [];
        $now = now()->toDateTimeString();

        foreach ($rows as $index => $row) {
            $rowNumber = $startRowIndex + $index;
            $name = trim($row['name'] ?? '');
            $email = trim($row['email'] ?? '');
            $role = strtolower(trim($row['role'] ?? ''));

            $rowError = $this->validateRow($name, $email, $role, $rowNumber);
            if ($rowError !== null) {
                $errors[] = $rowError;

                continue;
            }

            $validRows[] = [
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'password' => Hash::make(Str::password(48)),
                'invitation_token' => Str::random(64),
                'tenant_id' => $tenantId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $created = 0;
        $updated = 0;

        if ($validRows !== []) {
            DB::transaction(function () use ($validRows, &$created, &$updated): void {
                $result = $this->userRepository->upsertBatch($validRows);
                $created = $result['created'];
                $updated = $result['updated'];
            });
            Cache::tags(['users'])->flush();
            Cache::increment('version:users');
        }

        $this->importRepository->addChunkResult(
            $importId,
            count($rows),
            $created,
            $updated,
            $errors
        );

        $freshImport = $this->importRepository->findById($importId);
        if ($freshImport !== null) {
            try {
                ImportChunkProcessed::dispatch($freshImport);
            } catch (Throwable $exception) {
                Log::warning('Import progress broadcast failed', [
                    'import_id' => $importId,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        Log::info('Import chunk processed', [
            'import_id' => $importId,
            'rows' => count($rows),
            'created' => $created,
            'updated' => $updated,
            'errors' => count($errors),
        ]);
    }

    /**
     * @return array{row: int, message: string}|null
     */
    private function validateRow(string $name, string $email, string $role, int $rowNumber): ?array
    {
        if ($name === '') {
            return ['row' => $rowNumber, 'message' => 'Name is required.'];
        }
        if ($email === '') {
            return ['row' => $rowNumber, 'message' => 'Email is required.'];
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['row' => $rowNumber, 'message' => 'Invalid email.'];
        }
        if ($role === '') {
            return ['row' => $rowNumber, 'message' => 'Role is required.'];
        }
        if (! in_array($role, self::ALLOWED_ROLES, true)) {
            return ['row' => $rowNumber, 'message' => 'Invalid role. Supported roles: admin, user.'];
        }

        return null;
    }
}
