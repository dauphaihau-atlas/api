<?php

namespace App\Core\Application\Contracts;

use App\Core\Domain\Entities\UserImport;
use App\Core\Domain\Enums\ImportStatus;

interface UserImportRepositoryInterface
{
    public function save(UserImport $import): UserImport;

    public function findById(int $id): ?UserImport;

    /**
     * Atomically increment chunk results (processed rows, created/updated counts, errors).
     *
     * @param  array<int, array{row: int, message: string}>  $errors
     */
    public function addChunkResult(int $id, int $processedRows, int $created, int $updated, array $errors): void;

    public function updateTotalRows(int $id, int $totalRows): void;

    public function updateStatus(int $id, ImportStatus $status): void;
}
