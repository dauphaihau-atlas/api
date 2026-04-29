<?php

namespace App\Jobs;

use App\Core\Application\Services\UserImportChunkProcessor;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessImportChunk implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 3;

    /**
     * @param  array<int, array{name: string, email: string, role: string}>  $rows
     * @param  int  $startRowIndex  Row number of the first row in this chunk (for error reporting)
     */
    public function __construct(
        public readonly int $importId,
        public readonly array $rows,
        public readonly int $startRowIndex,
        public readonly ?int $tenantId = null,
    ) {}

    public function handle(UserImportChunkProcessor $processor): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $processor->process(
            $this->importId,
            $this->rows,
            $this->startRowIndex,
            $this->tenantId,
        );
    }
}
