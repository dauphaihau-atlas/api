<?php

namespace App\Infrastructure\Broadcasting\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ImportProgressUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $importId,
        public readonly int $totalRows,
        public readonly int $processedRows,
        public readonly int $createdCount,
        public readonly int $updatedCount,
        public readonly int $errorCount,
        public readonly float $progressPercentage
    ) {}

    /**
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("imports.{$this->importId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'import.progress';
    }
}
