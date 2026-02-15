<?php

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserImportApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.imports_disk', 'local'));
    }

    /**
     * Scenario: No token / invalid token.
     * Expectation: 401
     */
    public function test_users_import_returns_401_when_unauthenticated(): void
    {
        $csv = "name,email,password\nAlice,alice@example.com,password123";
        $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

        $response = $this->postJson('/api/v1/users/import', [
            'file' => $file,
        ]);

        $response->assertStatus(401);
    }

    /**
     * Scenario: Authenticated as non-admin.
     * Expectation: 403
     */
    public function test_users_import_returns_403_when_authenticated_as_non_admin(): void
    {
        $user = UserModel::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;
        $csv = "name,email,password\nAlice,alice@example.com,password123";
        $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

        $response = $this->post('/api/v1/users/import', [
            'file' => $file,
        ], [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(403);
    }

    /**
     * Scenario: No file or non-CSV file.
     * Expectation: 422
     */
    public function test_users_import_returns_422_when_file_missing_or_invalid(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->post('/api/v1/users/import', [], [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422);
    }

    /**
     * Scenario: Admin uploads non-CSV (e.g. image).
     * Expectation: 422
     */
    public function test_users_import_returns_422_when_file_is_not_csv(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;
        $file = UploadedFile::fake()->image('photo.jpg', 100, 100);

        $response = $this->post('/api/v1/users/import', [
            'file' => $file,
        ], [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422);
    }

    /**
     * Scenario: Admin uploads valid CSV; all new users.
     * Expectation: 200; created count; file on disk; users in DB with hashed password.
     */
    public function test_users_import_returns_200_and_creates_users_when_admin_uploads_valid_csv(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;
        $csv = "name,email,password\nAlice One,alice@example.com,password123\nBob Two,bob@example.com,secret456";
        $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

        $response = $this->post('/api/v1/users/import', [
            'file' => $file,
        ], [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('created', 2);
        $response->assertJsonPath('updated', 0);
        $response->assertJsonPath('errors', []);

        $disk = Storage::disk(config('filesystems.imports_disk', 'local'));
        $files = $disk->files('imports');
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.csv', $files[0]);

        $this->assertDatabaseHas('users', [
            'email' => 'alice@example.com',
            'name' => 'Alice One',
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'bob@example.com',
            'name' => 'Bob Two',
        ]);
        $alice = UserModel::where('email', 'alice@example.com')->first();
        $this->assertNotNull($alice);
        $this->assertTrue(Hash::check('password123', $alice->password));
    }

    /**
     * Scenario: Admin uploads CSV with existing email; should update.
     * Expectation: 200; updated count 1; user name/password updated.
     */
    public function test_users_import_updates_existing_user_when_email_matches(): void
    {
        UserModel::factory()->create([
            'email' => 'existing@example.com',
            'name' => 'Old Name',
            'password' => Hash::make('oldpass'),
        ]);
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;
        $csv = "name,email,password\nNew Name,existing@example.com,newpassword123";
        $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

        $response = $this->post('/api/v1/users/import', [
            'file' => $file,
        ], [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('created', 0);
        $response->assertJsonPath('updated', 1);

        $user = UserModel::where('email', 'existing@example.com')->first();
        $this->assertSame('New Name', $user->name);
        $this->assertTrue(Hash::check('newpassword123', $user->password));
    }

    /**
     * Scenario: CSV with some invalid rows (bad email, short password).
     * Expectation: 200; valid rows created; errors array contains row errors.
     */
    public function test_users_import_returns_errors_for_invalid_rows_but_imports_valid_ones(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;
        $csv = "name,email,password\nValid User,valid@example.com,password123\nBad Email,not-an-email,password123\nShort Pass,short@example.com,short\n,empty@example.com,password1234";
        $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

        $response = $this->post('/api/v1/users/import', [
            'file' => $file,
        ], [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('created', 1);
        $errors = $response->json('errors');
        $this->assertIsArray($errors);
        $this->assertGreaterThanOrEqual(3, count($errors));

        $this->assertDatabaseHas('users', ['email' => 'valid@example.com']);
    }
}
