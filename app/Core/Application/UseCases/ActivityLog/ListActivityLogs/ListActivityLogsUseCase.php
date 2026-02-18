<?php

declare(strict_types=1);

namespace App\Core\Application\UseCases\ActivityLog\ListActivityLogs;

use App\Core\Application\Contracts\ActivityLogRepositoryInterface;

class ListActivityLogsUseCase
{
    public function __construct(
        private readonly ActivityLogRepositoryInterface $activityLogRepository
    ) {}

    public function execute(ListActivityLogsRequest $request): ListActivityLogsResponse
    {
        $page = max(1, $request->page);
        $perPage = min(max(1, $request->perPage), 100);

        $entries = $this->activityLogRepository->findPaginated(
            $request->filters,
            $page,
            $perPage
        );
        $total = $this->activityLogRepository->count($request->filters);

        return new ListActivityLogsResponse(
            entries: $entries,
            total: $total,
            perPage: $perPage,
            currentPage: $page,
        );
    }
}
