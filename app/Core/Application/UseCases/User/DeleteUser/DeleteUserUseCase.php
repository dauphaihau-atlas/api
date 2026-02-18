<?php

namespace App\Core\Application\UseCases\User\DeleteUser;

use App\Core\Application\Contracts\UserRepositoryInterface;
use App\Exceptions\NotFoundException;

class DeleteUserUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    public function execute(DeleteUserRequest $request): DeleteUserResponse
    {
        $user = $this->userRepository->findById($request->userId);
        if ($user === null) {
            throw new NotFoundException('User not found');
        }

        $this->userRepository->delete($request->userId);

        return new DeleteUserResponse($request->userId);
    }
}
