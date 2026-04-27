<?php

namespace App\Core\Application\UseCases\User\CreateUser;

use App\Core\Application\Contracts\UserRepositoryInterface;
use App\Core\Domain\Entities\User;
use App\Core\Domain\ValueObjects\Email;
use App\Events\UserCreated;
use App\Exceptions\ConflictException;
use App\Exceptions\InternalServerException;
use Illuminate\Support\Facades\Hash;

class CreateUserUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    public function execute(CreateUserRequest $request): CreateUserResponse
    {
        $existingUser = $this->userRepository->findByEmail($request->email);
        if ($existingUser !== null) {
            throw new ConflictException('User with this email already exists');
        }

        $email = new Email($request->email);
        $user = new User(
            id: null,
            name: $request->name,
            email: $email,
            password: Hash::make($request->password)
        );

        $savedUser = $this->userRepository->save($user);

        $id = $savedUser->getId();
        if ($id === null) {
            throw new InternalServerException('User was saved but ID was not returned');
        }

        UserCreated::dispatch($savedUser);

        return new CreateUserResponse(
            id: $id,
            name: $savedUser->getName(),
            email: $savedUser->getEmail()->getValue(),
            createdAt: $savedUser->getCreatedAt()
        );
    }
}
