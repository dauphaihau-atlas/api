<?php

namespace App\Core\Domain\Entities;

use App\Core\Domain\Enums\ImportStatus;
use DateTimeImmutable;

class UserImport
{
    public function __construct(
        private ?int $id,
        private string $batchId,
        private string $filePath,
        private ImportStatus $status,
        private int $totalRows = 0,
        private int $processedRows = 0,
        private int $createdCount = 0,
        private int $updatedCount = 0,
        private array $errors = [],
        private ?int $tenantId = null,
        private string $processor = 'go',
        private ?DateTimeImmutable $startedAt = null,
        private ?DateTimeImmutable $completedAt = null,
        private ?DateTimeImmutable $createdAt = null,
        private ?DateTimeImmutable $updatedAt = null
    ) {
        $this->createdAt = $createdAt ?? new DateTimeImmutable;
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBatchId(): string
    {
        return $this->batchId;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getStatus(): ImportStatus
    {
        return $this->status;
    }

    public function getTotalRows(): int
    {
        return $this->totalRows;
    }

    public function getProcessedRows(): int
    {
        return $this->processedRows;
    }

    public function getCreatedCount(): int
    {
        return $this->createdCount;
    }

    public function getUpdatedCount(): int
    {
        return $this->updatedCount;
    }

    /**
     * @return array<int, array{row: int, message: string}>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getTenantId(): ?int
    {
        return $this->tenantId;
    }

    public function getProcessor(): string
    {
        return $this->processor;
    }

    public function getStartedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function markAsProcessing(): void
    {
        $this->status = ImportStatus::Processing;
        $this->startedAt = new DateTimeImmutable;
        $this->updatedAt = new DateTimeImmutable;
    }

    public function markAsCompleted(): void
    {
        $this->status = ImportStatus::Completed;
        $this->completedAt = new DateTimeImmutable;
        $this->updatedAt = new DateTimeImmutable;
    }

    public function markAsFailed(): void
    {
        $this->status = ImportStatus::Failed;
        $this->completedAt = new DateTimeImmutable;
        $this->updatedAt = new DateTimeImmutable;
    }

    public function markAsCancelled(): void
    {
        $this->status = ImportStatus::Cancelled;
        $this->completedAt = new DateTimeImmutable;
        $this->updatedAt = new DateTimeImmutable;
    }

    public function updateBatchId(string $batchId): void
    {
        $this->batchId = $batchId;
        $this->updatedAt = new DateTimeImmutable;
    }
}
