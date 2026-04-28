<?php

namespace App\Infrastructure\Import;

use App\Core\Application\Contracts\UserImportProcessorInterface;
use App\Core\Domain\Entities\UserImport;
use App\Exceptions\ServiceUnavailableException;
use Illuminate\Support\Facades\Http;

class GoWorkerUserImportProcessor implements UserImportProcessorInterface
{
    public function start(UserImport $import): void
    {
        $url = rtrim((string) config('services.go_worker.url'), '/');
        if ($url === '') {
            throw new ServiceUnavailableException('Go worker URL is not configured.', 'GO_WORKER_NOT_CONFIGURED');
        }

        $response = Http::timeout(10)
            ->acceptJson()
            ->post($url.'/user-imports', [
                'import_id' => $import->getId(),
                'file_path' => $import->getFilePath(),
                'tenant_id' => $import->getTenantId(),
            ]);

        if (! $response->successful()) {
            throw new ServiceUnavailableException('Failed to start Go user import processor.', 'GO_WORKER_IMPORT_START_FAILED');
        }
    }
}
