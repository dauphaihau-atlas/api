<?php

declare(strict_types=1);

namespace App\Core\Application\UseCases\User\GetUserStats;

use App\Core\Application\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class GetUserStatsUseCase
{
    private const TTL_SECONDS = 300;

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    public function execute(): GetUserStatsResponse
    {
        $totalActive = Cache::tags(['users'])->remember(
            'users:stats:total_active',
            self::TTL_SECONDS,
            fn () => $this->userRepository->countActive()
        );

        $totalDeleted = Cache::tags(['users'])->remember(
            'users:stats:total_deleted',
            self::TTL_SECONDS,
            fn () => $this->userRepository->countTrashed()
        );

        $today = Cache::tags(['users'])->remember(
            'users:stats:created_today',
            self::TTL_SECONDS,
            fn () => $this->userRepository->countCreatedToday()
        );

        return new GetUserStatsResponse((int) $totalActive, (int) $totalDeleted, (int) $today);
    }
}
