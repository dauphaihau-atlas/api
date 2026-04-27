<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Application\Contracts\UserRepositoryInterface;
use App\Core\Application\UseCases\User\CreateUser\CreateUserRequest;
use App\Core\Application\UseCases\User\CreateUser\CreateUserUseCase;
use App\Core\Domain\Entities\User;
use App\Core\Domain\ValueObjects\Email;
use App\Exceptions\ConflictException;
use App\Exceptions\InternalServerException;
use DateTimeImmutable;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class CreateUserUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private UserRepositoryInterface&MockObject $userRepository;

    private CreateUserUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->useCase = new CreateUserUseCase($this->userRepository);
    }

    /**
     * Scenario: Email is unique at pre-check and insert succeeds.
     * Expectation: Returns a response with the saved user data.
     */
    public function test_execute_creates_user_and_returns_response(): void
    {
        $savedUser = new User(
            id: 42,
            name: 'Jane Doe',
            email: new Email('jane@example.com'),
            password: 'hashed',
            createdAt: new DateTimeImmutable('2026-01-01 00:00:00'),
        );

        $this->userRepository->method('findByEmail')->willReturn(null);
        $this->userRepository->method('save')->willReturn($savedUser);

        $request = new CreateUserRequest('Jane Doe', 'jane@example.com', 'secret123');
        $response = $this->useCase->execute($request);

        $this->assertSame(42, $response->id);
        $this->assertSame('jane@example.com', $response->email);
    }

    /**
     * Scenario: Email already exists at pre-check time.
     * Expectation: Throws ConflictException before reaching the database.
     */
    public function test_execute_throws_conflict_when_email_exists_at_precheck(): void
    {
        $existing = new User(
            id: 1,
            name: 'Existing',
            email: new Email('jane@example.com'),
            password: 'hashed',
        );

        $this->userRepository->method('findByEmail')->willReturn($existing);
        $this->userRepository->expects($this->never())->method('save');

        $this->expectException(ConflictException::class);

        $this->useCase->execute(new CreateUserRequest('Jane Doe', 'jane@example.com', 'secret123'));
    }

    /**
     * Scenario: Pre-check passes but a concurrent insert causes a unique constraint violation.
     * Expectation: QueryException with SQLSTATE 23xxx is converted to ConflictException.
     */
    public function test_execute_converts_constraint_violation_to_conflict_exception(): void
    {
        $this->userRepository->method('findByEmail')->willReturn(null);
        $this->userRepository->method('save')->willThrowException(
            new QueryException('sqlite', 'INSERT INTO `users`', [], new Exception('Duplicate entry', 23000))
        );

        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('User with this email already exists');

        $this->useCase->execute(new CreateUserRequest('Jane Doe', 'jane@example.com', 'secret123'));
    }

    /**
     * Scenario: Save fails with a non-constraint database error.
     * Expectation: QueryException is re-thrown as InternalServerException.
     */
    public function test_execute_converts_non_constraint_query_exception_to_internal_server_exception(): void
    {
        $this->userRepository->method('findByEmail')->willReturn(null);
        $this->userRepository->method('save')->willThrowException(
            new QueryException('sqlite', 'INSERT INTO `users`', [], new Exception('Disk full', 0))
        );

        $this->expectException(InternalServerException::class);

        $this->useCase->execute(new CreateUserRequest('Jane Doe', 'jane@example.com', 'secret123'));
    }
}
