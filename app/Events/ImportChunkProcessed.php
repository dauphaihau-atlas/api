<?php

declare(strict_types=1);

namespace App\Events;

use App\Core\Domain\Entities\UserImport;
use Illuminate\Foundation\Events\Dispatchable;

class ImportChunkProcessed
{
    use Dispatchable;

    public function __construct(public readonly UserImport $import) {}
}
