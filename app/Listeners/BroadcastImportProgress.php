<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ImportChunkProcessed;
use App\Infrastructure\Broadcasting\Events\ImportProgressUpdated;

class BroadcastImportProgress
{
    public function handle(ImportChunkProcessed $event): void
    {
        $import = $event->import;

        $percentage = $import->getTotalRows() > 0
            ? round(($import->getProcessedRows() / $import->getTotalRows()) * 100, 2)
            : 0;

        ImportProgressUpdated::dispatch(
            $import->getId(),
            $import->getTotalRows(),
            $import->getProcessedRows(),
            $import->getCreatedCount(),
            $import->getUpdatedCount(),
            count($import->getErrors()),
            $percentage
        );
    }
}
