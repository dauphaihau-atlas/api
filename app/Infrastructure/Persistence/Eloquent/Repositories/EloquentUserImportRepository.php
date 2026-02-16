<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Core\Application\Contracts\UserImportRepositoryInterface;
use App\Core\Domain\Entities\UserImport;
use App\Infrastructure\Persistence\Eloquent\Models\UserImportModel;
use Illuminate\Support\Facades\DB;

class EloquentUserImportRepository implements UserImportRepositoryInterface
{
    public function save(UserImport $import): UserImport
    {
        if ($import->getId() === null) {
            $model = new UserImportModel();
        } else {
            $model = UserImportModel::findOrFail($import->getId());
        }

        $model->batch_id = $import->getBatchId();
        $model->file_path = $import->getFilePath();
        $model->status = $import->getStatus();
        $model->total_rows = $import->getTotalRows();
        $model->processed_rows = $import->getProcessedRows();
        $model->created_count = $import->getCreatedCount();
        $model->updated_count = $import->getUpdatedCount();
        $model->errors = $import->getErrors();
        $model->started_at = $import->getStartedAt();
        $model->completed_at = $import->getCompletedAt();
        $model->save();

        return $this->toEntity($model);
    }

    public function findById(int $id): ?UserImport
    {
        $model = UserImportModel::find($id);

        return $model !== null ? $this->toEntity($model) : null;
    }

    public function addChunkResult(int $id, int $processedRows, int $created, int $updated, array $errors): void
    {
        $model = UserImportModel::findOrFail($id);

        UserImportModel::where('id', $id)->update([
            'processed_rows' => DB::raw("processed_rows + {$processedRows}"),
            'created_count' => DB::raw("created_count + {$created}"),
            'updated_count' => DB::raw("updated_count + {$updated}"),
        ]);

        if ($errors !== []) {
            $existingErrors = $model->errors ?? [];
            $mergedErrors = array_merge($existingErrors, $errors);
            UserImportModel::where('id', $id)->update([
                'errors' => json_encode($mergedErrors),
            ]);
        }
    }

    public function updateStatus(int $id, string $status): void
    {
        $data = ['status' => $status];

        if ($status === 'processing') {
            $data['started_at'] = now();
        }

        if ($status === 'completed' || $status === 'failed') {
            $data['completed_at'] = now();
        }

        UserImportModel::where('id', $id)->update($data);
    }

    private function toEntity(UserImportModel $model): UserImport
    {
        return new UserImport(
            id: $model->id,
            batchId: $model->batch_id,
            filePath: $model->file_path,
            status: $model->status,
            totalRows: $model->total_rows,
            processedRows: $model->processed_rows,
            createdCount: $model->created_count,
            updatedCount: $model->updated_count,
            errors: $model->errors ?? [],
            startedAt: $model->started_at?->toDateTimeImmutable(),
            completedAt: $model->completed_at?->toDateTimeImmutable(),
            createdAt: $model->created_at?->toDateTimeImmutable(),
            updatedAt: $model->updated_at?->toDateTimeImmutable()
        );
    }
}
