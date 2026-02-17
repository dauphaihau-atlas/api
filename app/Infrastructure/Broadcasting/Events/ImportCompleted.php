<?php

namespace App\Infrastructure\Broadcasting\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ImportCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<int, array{row: int, message: string}>  $errors
     */
    public function __construct(
        public readonly int $importId,
        public readonly string $status,
        public readonly int $totalRows,
        public readonly int $processedRows,
        public readonly int $createdCount,
        public readonly int $updatedCount,
        public readonly array $errors
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
        return 'import.completed';
    }
}
