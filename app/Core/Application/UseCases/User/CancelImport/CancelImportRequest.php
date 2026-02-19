<?php

declare(strict_types=1);

namespace App\Core\Application\UseCases\User\CancelImport;

class CancelImportRequest
{
    public function __construct(public readonly int $importId) {}
}
