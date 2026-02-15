<?php

namespace Tests\Unit\Domain;

use App\Core\Domain\Entities\User;
use App\Core\Domain\ValueObjects\Email;
use PHPUnit\Framework\TestCase;

class UserAvatarTest extends TestCase
{
    /**
     * Scenario: New user (no avatar_path in constructor).
     * Expectation: getAvatarPath() returns null.
     */
    public function test_new_user_has_null_avatar_path(): void
    {
        $user = new User(
            id: 1,
            name: 'Test',
            email: new Email('test@example.com')
        );

        $this->assertNull($user->getAvatarPath());
    }

    /**
     * Scenario: User calls updateAvatarPath with a path.
     * Expectation: getAvatarPath() returns that path.
     */
    public function test_update_avatar_path_sets_path(): void
    {
        $user = new User(
            id: 1,
            name: 'Test',
            email: new Email('test@example.com')
        );
        $path = 'path/to/file.jpg';

        $user->updateAvatarPath($path);

        $this->assertSame($path, $user->getAvatarPath());
    }

    /**
     * Scenario: User with existing avatar_path calls updateAvatarPath(null).
     * Expectation: getAvatarPath() returns null.
     */
    public function test_update_avatar_path_to_null_clears_path(): void
    {
        $user = new User(
            id: 1,
            name: 'Test',
            email: new Email('test@example.com'),
            avatarPath: 'avatars/old.jpg'
        );

        $user->updateAvatarPath(null);

        $this->assertNull($user->getAvatarPath());
    }

    /**
     * Scenario: User calls updateAvatarPath with a path.
     * Expectation: getUpdatedAt() is updated (timestamp greater than or equal to before).
     */
    public function test_update_avatar_path_updates_updated_at(): void
    {
        $user = new User(
            id: 1,
            name: 'Test',
            email: new Email('test@example.com')
        );
        $before = $user->getUpdatedAt();

        $user->updateAvatarPath('avatars/new.jpg');
        $after = $user->getUpdatedAt();

        $this->assertGreaterThanOrEqual($before->getTimestamp(), $after->getTimestamp());
    }
}
