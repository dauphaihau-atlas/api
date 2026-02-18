<?php

namespace App\Core\Application\UseCases\User\ForceDeleteUser;

use App\Core\Application\Contracts\UserRepositoryInterface;
use App\Exceptions\NotFoundException;

class ForceDeleteUserUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    public function execute(ForceDeleteUserRequest $request): ForceDeleteUserResponse
    {
        $user = $this->userRepository->findTrashedById($request->userId)
          ?? $this->userRepository->findById($request->userId);

        if ($user === null) {
            throw new NotFoundException('User not found');
        }

        $this->userRepository->forceDelete($request->userId);

        return new ForceDeleteUserResponse($request->userId);
    }
}
