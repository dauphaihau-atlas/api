<?php

namespace App\Core\Application\UseCases\User\GetImportStatus;

class GetImportStatusRequest
{
    public function __construct(
        public readonly int $importId
    ) {
    }
}
