<?php

declare(strict_types=1);

namespace App\Core\Application\UseCases\User\CancelImport;

class CancelImportResponse
{
    public function __construct(
        public readonly int $id,
        public readonly string $status
    ) {}
}
