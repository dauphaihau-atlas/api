<?php

namespace App\Core\Application\UseCases\User\RestoreUser;

use App\Core\Application\Contracts\UserRepositoryInterface;
use App\Exceptions\NotFoundException;
use Illuminate\Support\Facades\DB;

class RestoreUserUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    public function execute(RestoreUserRequest $request): RestoreUserResponse
    {
        $restoredUser = DB::transaction(function () use ($request) {
            $trashedUser = $this->userRepository->findTrashedById($request->userId);
            if ($trashedUser === null) {
                throw new NotFoundException('Trashed user not found');
            }

            $this->userRepository->restore($request->userId);

            $restoredUser = $this->userRepository->findById($request->userId);
            if ($restoredUser === null) {
                throw new NotFoundException('User not found after restoration');
            }

            return $restoredUser;
        });

        return new RestoreUserResponse($restoredUser);
    }
}
