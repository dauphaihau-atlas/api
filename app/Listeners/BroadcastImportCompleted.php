<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ImportCompleted;
use App\Infrastructure\Broadcasting\Events\ImportCompleted as ImportCompletedBroadcast;

class BroadcastImportCompleted
{
    public function handle(ImportCompleted $event): void
    {
        $import = $event->import;

        ImportCompletedBroadcast::dispatch(
            $import->getId(),
            $import->getStatus()->value,
            $import->getTotalRows(),
            $import->getProcessedRows(),
            $import->getCreatedCount(),
            $import->getUpdatedCount(),
            $import->getErrors()
        );
    }
}
