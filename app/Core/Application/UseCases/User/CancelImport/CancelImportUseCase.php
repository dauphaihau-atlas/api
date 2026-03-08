<?php

declare(strict_types=1);

namespace App\Core\Application\UseCases\User\CancelImport;

use App\Core\Application\Contracts\UserImportRepositoryInterface;
use App\Core\Domain\Enums\ImportStatus;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Infrastructure\Broadcasting\Events\ImportCompleted;
use Illuminate\Support\Facades\Bus;

class CancelImportUseCase
{
    public function __construct(
        private readonly UserImportRepositoryInterface $importRepository
    ) {}

    public function execute(CancelImportRequest $request): CancelImportResponse
    {
        $import = $this->importRepository->findById($request->importId);

        if ($import === null) {
            throw new NotFoundException('Import not found.');
        }

        $status = $import->getStatus();
        if (! in_array($status, [ImportStatus::Pending, ImportStatus::Processing], true)) {
            throw new ConflictException("Import already {$status->value}.");
        }

        $batch = Bus::findBatch($import->getBatchId());
        $batch?->cancel();

        $this->importRepository->updateStatus($import->getId(), ImportStatus::Cancelled);

        ImportCompleted::dispatch(
            $import->getId(),
            ImportStatus::Cancelled->value,
            $import->getTotalRows(),
            $import->getProcessedRows(),
            $import->getCreatedCount(),
            $import->getUpdatedCount(),
            $import->getErrors()
        );

        return new CancelImportResponse($import->getId(), ImportStatus::Cancelled->value);
    }
}
