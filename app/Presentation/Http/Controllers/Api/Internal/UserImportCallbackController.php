<?php

namespace App\Presentation\Http\Controllers\Api\Internal;

use App\Core\Application\Contracts\UserImportRepositoryInterface;
use App\Core\Application\Services\UserImportChunkProcessor;
use App\Core\Domain\Enums\ImportStatus;
use App\Events\ImportCompleted;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Presentation\Http\Controllers\Controller;
use App\Presentation\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserImportCallbackController extends Controller
{
    public function __construct(
        private readonly UserImportRepositoryInterface $imports,
        private readonly UserImportChunkProcessor $chunks,
    ) {}

    public function started(Request $request, int $id): JsonResponse
    {
        $import = $this->imports->findById($id);
        if ($import === null) {
            throw new NotFoundException('Import not found.');
        }

        $totalRows = (int) $request->input('total_rows', 0);
        if ($totalRows < 1) {
            throw new ValidationException('Total rows must be greater than zero.');
        }

        $this->imports->updateTotalRows($id, $totalRows);
        $this->imports->updateStatus($id, ImportStatus::Processing);

        return ApiResponse::ok(['id' => $id, 'status' => ImportStatus::Processing->value]);
    }

    public function downloadFile(Request $request): StreamedResponse
    {
        $path = (string) $request->query('path', '');
        if ($path === '' || str_contains($path, '..')) {
            throw new ValidationException('Valid import file path is required.');
        }

        $disk = Storage::disk(config('filesystems.imports_disk', 'local'));
        $stream = $disk->readStream($path);
        if ($stream === null) {
            throw new NotFoundException('Import file not found.');
        }

        return response()->streamDownload(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, basename($path), [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function chunk(Request $request, int $id): JsonResponse
    {
        $import = $this->imports->findById($id);
        if ($import === null) {
            throw new NotFoundException('Import not found.');
        }

        $rows = $request->input('rows');
        if (! is_array($rows)) {
            throw new ValidationException('Rows must be an array.');
        }

        $startRowIndex = (int) $request->input('start_row_index', 2);
        if ($startRowIndex < 2) {
            throw new ValidationException('Start row index must be at least 2.');
        }

        $this->chunks->process($id, $rows, $startRowIndex, $import->getTenantId());

        return ApiResponse::ok(['id' => $id]);
    }

    public function complete(int $id): JsonResponse
    {
        $import = $this->imports->findById($id);
        if ($import === null) {
            throw new NotFoundException('Import not found.');
        }

        $this->imports->updateStatus($id, ImportStatus::Completed);
        $freshImport = $this->imports->findById($id);
        if ($freshImport !== null) {
            ImportCompleted::dispatch($freshImport);
        }

        return ApiResponse::ok(['id' => $id, 'status' => ImportStatus::Completed->value]);
    }

    public function fail(Request $request, int $id): JsonResponse
    {
        $import = $this->imports->findById($id);
        if ($import === null) {
            throw new NotFoundException('Import not found.');
        }

        $message = (string) $request->input('message', 'Go worker import failed.');
        $this->imports->addChunkResult($id, 0, 0, 0, [['row' => 0, 'message' => $message]]);
        $this->imports->updateStatus($id, ImportStatus::Failed);
        $freshImport = $this->imports->findById($id);
        if ($freshImport !== null) {
            ImportCompleted::dispatch($freshImport);
        }

        return ApiResponse::ok(['id' => $id, 'status' => ImportStatus::Failed->value]);
    }
}
