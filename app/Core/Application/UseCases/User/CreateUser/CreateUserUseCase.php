<?php

namespace App\Core\Application\UseCases\User\CreateUser;

use App\Core\Application\Contracts\UserRepositoryInterface;
use App\Core\Application\Database\SqlState;
use App\Core\Domain\Entities\User;
use App\Core\Domain\ValueObjects\Email;
use App\Events\UserCreated;
use App\Exceptions\ConflictException;
use App\Exceptions\InternalServerException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
        $password = $request->password !== null
            ? Hash::make($request->password)
            : Hash::make(Str::random(40));
        $user = new User(
            id: null,
            name: $request->name,
            email: $email,
            password: $password
        );

        $inviteToken = $request->sendInvite ? Str::random(64) : null;

        try {
            $savedUser = DB::transaction(function () use ($user, $request, $inviteToken): User {
                $savedUser = $this->userRepository->save($user);
                $savedUser = $this->userRepository->assignRole($savedUser, $request->role);

                if ($inviteToken !== null) {
                    $this->userRepository->createInvitation($savedUser, $inviteToken);
                }

                return $savedUser;
            });
        } catch (QueryException $e) {
            if (str_starts_with((string) $e->getCode(), SqlState::INTEGRITY_CONSTRAINT)) {
                throw new ConflictException('User with this email already exists');
            }
            throw new InternalServerException('Failed to create user');
        }

        $id = $savedUser->getId();
        if ($id === null) {
            throw new InternalServerException('User was saved but ID was not returned');
        }

        if ($inviteToken !== null) {
            $this->userRepository->sendInvite($savedUser, $inviteToken);
        } else {
            UserCreated::dispatch($savedUser);
        }

        return new CreateUserResponse(
            id: $id,
            name: $savedUser->getName(),
            email: $savedUser->getEmail()->getValue(),
            createdAt: $savedUser->getCreatedAt(),
            roles: array_map(fn ($role) => $role->getSlug(), $savedUser->getRoles()),
            invitationStatus: $inviteToken !== null ? 'sent' : 'not_sent',
        );
    }
}
