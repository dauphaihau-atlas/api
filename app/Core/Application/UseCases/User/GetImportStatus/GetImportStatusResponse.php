<?php

namespace App\Core\Application\UseCases\User\GetImportStatus;

class GetImportStatusResponse
{
    /**
     * @param  array<int, array{row: int, message: string}>  $errors
     */
    public function __construct(
        public readonly int $id,
        public readonly string $status,
        public readonly int $totalRows,
        public readonly int $processedRows,
        public readonly int $createdCount,
        public readonly int $updatedCount,
        public readonly array $errors,
        public readonly ?string $startedAt,
        public readonly ?string $completedAt
    ) {}
}
