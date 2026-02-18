<?php

declare(strict_types=1);

namespace App\Core\Application\UseCases\User\ListUsers;

use App\Core\Application\Contracts\UserRepositoryInterface;

class ListUsersUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    public function execute(ListUsersRequest $request): ListUsersResponse
    {
        $page = max(1, $request->page);
        $perPage = min(max(1, $request->perPage), 100);

        $users = $this->userRepository->findPaginated(
            $request->filters,
            $page,
            $perPage
        );
        $total = $this->userRepository->count($request->filters);

        return new ListUsersResponse(
            users: $users,
            total: $total,
            perPage: $perPage,
            currentPage: $page,
        );
    }
}
