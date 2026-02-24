<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\Models\ActivityLogModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_user_via_api_logs_activity_with_causer(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        ActivityLogModel::query()->delete();

        $response = $this->postJson('/api/v1/users', [
            'name' => 'API Created User',
            'email' => 'apiuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(201);
        $userId = $response->json('data.id');
        $this->assertNotNull($userId);

        $this->assertDatabaseCount('activity_logs', 1);

        $log = ActivityLogModel::where('event', 'created')
            ->where('subject_id', $userId)
            ->where('subject_type', UserModel::class)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('created', $log->event);
        $this->assertSame((string) $admin->id, (string) $log->causer_id);
        $this->assertSame(UserModel::class, $log->causer_type);
        $this->assertArrayNotHasKey('password', $log->new_values ?? []);
    }
}
