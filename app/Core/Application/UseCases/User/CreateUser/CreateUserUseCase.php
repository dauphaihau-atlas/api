<?php

namespace App\Core\Application\UseCases\User\CreateUser;

use App\Core\Application\Contracts\UserRepositoryInterface;
use App\Core\Application\Services\EmailServiceInterface;
use App\Core\Domain\Entities\User;
use App\Core\Domain\ValueObjects\Email;
use Illuminate\Support\Facades\Hash;

class CreateUserUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly EmailServiceInterface $emailService
    ) {
    }

    public function execute(CreateUserRequest $request): CreateUserResponse
    {
        $existingUser = $this->userRepository->findByEmail($request->email);
        if ($existingUser !== null) {
            throw new \RuntimeException('User with this email already exists');
        }

        $email = new Email($request->email);
        $user = new User(
            id: null,
            name: $request->name,
            email: $email,
            password: Hash::make($request->password)
        );

        $savedUser = $this->userRepository->save($user);

        $this->emailService->send(
            $savedUser->getEmail()->getValue(),
            'Welcome!',
            "Welcome {$savedUser->getName()}!"
        );

        $id = $savedUser->getId();
        if ($id === null) {
            throw new \RuntimeException('User was saved but ID was not returned');
        }

        return new CreateUserResponse(
            id: $id,
            name: $savedUser->getName(),
            email: $savedUser->getEmail()->getValue(),
            createdAt: $savedUser->getCreatedAt()
        );
    }
}
