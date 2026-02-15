<?php

namespace App\Core\Application\UseCases\User\UpdateUserAvatar;

use App\Core\Application\Contracts\UserRepositoryInterface;
use App\Core\Domain\Entities\User;
use App\Exceptions\NotFoundException;

class UpdateUserAvatarUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {
    }

    public function execute(int $userId, string $avatarPath): User
    {
        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            throw new NotFoundException('User not found');
        }

        $user->updateAvatarPath($avatarPath);

        return $this->userRepository->save($user);
    }
}
