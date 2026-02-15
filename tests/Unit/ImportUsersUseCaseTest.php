<?php

namespace Tests\Unit;

use App\Core\Application\UseCases\User\ImportUsers\ImportUsersRequest;
use App\Core\Application\UseCases\User\ImportUsers\ImportUsersUseCase;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentUserRepository;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportUsersUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private ImportUsersUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.imports_disk', 'local'));
        $this->useCase = new ImportUsersUseCase(
            new EloquentUserRepository()
        );
    }

    /**
     * Scenario: Empty file.
     * Expectation: 0 created, 0 updated, no errors.
     */
    public function test_execute_returns_zero_counts_for_empty_content(): void
    {
        Storage::disk(config('filesystems.imports_disk', 'local'))->put('imports/empty.csv', '');

        $response = $this->useCase->execute(new ImportUsersRequest('imports/empty.csv'));

        $this->assertSame(0, $response->created);
        $this->assertSame(0, $response->updated);
        $this->assertSame([], $response->errors);
    }

    /**
     * Scenario: Only header row, no data.
     * Expectation: 0 created, 0 updated, no errors.
     */
    public function test_execute_returns_zero_counts_when_only_headers(): void
    {
        $csv = "name,email,password\n";
        Storage::disk(config('filesystems.imports_disk', 'local'))->put('imports/headers_only.csv', $csv);

        $response = $this->useCase->execute(new ImportUsersRequest('imports/headers_only.csv'));

        $this->assertSame(0, $response->created);
        $this->assertSame(0, $response->updated);
        $this->assertSame([], $response->errors);
    }

    /**
     * Scenario: Valid rows create new users.
     * Expectation: created count; users in DB.
     */
    public function test_execute_creates_users_for_valid_rows(): void
    {
        $csv = "name,email,password\nAlice,alice@test.com,password123\nBob,bob@test.com,secret456";
        Storage::disk(config('filesystems.imports_disk', 'local'))->put('imports/valid.csv', $csv);

        $response = $this->useCase->execute(new ImportUsersRequest('imports/valid.csv'));

        $this->assertSame(2, $response->created);
        $this->assertSame(0, $response->updated);
        $this->assertSame([], $response->errors);
        $this->assertDatabaseHas('users', ['email' => 'alice@test.com', 'name' => 'Alice']);
        $this->assertDatabaseHas('users', ['email' => 'bob@test.com', 'name' => 'Bob']);
        $this->assertTrue(Hash::check('password123', UserModel::where('email', 'alice@test.com')->first()->password));
    }

    /**
     * Scenario: Row with existing email updates user.
     * Expectation: updated count; user name/password changed.
     */
    public function test_execute_updates_existing_user_by_email(): void
    {
        UserModel::factory()->create([
            'email' => 'update@test.com',
            'name' => 'Before',
            'password' => Hash::make('oldpass'),
        ]);
        $csv = "name,email,password\nAfter,update@test.com,newpass123";
        Storage::disk(config('filesystems.imports_disk', 'local'))->put('imports/update.csv', $csv);

        $response = $this->useCase->execute(new ImportUsersRequest('imports/update.csv'));

        $this->assertSame(0, $response->created);
        $this->assertSame(1, $response->updated);
        $user = UserModel::where('email', 'update@test.com')->first();
        $this->assertSame('After', $user->name);
        $this->assertTrue(Hash::check('newpass123', $user->password));
    }

    /**
     * Scenario: Invalid email and short password in rows.
     * Expectation: errors array contains row messages; valid row still created.
     */
    public function test_execute_collects_errors_for_invalid_rows(): void
    {
        $csv = "name,email,password\nValid,valid@test.com,password123\nBad Email,invalid-email,password123\nShort,short@test.com,short";
        Storage::disk(config('filesystems.imports_disk', 'local'))->put('imports/mixed.csv', $csv);

        $response = $this->useCase->execute(new ImportUsersRequest('imports/mixed.csv'));

        $this->assertSame(1, $response->created);
        $this->assertCount(2, $response->errors);
        $rowNumbers = array_column($response->errors, 'row');
        // Row 3: invalid email; Row 4: short password (row 2 is header, first data row is 2 = Valid)
        $this->assertContains(3, $rowNumbers);
        $this->assertContains(4, $rowNumbers);
        $this->assertDatabaseHas('users', ['email' => 'valid@test.com']);
    }

    /**
     * Scenario: Missing required headers.
     * Expectation: single error about headers; 0 created, 0 updated.
     */
    public function test_execute_returns_error_when_headers_invalid(): void
    {
        $csv = "foo,bar\nAlice,alice@test.com";
        Storage::disk(config('filesystems.imports_disk', 'local'))->put('imports/bad_headers.csv', $csv);

        $response = $this->useCase->execute(new ImportUsersRequest('imports/bad_headers.csv'));

        $this->assertSame(0, $response->created);
        $this->assertSame(0, $response->updated);
        $this->assertCount(1, $response->errors);
        $this->assertStringContainsString('headers', strtolower($response->errors[0]['message']));
    }
}
