<?php

namespace App\Jobs;

use App\Core\Application\Contracts\UserImportRepositoryInterface;
use App\Core\Application\Contracts\UserRepositoryInterface;
use App\Infrastructure\Broadcasting\Events\ImportProgressUpdated;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class ProcessImportChunk implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 3;

    private const MIN_PASSWORD_LENGTH = 8;

    /**
     * @param  array<int, array{name: string, email: string, password: string}>  $rows
     * @param  int  $startRowIndex  Row number of the first row in this chunk (for error reporting)
     */
    public function __construct(
        public readonly int $importId,
        public readonly array $rows,
        public readonly int $startRowIndex
    ) {}

    public function handle(
        UserRepositoryInterface $userRepository,
        UserImportRepositoryInterface $importRepository
    ): void {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $errors = [];
        $validRows = [];
        $now = now()->toDateTimeString();

        foreach ($this->rows as $index => $row) {
            $rowNumber = $this->startRowIndex + $index;
            $name = trim($row['name'] ?? '');
            $email = trim($row['email'] ?? '');
            $password = trim($row['password'] ?? '');

            $rowError = $this->validateRow($name, $email, $password, $rowNumber);
            if ($rowError !== null) {
                $errors[] = $rowError;

                continue;
            }

            $validRows[] = [
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $created = 0;
        $updated = 0;

        if ($validRows !== []) {
            DB::transaction(function () use ($validRows, $userRepository, &$created, &$updated): void {
                $result = $userRepository->upsertBatch($validRows);
                $created = $result['created'];
                $updated = $result['updated'];
            });
            Cache::tags(['users'])->flush();
            Cache::increment('version:users');
        }

        $importRepository->addChunkResult(
            $this->importId,
            count($this->rows),
            $created,
            $updated,
            $errors
        );

        $freshImport = $importRepository->findById($this->importId);
        if ($freshImport !== null) {
            $percentage = $freshImport->getTotalRows() > 0
                ? round(($freshImport->getProcessedRows() / $freshImport->getTotalRows()) * 100, 2)
                : 0;

            ImportProgressUpdated::dispatch(
                $this->importId,
                $freshImport->getTotalRows(),
                $freshImport->getProcessedRows(),
                $freshImport->getCreatedCount(),
                $freshImport->getUpdatedCount(),
                count($freshImport->getErrors()),
                $percentage
            );
        }

        Log::info('Import chunk processed', [
            'import_id' => $this->importId,
            'rows' => count($this->rows),
            'created' => $created,
            'updated' => $updated,
            'errors' => count($errors),
        ]);
    }

    /**
     * @return array{row: int, message: string}|null
     */
    private function validateRow(string $name, string $email, string $password, int $rowNumber): ?array
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
        if ($password === '') {
            return ['row' => $rowNumber, 'message' => 'Password is required.'];
        }
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            return ['row' => $rowNumber, 'message' => 'Password must be at least '.self::MIN_PASSWORD_LENGTH.' characters.'];
        }

        return null;
    }
}
