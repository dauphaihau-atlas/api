<?php

namespace Tests\Unit;

use App\Core\Application\Contracts\UserRepositoryInterface;
use App\Core\Application\UseCases\User\UpdateUserAvatar\UpdateUserAvatarUseCase;
use App\Core\Domain\Entities\User;
use App\Core\Domain\ValueObjects\Email;
use App\Exceptions\NotFoundException;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class UpdateUserAvatarUseCaseTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;

    private UpdateUserAvatarUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->useCase = new UpdateUserAvatarUseCase($this->userRepository);
    }

    /**
     * Scenario: User exists; findById returns user, save persists and returns user.
     * Expectation: Returns saved user; user has avatar_path set.
     */
    public function test_execute_updates_avatar_path_and_returns_saved_user(): void
    {
        $user = new User(
            id: 1,
            name: 'Test User',
            email: new Email('test@example.com'),
            password: 'hashed',
            avatarPath: null
        );
        $avatarPath = 'avatars/xyz.jpg';

        $this->userRepository
            ->method('findById')
            ->with(1)
            ->willReturn($user);
        $this->userRepository
            ->method('save')
            ->with($this->callback(function (User $u) use ($avatarPath): bool {
                return $u->getAvatarPath() === $avatarPath;
            }))
            ->willReturnCallback(function (User $u) {
                return $u;
            });

        $result = $this->useCase->execute(1, $avatarPath);

        $this->assertSame($user, $result);
        $this->assertSame($avatarPath, $result->getAvatarPath());
    }

    /**
     * Scenario: User missing; findById returns null.
     * Expectation: Throws NotFoundException with message "User not found".
     */
    public function test_execute_throws_not_found_exception_when_user_missing(): void
    {
        $this->userRepository
            ->method('findById')
            ->with(999)
            ->willReturn(null);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('User not found');

        $this->useCase->execute(999, 'avatars/xyz.jpg');
    }
}
