<?php

namespace App\Core\Application\Contracts;

use App\Core\Domain\Entities\UserImport;

interface UserImportProcessorInterface
{
    public function start(UserImport $import): void;
}
