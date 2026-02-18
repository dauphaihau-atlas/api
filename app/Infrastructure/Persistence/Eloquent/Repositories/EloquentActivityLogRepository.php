<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Core\Application\Contracts\ActivityLogRepositoryInterface;
use App\Core\Application\DTOs\ActivityLogEntry;
use App\Core\Application\DTOs\ActivityLogFilters;
use App\Infrastructure\Persistence\Eloquent\Models\ActivityLogModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeInterface;

class EloquentActivityLogRepository implements ActivityLogRepositoryInterface
{
    /**
     * @return ActivityLogEntry[]
     */
    public function findPaginated(ActivityLogFilters $filters, int $page, int $perPage): array
    {
        $query = $this->applyFilters(ActivityLogModel::query()->with('causer'), $filters);

        $sortColumn = $filters->sort !== '' && str_starts_with($filters->sort, '-')
            ? substr($filters->sort, 1)
            : $filters->sort;
        $sortColumn = $this->validateSortColumn($sortColumn);
        $sortDir = str_starts_with($filters->sort, '-') ? 'desc' : 'asc';

        $offset = max(0, ($page - 1) * $perPage);
        $models = $query->orderBy($sortColumn, $sortDir)
            ->offset($offset)
            ->limit($perPage)
            ->get();

        return array_map(
            fn (ActivityLogModel $model) => $this->toEntry($model),
            $models->all()
        );
    }

    public function count(ActivityLogFilters $filters): int
    {
        return $this->applyFilters(ActivityLogModel::query(), $filters)->count();
    }

    public function findById(int $id): ?ActivityLogEntry
    {
        $model = ActivityLogModel::with('causer')->find($id);

        return $model !== null ? $this->toEntry($model) : null;
    }

    private function applyFilters(\Illuminate\Database\Eloquent\Builder $query, ActivityLogFilters $filters): \Illuminate\Database\Eloquent\Builder
    {
        if ($filters->event !== null && $filters->event !== '') {
            $query->where('event', $filters->event);
        }
        if ($filters->subjectType !== null && $filters->subjectType !== '') {
            $query->where('subject_type', $filters->subjectType);
        }
        if ($filters->subjectId !== null) {
            $query->where('subject_id', $filters->subjectId);
        }
        if ($filters->causerId !== null) {
            $query->where('causer_id', $filters->causerId);
        }
        if ($filters->fromDate !== null && $filters->fromDate !== '') {
            $query->whereDate('created_at', '>=', $filters->fromDate);
        }
        if ($filters->toDate !== null && $filters->toDate !== '') {
            $query->whereDate('created_at', '<=', $filters->toDate);
        }

        return $query;
    }

    private function validateSortColumn(string $column): string
    {
        $allowed = ['id', 'log_name', 'event', 'subject_type', 'subject_id', 'causer_type', 'causer_id', 'created_at', 'updated_at'];

        return in_array($column, $allowed, true) ? $column : 'created_at';
    }

    private function toEntry(ActivityLogModel $model): ActivityLogEntry
    {
        $causerName = null;
        $causerEmail = null;
        $causer = $model->getRelationValue('causer');
        if ($causer instanceof UserModel) {
            $causerName = $causer->name ?? null;
            $causerEmail = $causer->email ?? null;
        }

        $createdAt = $model->created_at;
        if (! $createdAt instanceof DateTimeInterface) {
            $createdAt = \Carbon\Carbon::parse($model->created_at);
        }

        return new ActivityLogEntry(
            id: $model->id,
            logName: $model->log_name,
            event: $model->event,
            subjectType: $model->subject_type,
            subjectId: (int) $model->subject_id,
            causerType: $model->causer_type,
            causerId: $model->causer_id !== null ? (int) $model->causer_id : null,
            properties: $model->properties,
            createdAt: $createdAt,
            causerName: $causerName,
            causerEmail: $causerEmail,
        );
    }
}
