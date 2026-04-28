<?php

namespace App\Core\Application\Services;

use App\Core\Application\Contracts\UserImportProcessorInterface;
use App\Exceptions\ValidationException;
use App\Infrastructure\Import\GoWorkerUserImportProcessor;
use App\Infrastructure\Import\LaravelQueueUserImportProcessor;

class UserImportProcessorResolver
{
    public function __construct(
        private readonly LaravelQueueUserImportProcessor $laravelProcessor,
        private readonly GoWorkerUserImportProcessor $goProcessor,
    ) {}

    public function resolve(string $processor): UserImportProcessorInterface
    {
        return match ($processor) {
            'laravel' => $this->laravelProcessor,
            'go' => $this->goProcessor,
            default => throw new ValidationException('Invalid import processor. Supported processors: laravel, go.'),
        };
    }
}
