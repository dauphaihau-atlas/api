<?php

namespace App\Core\Application\UseCases\User\GetImportStatus;

use App\Core\Application\Contracts\UserImportRepositoryInterface;

class GetImportStatusUseCase
{
    public function __construct(
        private readonly UserImportRepositoryInterface $importRepository
    ) {}

    public function execute(GetImportStatusRequest $request): ?GetImportStatusResponse
    {
        $import = $this->importRepository->findById($request->importId);

        if ($import === null) {
            return null;
        }

        return new GetImportStatusResponse(
            id: $import->getId(),
            status: $import->getStatus(),
            totalRows: $import->getTotalRows(),
            processedRows: $import->getProcessedRows(),
            createdCount: $import->getCreatedCount(),
            updatedCount: $import->getUpdatedCount(),
            errors: $import->getErrors(),
            startedAt: $import->getStartedAt()?->format('c'),
            completedAt: $import->getCompletedAt()?->format('c')
        );
    }
}
