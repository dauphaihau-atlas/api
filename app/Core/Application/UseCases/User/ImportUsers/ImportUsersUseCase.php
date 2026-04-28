<?php

namespace App\Core\Application\UseCases\User\ImportUsers;

use App\Core\Application\Contracts\UserImportRepositoryInterface;
use App\Core\Application\Services\UserImportProcessorResolver;
use App\Core\Domain\Entities\UserImport;
use App\Core\Domain\Enums\ImportStatus;
use App\Exceptions\ValidationException;
use App\Infrastructure\Import\ImportPreparationException;
use App\Infrastructure\Tenant\TenantContext;
use Illuminate\Support\Str;

class ImportUsersUseCase
{
    public function __construct(
        private readonly UserImportRepositoryInterface $importRepository,
        private readonly UserImportProcessorResolver $processors,
        private readonly TenantContext $tenantContext,
    ) {}

    public function execute(ImportUsersRequest $request): ImportUsersResponse
    {
        $processor = $request->processor;
        if (! in_array($processor, ['laravel', 'go'], true)) {
            throw new ValidationException('Invalid import processor. Supported processors: laravel, go.');
        }

        $import = new UserImport(
            id: null,
            batchId: Str::uuid()->toString(),
            filePath: $request->path,
            status: ImportStatus::Pending,
            totalRows: 0,
            tenantId: $this->tenantContext->getTenantId(),
            processor: $processor,
        );

        $import = $this->importRepository->save($import);

        try {
            $this->processors->resolve($processor)->start($import);
        } catch (ImportPreparationException $exception) {
            $this->importRepository->updateStatus($import->getId(), ImportStatus::Failed);

            return new ImportUsersResponse(null, ImportStatus::Failed->value, $exception->getMessage());
        }

        $this->importRepository->updateStatus($import->getId(), ImportStatus::Processing);

        return new ImportUsersResponse($import->getId(), ImportStatus::Processing->value);
    }
}
