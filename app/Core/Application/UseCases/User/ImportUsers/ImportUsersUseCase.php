<?php

namespace App\Core\Application\UseCases\User\ImportUsers;

use App\Core\Application\Contracts\UserImportRepositoryInterface;
use App\Core\Domain\Entities\UserImport;
use App\Jobs\ProcessImportChunk;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportUsersUseCase
{
    private const REQUIRED_HEADERS = ['name', 'email', 'password'];

    private const CHUNK_SIZE = 500;

    public function __construct(
        private readonly UserImportRepositoryInterface $importRepository
    ) {
    }

    public function execute(ImportUsersRequest $request): ImportUsersResponse
    {
        $disk = Storage::disk(config('filesystems.imports_disk', 'local'));

        // --- Pass 1: validate headers and count rows (O(1) memory) ---
        // Use $disk->readStream() which works for both local and S3/MinIO disks.
        $stream = $disk->readStream($request->path);
        if ($stream === null) {
            return new ImportUsersResponse(null, 'failed', 'Failed to open import file.');
        }

        $headerRow = fgetcsv($stream);
        if ($headerRow === false) {
            fclose($stream);

            return new ImportUsersResponse(null, 'failed', 'Empty CSV file.');
        }

        $headerMap = $this->normalizeAndValidateHeaders($headerRow);
        if ($headerMap === null) {
            fclose($stream);

            return new ImportUsersResponse(null, 'failed', 'Invalid or missing CSV headers. Required: name, email, password.');
        }

        $totalRows = 0;
        while (($row = fgetcsv($stream)) !== false) {
            if (! $this->isEmptyRow($row)) {
                $totalRows++;
            }
        }
        fclose($stream);

        if ($totalRows === 0) {
            return new ImportUsersResponse(null, 'failed', 'CSV file contains no data rows.');
        }

        // Create import tracking record
        $import = new UserImport(
            id: null,
            batchId: Str::uuid()->toString(),
            filePath: $request->path,
            status: 'pending',
            totalRows: $totalRows
        );
        $import = $this->importRepository->save($import);
        $importId = $import->getId();

        // Dispatch an empty batch with callbacks
        $batch = Bus::batch([])
            ->name("User Import #{$importId}")
            ->then(function (Batch $batch) use ($importId): void {
                app(UserImportRepositoryInterface::class)->updateStatus($importId, 'completed');
            })
            ->catch(function (Batch $batch, \Throwable $e) use ($importId): void {
                Log::error('Import batch failed', [
                    'import_id' => $importId,
                    'error' => $e->getMessage(),
                ]);
                app(UserImportRepositoryInterface::class)->updateStatus($importId, 'failed');
            })
            ->dispatch();

        // --- Pass 2: stream file again, build and add jobs to batch in chunks (O(CHUNK_SIZE) memory) ---
        $stream = $disk->readStream($request->path);
        fgetcsv($stream); // skip header

        $currentChunk = [];
        $rowIndex = 2;
        $chunkStartRow = 2;

        while (($row = fgetcsv($stream)) !== false) {
            if ($this->isEmptyRow($row)) {
                $rowIndex++;

                continue;
            }

            $currentChunk[] = [
                'name' => (string) ($row[$headerMap['name']] ?? ''),
                'email' => (string) ($row[$headerMap['email']] ?? ''),
                'password' => (string) ($row[$headerMap['password']] ?? ''),
            ];

            if (count($currentChunk) >= self::CHUNK_SIZE) {
                $batch->add([new ProcessImportChunk(
                    importId: $importId,
                    rows: $currentChunk,
                    startRowIndex: $chunkStartRow
                )]);
                $currentChunk = [];
                $chunkStartRow = $rowIndex + 1;
            }

            $rowIndex++;
        }

        // Remaining rows
        if ($currentChunk !== []) {
            $batch->add([new ProcessImportChunk(
                importId: $importId,
                rows: $currentChunk,
                startRowIndex: $chunkStartRow
            )]);
        }

        fclose($stream);

        $this->importRepository->updateStatus($importId, 'processing');

        return new ImportUsersResponse($importId, 'processing');
    }

    /**
     * @param  array<int, string>  $headerRow
     * @return array{name: int, email: int, password: int}|null
     */
    private function normalizeAndValidateHeaders(array $headerRow): ?array
    {
        $map = [];
        foreach ($headerRow as $index => $cell) {
            $key = strtolower(trim((string) $cell));
            if ($key !== '') {
                $map[$key] = $index;
            }
        }
        foreach (self::REQUIRED_HEADERS as $required) {
            if (! isset($map[$required])) {
                return null;
            }
        }

        return [
            'name' => $map['name'],
            'email' => $map['email'],
            'password' => $map['password'],
        ];
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
