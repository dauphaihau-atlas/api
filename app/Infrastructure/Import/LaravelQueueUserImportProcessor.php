<?php

namespace App\Infrastructure\Import;

use App\Core\Application\Contracts\UserImportProcessorInterface;
use App\Core\Application\Contracts\UserImportRepositoryInterface;
use App\Core\Domain\Entities\UserImport;
use App\Core\Domain\Enums\ImportStatus;
use App\Events\ImportCompleted;
use App\Jobs\ProcessImportChunk;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class LaravelQueueUserImportProcessor implements UserImportProcessorInterface
{
    private const REQUIRED_HEADERS = ['name', 'email', 'password'];

    private const CHUNK_SIZE = 500;

    public function __construct(
        private readonly UserImportRepositoryInterface $importRepository,
    ) {}

    public function start(UserImport $import): void
    {
        $disk = Storage::disk(config('filesystems.imports_disk', 'local'));
        $stream = $disk->readStream($import->getFilePath());
        if ($stream === null) {
            throw new ImportPreparationException('Failed to open import file.');
        }

        $headerRow = fgetcsv($stream);
        if ($headerRow === false) {
            fclose($stream);
            throw new ImportPreparationException('Empty CSV file.');
        }

        $headerMap = $this->normalizeAndValidateHeaders($headerRow);
        if ($headerMap === null) {
            fclose($stream);
            throw new ImportPreparationException('Invalid or missing CSV headers. Required: name, email, password.');
        }

        $totalRows = 0;
        while (($row = fgetcsv($stream)) !== false) {
            if (! $this->isEmptyRow($row)) {
                $totalRows++;
            }
        }
        fclose($stream);

        if ($totalRows === 0) {
            throw new ImportPreparationException('CSV file contains no data rows.');
        }

        $this->importRepository->updateTotalRows($import->getId(), $totalRows);

        $batch = Bus::batch([])
            ->name("User Import #{$import->getId()}")
            ->then(function (Batch $batch) use ($import): void {
                $repo = app(UserImportRepositoryInterface::class);
                $repo->updateStatus($import->getId(), ImportStatus::Completed);

                $freshImport = $repo->findById($import->getId());
                if ($freshImport !== null) {
                    ImportCompleted::dispatch($freshImport);
                }
            })
            ->catch(function (Batch $batch, Throwable $e) use ($import): void {
                Log::error('Import batch failed', [
                    'import_id' => $import->getId(),
                    'error' => $e->getMessage(),
                ]);

                $repo = app(UserImportRepositoryInterface::class);
                $repo->updateStatus($import->getId(), ImportStatus::Failed);

                $freshImport = $repo->findById($import->getId());
                if ($freshImport !== null) {
                    ImportCompleted::dispatch($freshImport);
                }
            })
            ->dispatch();

        $stream = $disk->readStream($import->getFilePath());
        fgetcsv($stream);

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
                    importId: $import->getId(),
                    rows: $currentChunk,
                    startRowIndex: $chunkStartRow,
                    tenantId: $import->getTenantId(),
                )]);
                $currentChunk = [];
                $chunkStartRow = $rowIndex + 1;
            }

            $rowIndex++;
        }

        if ($currentChunk !== []) {
            $batch->add([new ProcessImportChunk(
                importId: $import->getId(),
                rows: $currentChunk,
                startRowIndex: $chunkStartRow,
                tenantId: $import->getTenantId(),
            )]);
        }

        fclose($stream);
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
