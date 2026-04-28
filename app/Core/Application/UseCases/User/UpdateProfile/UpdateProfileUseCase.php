<?php

declare(strict_types=1);

namespace App\Core\Application\UseCases\User\UpdateProfile;

use App\Core\Application\Contracts\UserRepositoryInterface;
use App\Core\Domain\ValueObjects\Email;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use Illuminate\Support\Facades\Hash;

class UpdateProfileUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    public function execute(UpdateProfileRequest $request): UpdateProfileResponse
    {
        $user = $this->userRepository->findById($request->userId);
        if ($user === null) {
            throw new NotFoundException('User not found');
        }

        if ($request->version !== $user->getVersion()) {
            throw new ConflictException(
                'Your profile has been modified by another session. Please refresh and retry.',
                'VERSION_CONFLICT',
            );
        }

        if ($request->name !== null) {
            $user->updateName($request->name);
        }

        if ($request->email !== null) {
            $existing = $this->userRepository->findByEmail($request->email);
            if ($existing !== null && $existing->getId() !== $request->userId) {
                throw new ConflictException('User with this email already exists');
            }
            $user->updateEmail(new Email($request->email));
        }

        if ($request->password !== null) {
            $user->updatePassword(Hash::make($request->password));
        }

        $saved = $this->userRepository->save($user);

        return new UpdateProfileResponse(
            id: $saved->getId(),
            name: $saved->getName(),
            email: $saved->getEmail()->getValue(),
            version: $saved->getVersion(),
            updatedAt: $saved->getUpdatedAt(),
        );
    }
}
