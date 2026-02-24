<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\Models\ActivityLogModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_activity_logs_returns_403_for_regular_user(): void
    {
        $user = UserModel::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/v1/activity-logs', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(403);
    }

    public function test_list_activity_logs_returns_401_without_auth(): void
    {
        $response = $this->getJson('/api/v1/activity-logs');

        $response->assertStatus(401);
    }

    public function test_list_activity_logs_returns_200_and_data_for_admin(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        ActivityLogModel::query()->delete();

        ActivityLogModel::create([
            'log_name' => 'default',
            'event' => 'created',
            'subject_type' => UserModel::class,
            'subject_id' => 1,
            'causer_type' => UserModel::class,
            'causer_id' => $admin->id,
            'properties' => ['name' => 'Test', 'email' => 'test@example.com'],
        ]);

        $response = $this->getJson('/api/v1/activity-logs', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'log_name',
                    'event',
                    'subject_type',
                    'subject_id',
                    'causer_type',
                    'causer_id',
                    'causer_name',
                    'causer_email',
                    'properties',
                    'old_values',
                    'new_values',
                    'created_at',
                ],
            ],
            'meta' => ['total', 'per_page', 'current_page'],
        ]);
        $this->assertSame(1, $response->json('meta.total'));
    }

    public function test_list_activity_logs_respects_event_filter(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        ActivityLogModel::create([
            'log_name' => 'default',
            'event' => 'created',
            'subject_type' => UserModel::class,
            'subject_id' => 1,
            'causer_type' => null,
            'causer_id' => null,
            'properties' => null,
        ]);
        ActivityLogModel::create([
            'log_name' => 'default',
            'event' => 'updated',
            'subject_type' => UserModel::class,
            'subject_id' => 1,
            'causer_type' => null,
            'causer_id' => null,
            'properties' => null,
        ]);

        $response = $this->getJson('/api/v1/activity-logs?event=updated', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame('updated', $response->json('data.0.event'));
    }

    public function test_show_activity_log_returns_404_for_missing_id(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/v1/activity-logs/99999', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(404);
    }

    public function test_show_activity_log_returns_403_for_regular_user(): void
    {
        $user = UserModel::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $log = ActivityLogModel::create([
            'log_name' => 'default',
            'event' => 'created',
            'subject_type' => UserModel::class,
            'subject_id' => 1,
            'causer_type' => null,
            'causer_id' => null,
            'properties' => null,
        ]);

        $response = $this->getJson('/api/v1/activity-logs/'.$log->id, [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(403);
    }

    public function test_show_activity_log_returns_200_and_data_for_admin(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $log = ActivityLogModel::create([
            'log_name' => 'default',
            'event' => 'created',
            'subject_type' => UserModel::class,
            'subject_id' => 1,
            'causer_type' => UserModel::class,
            'causer_id' => $admin->id,
            'properties' => ['name' => 'New User', 'email' => 'new@example.com'],
        ]);

        $response = $this->getJson('/api/v1/activity-logs/'.$log->id, [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $log->id);
        $response->assertJsonPath('data.event', 'created');
        $response->assertJsonPath('data.causer_name', $admin->name);
        $response->assertJsonPath('data.causer_email', $admin->email);
    }

    public function test_user_activity_logs_returns_activity_for_that_user_as_subject(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $targetUser = UserModel::factory()->create();
        $token = $admin->createToken('test')->plainTextToken;

        ActivityLogModel::query()->delete();

        ActivityLogModel::create([
            'log_name' => 'default',
            'event' => 'created',
            'subject_type' => UserModel::class,
            'subject_id' => $targetUser->id,
            'causer_type' => UserModel::class,
            'causer_id' => $admin->id,
            'properties' => null,
        ]);
        ActivityLogModel::create([
            'log_name' => 'default',
            'event' => 'created',
            'subject_type' => UserModel::class,
            'subject_id' => $admin->id,
            'causer_type' => null,
            'causer_id' => null,
            'properties' => null,
        ]);

        $response = $this->getJson('/api/v1/users/'.$targetUser->id.'/activity-logs', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame((string) $targetUser->id, (string) $response->json('data.0.subject_id'));
    }
}
