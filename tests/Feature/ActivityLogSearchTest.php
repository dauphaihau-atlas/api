<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\Models\ActivityLogModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_activity_logs_by_causer_name(): void
    {
        $admin = UserModel::factory()->admin()->create(['name' => 'Admin Boss']);
        $otherUser = UserModel::factory()->create(['name' => 'Charlie Delta']);
        $token = $admin->createToken('test')->plainTextToken;

        ActivityLogModel::query()->delete();

        ActivityLogModel::create([
            'log_name' => 'default',
            'event' => 'created',
            'subject_type' => UserModel::class,
            'subject_id' => 1,
            'causer_type' => UserModel::class,
            'causer_id' => $admin->id,
            'properties' => null,
        ]);
        ActivityLogModel::create([
            'log_name' => 'default',
            'event' => 'updated',
            'subject_type' => UserModel::class,
            'subject_id' => 1,
            'causer_type' => UserModel::class,
            'causer_id' => $otherUser->id,
            'properties' => null,
        ]);

        $response = $this->getJson('/api/v1/activity-logs?search=Charlie', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame($otherUser->id, $response->json('data.0.causer_id'));
    }

    public function test_search_activity_logs_by_event(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        ActivityLogModel::query()->delete();

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
            'event' => 'deleted',
            'subject_type' => UserModel::class,
            'subject_id' => 1,
            'causer_type' => null,
            'causer_id' => null,
            'properties' => null,
        ]);

        $response = $this->getJson('/api/v1/activity-logs?search=deleted', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame('deleted', $response->json('data.0.event'));
    }

    public function test_search_activity_logs_returns_empty_for_no_match(): void
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
            'properties' => null,
        ]);

        $response = $this->getJson('/api/v1/activity-logs?search=nonexistent', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(200);
        $this->assertSame(0, $response->json('meta.total'));
    }

    public function test_search_user_activity_logs(): void
    {
        $admin = UserModel::factory()->admin()->create(['name' => 'Admin Boss']);
        $targetUser = UserModel::factory()->create(['name' => 'Target User']);
        $otherCauser = UserModel::factory()->create(['name' => 'Other Causer']);
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
            'event' => 'updated',
            'subject_type' => UserModel::class,
            'subject_id' => $targetUser->id,
            'causer_type' => UserModel::class,
            'causer_id' => $otherCauser->id,
            'properties' => null,
        ]);

        $response = $this->getJson('/api/v1/users/'.$targetUser->id.'/activity-logs?search=Other', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame($otherCauser->id, $response->json('data.0.causer_id'));
    }

    public function test_empty_search_returns_all_activity_logs(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        ActivityLogModel::query()->delete();

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

        $response = $this->getJson('/api/v1/activity-logs?search=', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(200);
        $this->assertSame(2, $response->json('meta.total'));
    }
}
