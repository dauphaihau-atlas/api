<?php

declare(strict_types=1);

namespace App\Core\Application\UseCases\ActivityLog\GetActivityLog;

use App\Core\Application\Contracts\ActivityLogRepositoryInterface;
use App\Exceptions\NotFoundException;

class GetActivityLogUseCase
{
    public function __construct(
        private readonly ActivityLogRepositoryInterface $activityLogRepository
    ) {}

    public function execute(GetActivityLogRequest $request): GetActivityLogResponse
    {
        $entry = $this->activityLogRepository->findById($request->id);

        if ($entry === null) {
            throw new NotFoundException('Activity log not found.');
        }

        return new GetActivityLogResponse(entry: $entry);
    }
}
